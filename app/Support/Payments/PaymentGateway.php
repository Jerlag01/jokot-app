<?php

namespace App\Support\Payments;

use App\Enums\PaymentProvider;
use App\Models\Order;

interface PaymentGateway
{
    public function provider(): PaymentProvider;

    public function isConfigured(): bool;

    /**
     * Creates the hosted checkout, stores its reference on the order and returns the URL to send the buyer to.
     */
    public function startCheckout(Order $order): string;

    /**
     * Asks the provider whether the order is paid; returns the provider's transaction id when it is, null otherwise.
     * Throws PaymentFailed when the provider says the payment failed for good.
     */
    public function paidTransaction(Order $order): ?string;

    public function refund(Order $order): void;
}
