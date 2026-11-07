<?php

namespace App\Support\Payments;

use App\Enums\PaymentProvider;
use InvalidArgumentException;

class Payments
{
    public function gateway(PaymentProvider $provider): PaymentGateway
    {
        return match ($provider) {
            PaymentProvider::Stripe => app(StripeGateway::class),
            PaymentProvider::SumUp => app(SumUpGateway::class),
            default => throw new InvalidArgumentException("{$provider->value} is geen online betaalmethode."),
        };
    }

    /**
     * The online providers the board switched on and configured, offered at checkout.
     *
     * @return list<PaymentProvider>
     */
    public function available(): array
    {
        return array_values(array_filter(
            [PaymentProvider::Stripe, PaymentProvider::SumUp],
            fn (PaymentProvider $provider): bool => $this->gateway($provider)->isConfigured(),
        ));
    }
}
