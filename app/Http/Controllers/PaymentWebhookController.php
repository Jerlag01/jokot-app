<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Models\MemberCardTransaction;
use App\Models\Order;
use App\Support\CardTopUps;
use App\Support\Payments\StripeGateway;
use App\Support\Ticketing\TicketSales;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Payment notifications. Neither handler trusts the payload's status: Stripe's signature is checked and the
 * order is then synced from the API, SumUp's checkout is always fetched from the API.
 */
class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, StripeGateway $stripe, TicketSales $sales): Response
    {
        if (! $stripe->verifyWebhook($request->getContent(), $request->header('Stripe-Signature'))) {
            return response('Invalid signature', 400);
        }

        $session = $request->json('data.object', []);

        if (str_starts_with((string) $request->json('type'), 'checkout.session.')) {
            $order = Order::query()
                ->where('provider', PaymentProvider::Stripe)
                ->where(fn ($query) => $query->where('provider_reference', $session['id'] ?? '')->orWhere('id', $session['metadata']['order_id'] ?? 0))
                ->first();

            if ($order) {
                $sales->sync($order);
            }

            $topUp = MemberCardTransaction::query()
                ->where('provider', PaymentProvider::Stripe)
                ->where(fn ($query) => $query->where('provider_reference', $session['id'] ?? '')->orWhere('id', $session['metadata']['card_topup_id'] ?? 0))
                ->first();

            if ($topUp) {
                app(CardTopUps::class)->sync($topUp);
            }
        }

        return response('ok');
    }

    public function sumup(Request $request, TicketSales $sales): Response
    {
        $checkoutId = (string) $request->input('id', '');

        if ($checkoutId !== '') {
            $order = Order::query()->where('provider', PaymentProvider::SumUp)->where('provider_reference', $checkoutId)->first();

            if ($order) {
                $sales->sync($order);
            }

            $topUp = MemberCardTransaction::query()->where('provider', PaymentProvider::SumUp)->where('provider_reference', $checkoutId)->first();

            if ($topUp) {
                app(CardTopUps::class)->sync($topUp);
            }
        }

        return response('ok');
    }
}
