<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Models\Member;
use App\Models\MemberCardTransaction;
use App\Support\CardTopUps;
use App\Support\Payments\Payments;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

/**
 * Topping up the budget on a member card: from the public page (card number + last name) or from the
 * member's own page behind the QR code.
 */
class CardTopUpController extends Controller
{
    public function __construct(private CardTopUps $topUps) {}

    public function create(): View
    {
        return view('card-topups.create', ['topUps' => $this->topUps]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->topUps->enabled(), 404);

        $data = $request->validate([
            'card_number' => ['required', 'string', 'regex:'.Member::CARD_PATTERN],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            ...$this->paymentRules(),
        ], ['card_number.regex' => 'Het kaartnummer bestaat uit 5 cijfers (onder de barcode op je lidkaart).'], ['card_number' => 'kaartnummer', 'last_name' => 'naam', 'amount' => 'bedrag']);

        $member = $this->topUps->findMember($data['card_number'], $data['last_name']);

        if (! $member) {
            return back()->withInput()->withErrors(['card_number' => 'Dit kaartnummer en deze naam horen niet bij elkaar. Kijk ze na of vraag het aan de toog.']);
        }

        return $this->startPayment($member, $data, $request);
    }

    /**
     * From the member's own page: no card number needed, the QR link identifies them.
     */
    public function storeForMember(Request $request, string $token): RedirectResponse
    {
        abort_unless($this->topUps->enabled(), 404);
        $member = Member::query()->where('token', $token)->firstOrFail();

        $data = $request->validate($this->paymentRules(), [], ['amount' => 'bedrag']);

        if (blank($member->card_number)) {
            return back()->withErrors(['amount' => 'Er is nog geen lidkaart aan je gekoppeld. Vraag er een aan de toog.']);
        }

        return $this->startPayment($member, $data, $request);
    }

    public function show(Request $request, MemberCardTransaction $topUp): View
    {
        if ($topUp->isPending()) {
            try {
                $topUp = $this->topUps->sync($topUp);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('card-topups.show', [
            'topUp' => $topUp->load('member'),
            'cancelledAtProvider' => $request->boolean('geannuleerd'),
        ]);
    }

    /**
     * Back to the payment page of an open top-up (e.g. after closing the tab).
     */
    public function pay(MemberCardTransaction $topUp): RedirectResponse
    {
        abort_unless($topUp->isPending() && filled($topUp->checkout_url) && $topUp->created_at->gt(now()->subMinutes(30)), 404);

        return redirect()->away($topUp->checkout_url);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function paymentRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:'.$this->topUps->minimum(), 'max:'.$this->topUps->maximum()],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function startPayment(Member $member, array $data, Request $request): RedirectResponse
    {
        $available = app(Payments::class)->available();
        $provider = PaymentProvider::tryFrom((string) ($data['provider'] ?? '')) ?? $available[0];

        try {
            [, $url] = $this->topUps->start($member, round((float) $data['amount'], 2), $provider, $data['email'] ?? null, $request->ip());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['amount' => 'De betaling kon niet gestart worden. Probeer het later opnieuw.']);
        }

        return redirect()->away($url);
    }
}
