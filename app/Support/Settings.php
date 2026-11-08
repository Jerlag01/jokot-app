<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Site-wide settings stored as key/value rows, with defaults taken from the original jokot.be.
 */
class Settings
{
    private const CACHE_KEY = 'jokot.settings';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'site_name' => 'JH Jokot',
            'legal_name' => 'JOC Niel vzw',
            'tagline' => 'Jeugdhuis in Niel',
            'address' => 'Landbouwstraat 145/3, 2845 Niel',
            'email' => 'info@jokot.be',
            'bands_email' => 'bands@jokot.be',
            'company_number' => '1013.578.536',
            'facebook_url' => 'https://www.facebook.com/jhjokot',
            'instagram_url' => 'https://www.instagram.com/jh_jokot/',
            'opening_hours_note' => 'In de vakanties zijn wij op donderdag en zondag van 20u tot 23u ook geopend.',
            'notification_email' => 'info@jokot.be',
            'rental_min_days_ahead' => 30,
            'rental_max_months_ahead' => 6,
            'rentals_open' => true,
            // ISO weekdays (1 = monday ... 7 = sunday) on which the website accepts no rental requests at all.
            'rental_blocked_weekdays' => [],
            // Confirming mails the contract signing link at once; off = send it by hand via "Contract".
            'rental_contract_auto_send' => false,
            'cup_contract_auto_send' => true,
            'logo' => null,
            // Invoicing
            'iban' => 'BE65 7380 4975 8796',
            'bic' => 'KREDBEBB',
            'vat_number' => null,
            'vat_note' => null,
            'default_vat_rate' => 0,
            'invoice_payment_days' => 30,
            'invoice_footer' => null,
            // Rentals and contracts
            'corkage_fee' => 3,
            'contract_representative' => null,
            'contract_contact' => "Met vragen of opmerkingen kan je altijd terecht bij volgende personen of via mail: jhniel@outlook.com of via onze Facebookpagina \"JH Jokot\".\nLagaet Jeroen: +32 (0)471 49 14 34\nThomas Hellemans: +32 (0)472 63 04 03",
            // Cup loans (wisselbekers)
            'cup_loans_open' => true,
            'cups_total' => null,
            'crates_total' => null,
            'cups_per_crate' => null,
            'cup_rental_fee' => 15,
            'cup_fee' => 0.25,
            'cup_crate_fee' => 25,
            // Factorberekening: selling price = cost per serving incl. VAT × target factor, rounded up.
            'pricing' => [
                'target_factor' => 2.0,
                'rounding' => 0.1,
                'target_soft' => null,
                'target_beer' => null,
                'target_wine' => null,
                'target_snack' => null,
                'target_other' => null,
            ],
            // Members
            'member_fee' => 5,
            // Ticket sales. Secrets are stored encrypted.
            'tickets_open' => true,
            'payments' => [
                'stripe_enabled' => false,
                'stripe_secret' => null,
                'stripe_webhook_secret' => null,
                'sumup_enabled' => false,
                'sumup_api_key' => null,
                'sumup_merchant_code' => null,
                'order_minutes' => 30,
            ],
            // Billit (invoicing platform / Peppol access point). Secrets are stored encrypted.
            'billit' => [
                'enabled' => false,
                'environment' => 'sandbox',
                'api_key' => null,
                'party_id' => null,
                'transport' => 'auto',
                'push_payments' => true,
                'import_incoming' => false,
                'webhook_id' => null,
                'webhook_secret' => null,
            ],
            // Search engines and social media. Indexing is only ever allowed on the production site.
            'seo' => [
                'indexable' => true,
                'description' => 'JH Jokot is het jeugdhuis van Niel: fuiven, optredens en activiteiten voor jongeren, en een zaal te huur voor feesten en evenementen tot 350 personen.',
                'share_image' => null,
                'google_verification' => null,
                'bing_verification' => null,
            ],
            // Login logging and automatic IP blocking.
            'security' => [
                'auto_block' => true,
                'max_attempts' => 5,
                'window_minutes' => 15,
                'block_minutes' => 60,
                'whitelist' => null,
                'retention_days' => 365,
            ],
            // Visitor statistics (no IP addresses or cookies).
            'stats' => [
                'enabled' => true,
                'exclude_logged_in' => true,
                'retention_days' => 730,
            ],
            // Empty mailer = the .env configuration stays in effect. The password is stored encrypted.
            'mail' => [
                // Master switch: off = the website sends no mail at all (only the test mail).
                'enabled' => true,
                'auto_rental_requester' => true,
                'auto_rental_board' => true,
                'auto_signed_contract' => true,
                'auto_tickets' => true,
                'mailer' => null,
                'host' => null,
                'port' => 587,
                'scheme' => null,
                'username' => null,
                'password' => null,
                'from_address' => null,
                'from_name' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values === null) {
            try {
                $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
            } catch (Throwable) {
                $stored = [];
            }

            $defaults = self::defaults();
            $this->values = array_replace($defaults, $stored);

            foreach ($defaults as $key => $default) {
                if (is_array($default)) {
                    $this->values[$key] = array_replace($default, (array) ($stored[$key] ?? []));
                }
            }
        }

        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    /**
     * Keys of settings stored encrypted (never sent back to a form).
     *
     * @return list<string>
     */
    public static function secretKeys(): array
    {
        return ['mail.password', 'payments.stripe_secret', 'payments.stripe_webhook_secret', 'payments.sumup_api_key', 'billit.api_key', 'billit.webhook_secret'];
    }

    public function secret(string $key): ?string
    {
        $value = $this->get($key);

        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Overrides the mail configuration with the values set in the admin site.
     * When no mailer is chosen there, the .env configuration stays in effect.
     */
    public function applyMailConfig(): void
    {
        $mail = (array) $this->get('mail');

        if (blank($mail['mailer'] ?? null)) {
            return;
        }

        $password = $this->secret('mail.password');

        config(array_filter([
            'mail.default' => $mail['mailer'],
            'mail.mailers.smtp.host' => $mail['host'] ?? null,
            'mail.mailers.smtp.port' => $mail['port'] ?? null,
            'mail.mailers.smtp.username' => $mail['username'] ?? null,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.scheme' => $mail['scheme'] ?? null,
            'mail.from.address' => $mail['from_address'] ?: $this->get('email'),
            'mail.from.name' => $mail['from_name'] ?: $this->siteName(),
        ], fn (mixed $value): bool => filled($value)));
    }

    public function siteName(): string
    {
        return (string) $this->get('site_name');
    }

    public function logoUrl(): string
    {
        $logo = $this->get('logo');

        return filled($logo) ? Storage::disk('public')->url($logo) : URL::asset('images/jokot-logo-wit.png');
    }
}
