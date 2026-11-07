<?php

namespace App\Support\Ticketing;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Mail\OrderTicketsMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Payments\PaymentFailed;
use App\Support\Payments\Payments;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Selling tickets: reserving (with capacity checks under lock), marking paid, issuing and mailing tickets,
 * box-office sales, cancelling and refunding.
 */
class TicketSales
{
    /**
     * Creates a pending order that holds the tickets for a while. Free orders are paid at once.
     *
     * @param  array<int|string, int|string|null>  $quantities  ticket type id => quantity
     * @param  array{name: string, email: string, phone?: string|null}  $buyer
     *
     * @throws ValidationException
     */
    public function reserve(Event $event, array $quantities, array $buyer, ?PaymentProvider $provider, ?string $ip = null): Order
    {
        $quantities = collect($quantities)->map(fn ($quantity): int => (int) $quantity)->filter(fn (int $quantity): bool => $quantity > 0);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages(['tickets' => 'Kies minstens één ticket.']);
        }

        $order = DB::transaction(function () use ($event, $quantities, $buyer, $provider, $ip): Order {
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $types = TicketType::query()->where('event_id', $event->id)->whereKey($quantities->keys())->lockForUpdate()->get()->keyBy('id');
            $event->setRelation('ticketTypes', $event->ticketTypes()->get());

            if (! $event->sellsTickets() || ! settings()->get('tickets_open', true)) {
                throw ValidationException::withMessages(['tickets' => 'De ticketverkoop voor dit evenement is gesloten.']);
            }

            $total = 0.0;

            foreach ($quantities as $typeId => $quantity) {
                $type = $types->get($typeId);

                if (! $type || ! $type->setRelation('event', $event)->isOnSale()) {
                    throw ValidationException::withMessages(['tickets' => 'Een gekozen ticket is niet (meer) te koop.']);
                }

                if ($quantity > $type->max_per_order) {
                    throw ValidationException::withMessages(['tickets' => "Maximaal {$type->max_per_order} tickets \"{$type->name}\" per bestelling."]);
                }

                $remaining = $type->remaining();
                if ($remaining !== null && $quantity > $remaining) {
                    throw ValidationException::withMessages(['tickets' => $remaining === 0 ? "\"{$type->name}\" is uitverkocht." : "Er zijn nog maar {$remaining} tickets \"{$type->name}\"."]);
                }

                $total += $quantity * (float) $type->price;
            }

            $eventRemaining = $event->remainingCapacity();
            if ($eventRemaining !== null && $quantities->sum() > $eventRemaining) {
                throw ValidationException::withMessages(['tickets' => $eventRemaining === 0 ? 'Dit evenement is uitverkocht.' : "Er zijn nog maar {$eventRemaining} tickets."]);
            }

            if ($total > 0 && $provider === null) {
                throw ValidationException::withMessages(['provider' => 'Kies een betaalmethode.']);
            }

            $order = Order::query()->create([
                'event_id' => $event->id,
                'name' => $buyer['name'],
                'email' => $buyer['email'],
                'phone' => $buyer['phone'] ?? null,
                'total' => round($total, 2),
                'provider' => $total > 0 ? $provider : PaymentProvider::Free,
                'expires_at' => now()->addMinutes((int) settings()->get('payments.order_minutes', 30)),
                'ip_address' => $ip,
            ]);

            foreach ($quantities as $typeId => $quantity) {
                $type = $types->get($typeId);
                $order->items()->create(['ticket_type_id' => $type->id, 'name' => $type->name, 'quantity' => $quantity, 'unit_price' => $type->price]);
            }

            return $order;
        });

        if ((float) $order->total === 0.0) {
            $this->markPaid($order);
        }

        return $order->refresh();
    }

    /**
     * The hosted checkout URL of the order's provider.
     */
    public function checkoutUrl(Order $order): string
    {
        return app(Payments::class)->gateway($order->provider)->startCheckout($order);
    }

    /**
     * Asks the provider for the status of a pending online order (return page, webhook, expiry run).
     */
    public function sync(Order $order): Order
    {
        if (! $order->isPending() || ! $order->provider?->isOnline()) {
            return $order;
        }

        try {
            $transaction = app(Payments::class)->gateway($order->provider)->paidTransaction($order);
        } catch (PaymentFailed) {
            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            return $order;
        }

        if ($transaction !== null) {
            $this->markPaid($order, $transaction);
        }

        return $order->refresh();
    }

    /**
     * Idempotent: issues one ticket per seat and mails them. A payment that arrives after expiry is still honoured.
     */
    public function markPaid(Order $order, ?string $transaction = null): void
    {
        $issued = DB::transaction(function () use ($order, $transaction): bool {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->isPaid()) {
                return false;
            }

            $locked->update(['status' => OrderStatus::Paid, 'paid_at' => now(), 'provider_transaction' => $transaction ?? $locked->provider_transaction]);

            foreach ($locked->items()->get() as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $locked->tickets()->create(['event_id' => $locked->event_id, 'ticket_type_id' => $item->ticket_type_id, 'holder_name' => $locked->name]);
                }
            }

            return true;
        });

        if ($issued) {
            $this->mailTickets($order->refresh());
        }
    }

    public function mailTickets(Order $order): void
    {
        if (blank($order->email)) {
            return;
        }

        try {
            Mail::to($order->email)->send(new OrderTicketsMail($order));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * A sale at the door or in advance by a board member: paid at once (cash or card), tickets issued.
     */
    public function boxOffice(TicketType $type, int $quantity, PaymentProvider $provider, User $seller, ?string $name = null, ?string $email = null): Order
    {
        if (! in_array($provider, [PaymentProvider::Cash, PaymentProvider::Card, PaymentProvider::Free], true)) {
            throw new RuntimeException('Aan de kassa kan enkel cash, kaart of gratis.');
        }

        $order = DB::transaction(function () use ($type, $quantity, $provider, $seller, $name, $email): Order {
            $type = TicketType::query()->lockForUpdate()->findOrFail($type->id);
            $remaining = $type->remaining();

            if ($remaining !== null && $quantity > $remaining) {
                throw new RuntimeException("Er zijn nog maar {$remaining} tickets \"{$type->name}\".");
            }

            $order = Order::query()->create([
                'event_id' => $type->event_id,
                'name' => $name ?: 'Kassa',
                'email' => $email ?? '',
                'total' => $provider === PaymentProvider::Free ? 0 : round($quantity * (float) $type->price, 2),
                'provider' => $provider,
                'user_id' => $seller->id,
            ]);
            $order->items()->create(['ticket_type_id' => $type->id, 'name' => $type->name, 'quantity' => $quantity, 'unit_price' => $provider === PaymentProvider::Free ? 0 : $type->price]);

            return $order;
        });

        $this->markPaid($order);

        return $order->refresh();
    }

    /**
     * Cancels an order and its tickets; with $refund, paid online orders are refunded through the provider first.
     */
    public function cancel(Order $order, bool $refund): void
    {
        if ($refund && $order->isPaid() && $order->provider?->isOnline() && (float) $order->total > 0) {
            app(Payments::class)->gateway($order->provider)->refund($order);
        }

        DB::transaction(function () use ($order, $refund): void {
            $order->tickets()->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
            $order->update($refund && $order->isPaid()
                ? ['status' => OrderStatus::Refunded, 'refunded_at' => now()]
                : ['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
        });
    }

    /**
     * Pending orders past their time: checked with the provider one last time, then released.
     */
    public function expireStale(): int
    {
        $expired = 0;

        Order::query()->where('status', OrderStatus::Pending)->where('expires_at', '<', now()->subMinutes(5))->each(function (Order $order) use (&$expired): void {
            try {
                $this->sync($order);
            } catch (Throwable $exception) {
                report($exception);
            }

            if ($order->refresh()->isPending()) {
                $order->update(['status' => OrderStatus::Expired]);
                $expired++;
            }
        });

        return $expired;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function pdf(Collection $tickets): DomPdf
    {
        $tickets->loadMissing('event', 'ticketType', 'order');

        return Pdf::loadView('pdf.tickets', [
            'tickets' => $tickets,
            'qrs' => $tickets->mapWithKeys(fn (Ticket $ticket): array => [$ticket->id => Qr::pngDataUri($ticket->code, 8)]),
            'settings' => settings(),
        ])->setPaper('a4');
    }
}
