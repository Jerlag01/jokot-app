<?php

namespace App\Mail;

use App\Models\RentalRequest;
use App\Support\MailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the board ($forBoard) and as a confirmation of receipt to the requester.
 */
class RentalRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RentalRequest $rentalRequest, public bool $forBoard = false) {}

    public function templateKey(): string
    {
        return $this->forBoard ? 'rental_board' : 'rental_requester';
    }

    /**
     * @return array<string, string>
     */
    public function templateValues(): array
    {
        $r = $this->rentalRequest;

        return [
            'naam' => $r->fullName(),
            'voornaam' => (string) $r->first_name,
            'organisatie' => $r->organisation ? '('.$r->organisation.')' : '',
            'referentie' => (string) $r->reference,
            'datum' => $r->event_date->translatedFormat('l j F Y'),
            'datum_kort' => $r->event_date->translatedFormat('j F Y'),
            'email' => (string) $r->email,
            'telefoon' => (string) $r->phone,
            ...MailTemplates::commonValues(),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: MailTemplates::subject($this->templateKey(), $this->templateValues()),
            replyTo: $this->forBoard ? [$this->rentalRequest->email] : [settings()->get('email')],
        );
    }

    public function content(): Content
    {
        $r = $this->rentalRequest->loadMissing('drinks', 'rentalRate', 'rentalExtra');

        $overview = [MailTemplates::table(array_filter([
            'Referentie' => $r->reference,
            'Datum' => $r->event_date->format('d/m/Y'),
            'Gebruikersgroep' => $r->rentalRate?->name ?? '-',
            'DJ-tafel' => $r->rentalExtra?->name ?? 'Nee',
            'Verwacht aantal' => $r->expected_guests ?? '-',
            'Eigen wijn/cava/champagne' => $r->brings_own_wine ? 'Ja' : 'Nee',
            'Richtprijs huur' => $r->quoted_price !== null ? euro($r->quoted_price).' (waarborg '.euro($r->quoted_deposit).')' : null,
            'E-mail' => $r->email,
            'Telefoon' => $r->phone,
            'Adres' => $r->address(),
        ], fn ($value): bool => $value !== null))];

        if ($r->drinks->isNotEmpty()) {
            $overview[] = MailTemplates::markdown("**Drank**\n\n".$r->drinks->map(fn ($line): string => '- '.$line->quantity.' × '.e($line->name))->implode("\n"));
        }

        if ($r->details) {
            $overview[] = MailTemplates::markdown("**Details**\n\n".nl2br(e($r->details)));
        }

        return new Content(markdown: 'mail.template', with: ['parts' => MailTemplates::parts($this->templateKey(), $this->templateValues(), [
            'overzicht' => $overview,
            'knop' => [MailTemplates::button(url('/admin/rental-requests/'.$r->id), 'Aanvraag openen')],
        ])]);
    }
}
