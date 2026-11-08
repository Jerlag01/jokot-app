<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Whether the website sends mail at all, and which automatic mails are switched on (Instellingen → E-mail).
 * With mail switched off nothing leaves the server: every message is dropped and noted in the log.
 */
class Mailing
{
    /** Header that lets the "Testmail versturen" button through while mail is switched off. */
    public const TEST_HEADER = 'X-Jokot-Test-Mail';

    /**
     * Automatic mails that can be switched off one by one.
     *
     * @return array<string, string>
     */
    public static function automaticTypes(): array
    {
        return [
            'rental_requester' => 'Bevestiging van ontvangst aan wie een huuraanvraag indient',
            'rental_board' => 'Nieuwe huuraanvraag naar het meldingsadres',
            'signed_contract' => 'Kopie van het ondertekende contract (huurder en meldingsadres)',
            'tickets' => 'Tickets na een online aankoop',
        ];
    }

    public static function enabled(): bool
    {
        return (bool) settings()->get('mail.enabled', true);
    }

    public static function allows(string $type): bool
    {
        return self::enabled() && (bool) settings()->get('mail.auto_'.$type, true);
    }

    /**
     * For mails sent by hand (invoices, contract links): fail loudly instead of pretending they were sent.
     */
    public static function ensureEnabled(): void
    {
        if (! self::enabled()) {
            throw new RuntimeException('Het versturen van e-mails staat uit (Systeem → Instellingen → E-mail).');
        }
    }

    /**
     * MessageSending listener: returning false stops the mailer from sending.
     */
    public static function filter(MessageSending $event): ?bool
    {
        if (self::enabled() || $event->message->getHeaders()->has(self::TEST_HEADER)) {
            return null;
        }

        Log::info('Mail niet verstuurd (e-mail staat uit)', [
            'to' => array_map(fn ($address): string => $address->getAddress(), $event->message->getTo()),
            'subject' => $event->message->getSubject(),
        ]);

        return false;
    }
}
