<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Models\Event;
use App\Models\Order;
use App\Support\Payments\Payments;
use App\Support\Ticketing\TicketSales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Throwable;

class OrderController extends Controller
{
    public function store(Request $request, Event $event, TicketSales $sales, Payments $payments): RedirectResponse
    {
        abort_unless($event->is_published, 404);

        $available = array_map(fn (PaymentProvider $provider): string => $provider->value, $payments->available());

        $data = $request->validate([
            'tickets' => ['required', 'array'],
            'tickets.*' => ['nullable', 'integer', 'min:0', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'provider' => ['nullable', Rule::in($available)],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ], [
            'terms.accepted' => 'Je moet akkoord gaan met de algemene voorwaarden.',
        ], [
            'name' => 'naam', 'email' => 'e-mail', 'phone' => 'telefoon', 'provider' => 'betaalmethode',
        ]);

        $provider = isset($data['provider']) ? PaymentProvider::from($data['provider']) : (count($available) === 1 ? PaymentProvider::from($available[0]) : null);

        $order = $sales->reserve($event, $data['tickets'], $data, $provider, $request->ip());

        if ($order->isPaid()) {
            return redirect()->to($order->publicUrl());
        }

        try {
            return redirect()->away($sales->checkoutUrl($order));
        } catch (Throwable $exception) {
            report($exception);
            $sales->cancel($order, refund: false);

            return back()->withInput()->withErrors(['tickets' => 'De betaling kon niet gestart worden. Probeer het later opnieuw of kies een andere betaalmethode.'])->withFragment('tickets');
        }
    }

    public function show(Request $request, Order $order, TicketSales $sales): View
    {
        if ($order->isPending()) {
            try {
                $order = $sales->sync($order);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('orders.show', [
            'order' => $order->load('event', 'items', 'tickets'),
            'cancelledAtProvider' => $request->boolean('geannuleerd'),
        ]);
    }

    /**
     * Back to the payment page for a pending order that has not expired (e.g. after closing the tab).
     */
    public function pay(Order $order, TicketSales $sales): RedirectResponse
    {
        abort_unless($order->isPending() && $order->expires_at?->isFuture() && $order->provider?->isOnline(), 404);

        try {
            return redirect()->away($sales->checkoutUrl($order));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->to($order->publicUrl())->withErrors(['payment' => 'De betaling kon niet gestart worden.']);
        }
    }

    public function tickets(Order $order, TicketSales $sales): Response
    {
        abort_unless($order->isPaid(), 404);

        return $sales->pdf($order->tickets()->valid()->get())->download('tickets-'.$order->number.'.pdf');
    }
}
