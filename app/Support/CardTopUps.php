<?php

namespace App\Support;

use App\Enums\CardTransactionStatus;
use App\Enums\CardTransactionType;
use App\Enums\PaymentProvider;
use App\Mail\CardTopUpMail;
use App\Models\Member;
use App\Models\MemberCardTransaction;
use App\Models\User;
use App\Support\Payments\CheckoutRequest;
use App\Support\Payments\PaymentFailed;
use App\Support\Payments\Payments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Budget on the member card: members top it up online (Stripe/SumUp) and spend it at the POS.
 * Settings under "topup" (Instellingen → Leden).
 */
class CardTopUps
{
    public function enabled(): bool
    {
        return (bool) settings()->get('topup.enabled') && app(Payments::class)->available() !== [];
    }

    /**
     * @return list<int>
     */
    public function presets(): array
    {
        return collect(explode(',', (string) settings()->get('topup.presets')))
            ->map(fn (string $amount): int => (int) trim($amount))
            ->filter(fn (int $amount): bool => $amount >= $this->minimum() && $amount <= $this->maximum())
            ->unique()->sort()->values()->all();
    }

    public function minimum(): float
    {
        return (float) settings()->get('topup.min');
    }

    public function maximum(): float
    {
        return (float) settings()->get('topup.max');
    }

    public function maximumBalance(): ?float
    {
        $max = settings()->get('topup.max_balance');

        return filled($max) ? (float) $max : null;
    }

    /**
     * The card holder for a card number and last name, or null when they do not belong together.
     */
    public function findMember(string $cardNumber, string $lastName): ?Member
    {
        $member = Member::findByCard($cardNumber);
        $normalise = fn (?string $value): string => Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim((string) $value))));

        return $member && $normalise($member->last_name) === $normalise($lastName) ? $member : null;
    }

    /**
     * A reason why this amount cannot be added now, or null when it can.
     */
    public function problem(Member $member, float $amount): ?string
    {
        if ($amount < $this->minimum() || $amount > $this->maximum()) {
            return 'Je kan tussen '.euro($this->minimum()).' en '.euro($this->maximum()).' opladen.';
        }

        if (($max = $this->maximumBalance()) !== null && $member->cardBalance() + $amount > $max) {
            return 'Er mag maximaal '.euro($max).' op een kaart staan. Je kan nog '.euro(max(0, $max - $member->cardBalance())).' opladen.';
        }

        return null;
    }

    /**
     * Creates the pending top-up and returns it with the URL of the hosted checkout.
     *
     * @return array{0: MemberCardTransaction, 1: string}
     */
    public function start(Member $member, float $amount, PaymentProvider $provider, ?string $email = null, ?string $ip = null): array
    {
        if (! in_array($provider, app(Payments::class)->available(), true)) {
            throw new InvalidArgumentException('Deze betaalmethode is niet beschikbaar.');
        }

        if ($problem = $this->problem($member, $amount)) {
            throw new InvalidArgumentException($problem);
        }

        $topUp = $member->cardTransactions()->create([
            'amount' => round($amount, 2),
            'type' => CardTransactionType::TopUpOnline,
            'status' => CardTransactionStatus::Pending,
            'description' => 'Online opgeladen',
            'provider' => $provider,
            'email' => $email ?: $member->email,
            'token' => Str::random(40),
            'ip' => $ip,
        ]);

        $checkout = app(Payments::class)->gateway($provider)->createCheckout(new CheckoutRequest(
            amount: (float) $topUp->amount,
            description: 'Lidkaart opladen - '.settings()->siteName(),
            reference: 'OPL-'.$topUp->id,
            returnUrl: $topUp->publicUrl().'?betaald=1',
            cancelUrl: $topUp->publicUrl().'?geannuleerd=1',
            email: $topUp->email,
            metadata: ['card_topup_id' => $topUp->id],
        ));

        $topUp->update(['provider_reference' => $checkout['reference'], 'checkout_url' => $checkout['url']]);

        return [$topUp, $checkout['url']];
    }

    /**
     * Asks the provider about a pending online top-up (return page, webhook, scheduled run).
     */
    public function sync(MemberCardTransaction $topUp): MemberCardTransaction
    {
        if (! $topUp->isPending() || ! $topUp->provider?->isOnline() || blank($topUp->provider_reference)) {
            return $topUp;
        }

        try {
            $transaction = app(Payments::class)->gateway($topUp->provider)->checkoutTransaction($topUp->provider_reference);
        } catch (PaymentFailed) {
            $topUp->update(['status' => CardTransactionStatus::Failed]);

            return $topUp;
        }

        if ($transaction !== null) {
            $this->complete($topUp, $transaction);
        }

        return $topUp->refresh();
    }

    /**
     * Idempotent: books the top-up once and mails a confirmation. A payment after expiry still counts.
     */
    public function complete(MemberCardTransaction $topUp, ?string $transaction = null): void
    {
        $completed = DB::transaction(function () use ($topUp, $transaction): bool {
            $locked = MemberCardTransaction::query()->lockForUpdate()->findOrFail($topUp->id);

            if ($locked->isCompleted()) {
                return false;
            }

            $locked->update(['status' => CardTransactionStatus::Completed, 'completed_at' => now(), 'provider_transaction' => $transaction ?? $locked->provider_transaction]);

            return true;
        });

        if ($completed) {
            $this->mailConfirmation($topUp->refresh());
        }
    }

    public function mailConfirmation(MemberCardTransaction $topUp): void
    {
        if (blank($topUp->email) || ! Mailing::allows('card_topup')) {
            return;
        }

        try {
            Mail::to($topUp->email)->send(new CardTopUpMail($topUp));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * A movement booked by hand in the admin site (top-up at the bar, refund, correction).
     */
    public function book(Member $member, float $amount, CardTransactionType $type, ?string $description, ?User $user): MemberCardTransaction
    {
        if (round($amount, 2) == 0.0) {
            throw new InvalidArgumentException('Het bedrag mag geen 0 zijn.');
        }

        return $member->cardTransactions()->create([
            'amount' => round($amount, 2),
            'type' => $type,
            'status' => CardTransactionStatus::Completed,
            'description' => $description,
            'created_by' => $user?->id,
            'completed_at' => now(),
        ]);
    }

    /**
     * Checks open online top-ups once more; after a day an unpaid one is given up.
     */
    public function expireStale(): int
    {
        $failed = 0;

        MemberCardTransaction::query()
            ->where('status', CardTransactionStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(15))
            ->each(function (MemberCardTransaction $topUp) use (&$failed): void {
                try {
                    $topUp = $this->sync($topUp);
                } catch (Throwable $exception) {
                    report($exception);
                }

                if ($topUp->isPending() && $topUp->created_at->lt(now()->subDay())) {
                    $topUp->update(['status' => CardTransactionStatus::Failed]);
                }

                $failed += $topUp->status === CardTransactionStatus::Failed ? 1 : 0;
            });

        return $failed;
    }
}
