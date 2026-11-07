<?php

namespace App\Support;

/**
 * Every permission a role can grant, grouped as shown in the role form. Admin roles have them all.
 */
class Permissions
{
    public const PAGES = 'pages';

    public const EVENTS = 'events';

    public const TICKET_ORDERS = 'ticket_orders';

    public const CHECKIN = 'checkin';

    public const RENTALS = 'rentals';

    public const RENTAL_SETTINGS = 'rental_settings';

    public const CUP_LOANS = 'cup_loans';

    public const PRODUCTS = 'products';

    public const STOCK = 'stock';

    public const PRICING = 'pricing';

    public const INVOICES = 'invoices';

    public const PURCHASES = 'purchases';

    public const MEMBERS = 'members';

    public const INSURANCES = 'insurances';

    public const ACCOUNTS = 'accounts';

    public const STATISTICS = 'statistics';

    public const SECURITY = 'security';

    public const USERS = 'users';

    public const SETTINGS = 'settings';

    /**
     * @return array<string, array<string, string>> group label => [permission => label]
     */
    public static function grouped(): array
    {
        return [
            'Website' => [
                self::PAGES => 'Pagina\'s en openingsuren beheren',
                self::STATISTICS => 'Bezoekersstatistieken bekijken',
            ],
            'Evenementen & tickets' => [
                self::EVENTS => 'Activiteiten en tickettypes beheren',
                self::TICKET_ORDERS => 'Bestellingen bekijken, kassaverkoop, annuleren en terugbetalen',
                self::CHECKIN => 'Tickets inchecken (ook via de app)',
            ],
            'Verhuur' => [
                self::RENTALS => 'Huuraanvragen, kalender, contracten en drankentelling',
                self::RENTAL_SETTINGS => 'Tarieven en materialen',
                self::CUP_LOANS => 'Wisselbekers uitlenen',
            ],
            'Stock' => [
                self::PRODUCTS => 'Producten (drank en snacks) beheren',
                self::STOCK => 'Stockbewegingen en tellingen',
                self::PRICING => 'Factorberekening en verkoopprijzen',
            ],
            'Facturatie' => [
                self::INVOICES => 'Klanten, facturen, creditnota\'s en betalingen',
                self::PURCHASES => 'Inkomende facturen, leveranciers en betalingen',
            ],
            'Leden' => [
                self::MEMBERS => 'Ledenlijsten en lidkaarten',
            ],
            'Administratie' => [
                self::INSURANCES => 'Verzekeringen en polissen',
                self::ACCOUNTS => 'Jaarrekening, budget, toogkassa en manuele boekingen',
            ],
            'Systeem' => [
                self::USERS => 'Gebruikers en rollen beheren',
                self::SETTINGS => 'Instellingen wijzigen',
                self::SECURITY => 'Beveiliging: aanmeldpogingen en IP-blokkering',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::grouped())));
    }

    /**
     * The roles created on install, as asked by the board: admin, editor, financials, events, checkins.
     *
     * @return array<string, array{description: string, is_admin: bool, permissions: list<string>}>
     */
    public static function defaultRoles(): array
    {
        return [
            'Admin' => ['description' => 'Alles, ook gebruikers en instellingen.', 'is_admin' => true, 'permissions' => []],
            'Editor' => ['description' => 'Website en activiteiten.', 'is_admin' => false, 'permissions' => [self::PAGES, self::EVENTS, self::MEMBERS, self::STATISTICS]],
            'Financieel' => ['description' => 'Facturatie, verhuur, wisselbekers, stock en ticketverkoop.', 'is_admin' => false, 'permissions' => [self::INVOICES, self::PURCHASES, self::MEMBERS, self::INSURANCES, self::ACCOUNTS, self::RENTALS, self::RENTAL_SETTINGS, self::CUP_LOANS, self::PRODUCTS, self::STOCK, self::PRICING, self::TICKET_ORDERS]],
            'Evenementen' => ['description' => 'Activiteiten, tickets en check-in.', 'is_admin' => false, 'permissions' => [self::EVENTS, self::TICKET_ORDERS, self::CHECKIN]],
            'Check-in' => ['description' => 'Enkel tickets scannen aan de deur.', 'is_admin' => false, 'permissions' => [self::CHECKIN]],
        ];
    }
}
