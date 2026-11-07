<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Support\Billit\Billit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Billit order notifications. Only the order id is taken from the payload; everything else is fetched from the API.
 */
class BillitWebhookController extends Controller
{
    public function __invoke(Request $request, Billit $billit): Response
    {
        if (! $billit->client()->verifyWebhook($request->getContent(), $request->header('Billit-Signature'))) {
            return response('Invalid signature', 400);
        }

        $orderId = (int) ($request->json('OrderID') ?? $request->json('EntityID') ?? 0);

        if ($orderId === 0) {
            return response('ok');
        }

        try {
            if ($invoice = Invoice::query()->where('billit_order_id', $orderId)->first()) {
                $billit->sync($invoice);
            } elseif (settings()->get('billit.import_incoming') && PurchaseInvoice::query()->where('billit_order_id', $orderId)->doesntExist()) {
                $order = $billit->client()->order($orderId);

                if (($order['OrderDirection'] ?? null) === 'Cost' && in_array($order['OrderType'] ?? null, ['Invoice', 'CreditNote'], true)) {
                    $billit->importOrder($order);
                }
            }
        } catch (Throwable $exception) {
            report($exception);

            return response('retry later', 503);
        }

        return response('ok');
    }
}
