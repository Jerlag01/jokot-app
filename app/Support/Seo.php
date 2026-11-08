<?php

namespace App\Support;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything search engines and social networks read: indexing rules, descriptions, share image and
 * schema.org structured data (the organisation, the website and each event).
 */
class Seo
{
    /**
     * Only the production site may be indexed (APP_ENV=production and not switched off in the settings),
     * so the development copy never ends up in Google.
     */
    public static function indexable(): bool
    {
        return app()->isProduction() && (bool) settings()->get('seo.indexable', true);
    }

    public static function description(?string $description): string
    {
        $text = trim((string) ($description ?: settings()->get('seo.description') ?: settings()->get('tagline')));

        return Str::limit(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '', 160, '…');
    }

    public static function shareImage(?string $image = null): string
    {
        if ($image) {
            return $image;
        }

        $setting = settings()->get('seo.share_image');

        return filled($setting) ? Storage::disk('public')->url($setting) : asset('images/zaal-rook.jpg');
    }

    /**
     * "Landbouwstraat 145/3, 2845 Niel" as a schema.org PostalAddress.
     *
     * @return array<string, string>
     */
    public static function postalAddress(): array
    {
        $address = (string) settings()->get('address');
        $street = trim(Str::before($address, ','));
        $rest = trim(Str::after($address, ','));
        preg_match('/^(\d{4})\s+(.+)$/', $rest, $match);

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'postalCode' => $match[1] ?? null,
            'addressLocality' => $match[2] ?? $rest,
            'addressCountry' => 'BE',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return array_filter([
            '@type' => ['Organization', 'LocalBusiness'],
            '@id' => url('/').'#organisatie',
            'name' => settings()->siteName(),
            'legalName' => settings()->get('legal_name'),
            'description' => self::description(null),
            'url' => url('/'),
            'logo' => asset('images/jokot-logo-zwart.png'),
            'image' => self::shareImage(),
            'email' => settings()->get('email'),
            'address' => self::postalAddress(),
            'vatID' => settings()->get('vat_number') ?: null,
            'sameAs' => array_values(array_filter([settings()->get('facebook_url'), settings()->get('instagram_url')])),
        ]);
    }

    /**
     * The organisation and the website, on every page.
     */
    public static function siteGraph(): string
    {
        return self::json([
            '@context' => 'https://schema.org',
            '@graph' => [
                self::organization(),
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'name' => settings()->siteName(),
                    'url' => url('/'),
                    'inLanguage' => 'nl-BE',
                    'publisher' => ['@id' => url('/').'#organisatie'],
                ],
            ],
        ]);
    }

    /**
     * An event with date, place and tickets, so it can appear in Google's event listings.
     */
    public static function event(Event $event): string
    {
        $place = [
            '@type' => 'Place',
            'name' => settings()->siteName(),
            'address' => self::postalAddress(),
        ];

        $offers = $event->tickets_enabled
            ? $event->ticketTypes->where('is_active', true)->map(fn (TicketType $type): array => array_filter([
                '@type' => 'Offer',
                'name' => $type->name,
                'price' => number_format((float) $type->price, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'url' => route('events.show', $event),
                'availability' => $type->isOnSale() ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                'validFrom' => $type->sales_start?->toIso8601String(),
            ]))->values()->all()
            : ($event->ticket_url ? [['@type' => 'Offer', 'url' => $event->ticket_url]] : []);

        return self::json(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'description' => self::description($event->summary ?: strip_tags((string) $event->description)),
            'startDate' => $event->starts_at->toIso8601String(),
            'endDate' => $event->ends_at?->toIso8601String(),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $place,
            'image' => [self::shareImage($event->imageUrl())],
            'organizer' => ['@type' => 'Organization', 'name' => settings()->siteName(), 'url' => url('/')],
            'offers' => $offers ?: null,
            'url' => route('events.show', $event),
        ]));
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
