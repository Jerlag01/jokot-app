<?php

namespace App\Support\Payments;

use Carbon\CarbonInterface;

/**
 * A single-amount hosted checkout (e.g. topping up a member card), independent of ticket orders.
 */
final readonly class CheckoutRequest
{
    /**
     * @param  array<string, string|int>  $metadata
     */
    public function __construct(
        public float $amount,
        public string $description,
        public string $reference,
        public string $returnUrl,
        public string $cancelUrl,
        public ?string $email = null,
        public array $metadata = [],
        public ?CarbonInterface $expiresAt = null,
    ) {}
}
