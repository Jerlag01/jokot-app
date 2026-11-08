<?php

namespace App\Support;

use App\Mail\ContractMail;
use App\Mail\InvoiceMail;
use App\Mail\OrderTicketsMail;
use App\Mail\RentalRequestReceived;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MailTemplate;
use App\Models\Order;
use App\Models\RentalRequest;
use Illuminate\Mail\Mailable;
use InvalidArgumentException;
use Throwable;

/**
 * The website's mails as editable templates (Systeem → E-mailsjablonen).
 *
 * A template is Markdown with {placeholders}. Text placeholders are filled in (escaped) anywhere; block
 * placeholders such as {knop} or {overzicht} stand on a line of their own and become a button or a table.
 * Admin-written text is never compiled as Blade.
 */
class MailTemplates
{
    /**
     * Unsaved texts shown by the preview in the admin site.
     *
     * @var array<string, array{subject: string, body: string}>
     */
    public static array $overrides = [];

    /**
     * @return array<string, array{label: string, description: string, subject: string, body: string, placeholders: array<string, string>, blocks: array<string, string>}>
     */
    public static function definitions(): array
    {
        $signature = "Met vriendelijke groeten,<br>\n{site}";

        return [
            'rental_requester' => [
                'label' => 'Huuraanvraag: bevestiging aan de aanvrager',
                'description' => 'Meteen na een huuraanvraag via de website.',
                'subject' => 'Je huuraanvraag bij {site} ({referentie})',
                'body' => "# Bedankt voor je aanvraag\n\nHallo {voornaam},\n\nWe hebben je aanvraag om het jeugdhuis te huren op **{datum}** goed ontvangen. Het bestuur bekijkt ze zo snel mogelijk en neemt contact met je op. Let op: je aanvraag is pas definitief na onze bevestiging.\n\n{overzicht}\n\n{site}<br>\n{adres_jeugdhuis}",
                'placeholders' => self::rentalPlaceholders(),
                'blocks' => ['overzicht' => 'tabel met de aanvraag, drank en details'],
            ],
            'rental_board' => [
                'label' => 'Huuraanvraag: melding aan het bestuur',
                'description' => 'Naar het meldingsadres (Instellingen → Verhuur) bij een nieuwe huuraanvraag.',
                'subject' => 'Nieuwe huuraanvraag {referentie} voor {datum_kort}',
                'body' => "# Nieuwe huuraanvraag\n\n**{naam}** {organisatie} vraagt het jeugdhuis aan voor **{datum}**.\n\n{overzicht}\n\n{knop}\n\n{site}",
                'placeholders' => self::rentalPlaceholders(),
                'blocks' => ['overzicht' => 'tabel met de aanvraag, drank en details', 'knop' => 'knop "Aanvraag openen" in het beheer'],
            ],
            'contract_sign' => [
                'label' => 'Contract: ondertekenlink',
                'description' => 'Bij "Contract versturen om te ondertekenen" (verhuur en wisselbekers).',
                'subject' => 'Te ondertekenen: {contract}',
                'body' => "Beste {naam},\n\nHierbij het contract \"{contract}\" ({referentie}). Je kan het online lezen en ondertekenen via de knop hieronder.\n\n{knop}\n\nVragen? Antwoord gewoon op deze mail.\n\n".$signature,
                'placeholders' => self::contractPlaceholders(),
                'blocks' => ['knop' => 'knop "Contract lezen en ondertekenen"'],
            ],
            'contract_signed' => [
                'label' => 'Contract: kopie na ondertekenen',
                'description' => 'Naar de ondertekenaar en het meldingsadres, met het ondertekende contract (PDF) in bijlage.',
                'subject' => 'Ondertekend: {contract}',
                'body' => "Beste {naam},\n\nBedankt! In bijlage vind je het ondertekende contract \"{contract}\" ({referentie}), ondertekend op {ondertekend_op}.\n\nVragen? Antwoord gewoon op deze mail.\n\n".$signature,
                'placeholders' => self::contractPlaceholders(),
                'blocks' => [],
            ],
            'invoice' => [
                'label' => 'Factuur',
                'description' => 'Bij "Mailen" op een factuur of creditnota, met de PDF in bijlage.',
                'subject' => '{document} {nummer} van {site}',
                'body' => "Beste {naam},\n\nIn bijlage vind je {soort} **{nummer}** {onderwerp}.\n\n{betaalgegevens}\n\n".$signature.' - {organisatie}',
                'placeholders' => self::invoicePlaceholders(),
                'blocks' => ['betaalgegevens' => 'tabel met bedrag, vervaldag, rekeningnummer en mededeling (niet bij een creditnota)'],
            ],
            'invoice_reminder' => [
                'label' => 'Factuur: herinnering',
                'description' => 'Bij "Herinnering sturen" op een onbetaalde factuur.',
                'subject' => 'Herinnering: {document} {nummer} van {site}',
                'body' => "# Herinnering\n\nBeste {naam},\n\nVolgens onze gegevens is {soort} **{nummer}** van {datum} nog niet (volledig) betaald. Het openstaande bedrag is **{bedrag}**, te betalen sinds {vervaldatum}. Heb je intussen betaald? Dan mag je deze herinnering negeren.\n\n{betaalgegevens}\n\n".$signature.' - {organisatie}',
                'placeholders' => self::invoicePlaceholders(),
                'blocks' => ['betaalgegevens' => 'tabel met bedrag, vervaldag, rekeningnummer en mededeling'],
            ],
            'order_tickets' => [
                'label' => 'Tickets na een aankoop',
                'description' => 'Na een betaalde online bestelling of kassaverkoop met e-mailadres, met de tickets (PDF) in bijlage.',
                'subject' => 'Je tickets voor {evenement} ({bestelling})',
                'body' => "# Je tickets voor {evenement}\n\nHallo {naam},\n\nBedankt voor je bestelling! In bijlage vind je je tickets ({aantal}). Toon de QR-code aan de ingang, op je gsm of afgedrukt. Elke code geeft één keer toegang.\n\n{overzicht}\n\n{knop}\n\nTot dan!<br>\n{site}",
                'placeholders' => [
                    'naam' => 'naam van de koper', 'evenement' => 'titel van het evenement', 'wanneer' => 'datum en uur',
                    'aantal' => 'aantal tickets', 'bestelling' => 'bestelnummer', 'totaal' => 'totaalbedrag',
                    ...self::commonPlaceholders(),
                ],
                'blocks' => ['overzicht' => 'tabel met evenement, datum, plaats, bestelling en totaal', 'knop' => 'knop "Bestelling en tickets bekijken"'],
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, subject: string, body: string, placeholders: array<string, string>, blocks: array<string, string>}
     */
    public static function definition(string $key): array
    {
        return self::definitions()[$key] ?? throw new InvalidArgumentException("Onbekend e-mailsjabloon [{$key}].");
    }

    /**
     * Creates the rows of templates that do not exist yet (new ones keep their default text).
     */
    public static function sync(): void
    {
        foreach (self::definitions() as $key => $definition) {
            MailTemplate::query()->firstOrCreate(['key' => $key], ['subject' => $definition['subject'], 'body' => $definition['body']]);
        }
    }

    /**
     * A mail of this template filled in with the most recent matching record, for the preview.
     */
    public static function sample(string $key): ?Mailable
    {
        self::definition($key);

        return match ($key) {
            'rental_requester', 'rental_board' => ($rental = RentalRequest::query()->latest('id')->first())
                ? new RentalRequestReceived($rental, forBoard: $key === 'rental_board') : null,
            'contract_sign', 'contract_signed' => ($contract = Contract::query()->with('contractable')->latest('id')->get()->first(fn (Contract $contract): bool => $contract->contractable !== null))
                ? new ContractMail($contract, signedCopy: $key === 'contract_signed') : null,
            'invoice', 'invoice_reminder' => ($invoice = Invoice::query()->whereNotNull('number')->latest('id')->first() ?? Invoice::query()->latest('id')->first())
                ? new InvoiceMail($invoice, reminder: $key === 'invoice_reminder') : null,
            'order_tickets' => ($order = Order::query()->whereHas('event')->latest('id')->first())
                ? new OrderTicketsMail($order) : null,
        };
    }

    /**
     * Subject and body as edited, falling back to the default.
     *
     * @return array{subject: string, body: string}
     */
    public static function current(string $key): array
    {
        $definition = self::definition($key);

        if (isset(self::$overrides[$key])) {
            return self::$overrides[$key];
        }

        try {
            $template = MailTemplate::query()->find($key);
        } catch (Throwable) {
            $template = null;
        }

        return [
            'subject' => filled($template?->subject) ? $template->subject : $definition['subject'],
            'body' => filled($template?->body) ? $template->body : $definition['body'],
        ];
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    public static function subject(string $key, array $values): string
    {
        $subject = self::fill(self::current($key)['subject'], $values, escape: false);

        return trim((string) preg_replace('/\s+/', ' ', $subject));
    }

    /**
     * The body split into parts for the mail.template view: Markdown text, tables and buttons.
     *
     * @param  array<string, scalar|null>  $values
     * @param  array<string, list<array<string, mixed>>>  $blocks  block placeholder => parts
     * @return list<array<string, mixed>>
     */
    public static function parts(string $key, array $values, array $blocks = []): array
    {
        $body = str_replace("\r\n", "\n", self::current($key)['body']);
        $names = array_keys(self::definition($key)['blocks']);

        if ($names === []) {
            return [['type' => 'markdown', 'markdown' => self::fill($body, $values)]];
        }

        $pattern = '/^[ \t]*\{('.implode('|', array_map('preg_quote', $names)).')\}[ \t]*$/m';
        $pieces = preg_split($pattern, $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = [];

        foreach ($pieces as $index => $piece) {
            if ($index % 2 === 1) {
                array_push($parts, ...($blocks[$piece] ?? []));
            } elseif (trim($piece) !== '') {
                $parts[] = ['type' => 'markdown', 'markdown' => self::fill(trim($piece, "\n"), $values)];
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    private static function fill(string $text, array $values, bool $escape = true): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/i', function (array $match) use ($values, $escape): string {
            if (! array_key_exists($match[1], $values)) {
                return $match[0];
            }

            $value = (string) $values[$match[1]];

            return $escape ? e($value) : $value;
        }, $text);
    }

    /**
     * @return array<string, string>
     */
    private static function commonPlaceholders(): array
    {
        return ['site' => 'naam van het jeugdhuis', 'organisatie_jeugdhuis' => 'JOC Niel vzw', 'adres_jeugdhuis' => 'adres van het jeugdhuis', 'email_jeugdhuis' => 'algemeen e-mailadres'];
    }

    /**
     * @return array<string, string>
     */
    private static function rentalPlaceholders(): array
    {
        return [
            'naam' => 'volledige naam aanvrager', 'voornaam' => 'voornaam aanvrager', 'organisatie' => '(organisatie) of leeg',
            'referentie' => 'referentie (HA-2026-001)', 'datum' => 'datum voluit (zaterdag 5 juli 2026)', 'datum_kort' => 'datum (5 juli 2026)',
            'email' => 'e-mail aanvrager', 'telefoon' => 'telefoon aanvrager',
            ...self::commonPlaceholders(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function contractPlaceholders(): array
    {
        return [
            'naam' => 'naam van de ondertekenaar', 'contract' => 'titel van het contract', 'referentie' => 'referentie (HA-2026-001 of WB-2026-001)',
            'ondertekend_op' => 'datum en uur van ondertekening',
            ...self::commonPlaceholders(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function invoicePlaceholders(): array
    {
        return [
            'naam' => 'contactpersoon of naam klant', 'document' => 'Factuur / Creditnota', 'soort' => 'factuur / creditnota',
            'nummer' => 'factuurnummer', 'onderwerp' => '(onderwerp) of leeg', 'datum' => 'factuurdatum', 'vervaldatum' => 'vervaldag',
            'bedrag' => 'openstaand bedrag', 'iban' => 'rekeningnummer', 'mededeling' => 'gestructureerde mededeling',
            'organisatie' => 'JOC Niel vzw',
            ...self::commonPlaceholders(),
        ];
    }

    /**
     * Values every template can use.
     *
     * @return array<string, string>
     */
    public static function commonValues(): array
    {
        $settings = settings();

        return [
            'site' => $settings->siteName(),
            'organisatie_jeugdhuis' => (string) $settings->get('legal_name'),
            'adres_jeugdhuis' => (string) $settings->get('address'),
            'email_jeugdhuis' => (string) $settings->get('email'),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $rows  label => value
     * @return array<string, mixed>
     */
    public static function table(array $rows): array
    {
        return ['type' => 'table', 'rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    public static function button(string $url, string $label): array
    {
        return ['type' => 'button', 'url' => $url, 'label' => $label];
    }

    /**
     * Markdown generated by the code itself (values must already be escaped).
     *
     * @return array<string, mixed>
     */
    public static function markdown(string $markdown): array
    {
        return ['type' => 'markdown', 'markdown' => $markdown];
    }
}
