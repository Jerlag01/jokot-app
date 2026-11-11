<?php

namespace App\Mail;

use App\Models\MemberCardTransaction;
use App\Support\MailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation that money was added to a member card.
 */
class CardTopUpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MemberCardTransaction $topUp) {}

    public function templateKey(): string
    {
        return 'card_topup';
    }

    /**
     * @return array<string, string>
     */
    public function templateValues(): array
    {
        $member = $this->topUp->member;

        return [
            'naam' => $member->fullName(),
            'voornaam' => (string) $member->first_name,
            'kaartnummer' => (string) $member->card_number,
            'bedrag' => euro($this->topUp->amount),
            'saldo' => euro($member->cardBalance()),
            'datum' => ($this->topUp->completed_at ?? now())->format('d/m/Y H:i'),
            ...MailTemplates::commonValues(),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: MailTemplates::subject($this->templateKey(), $this->templateValues()),
            replyTo: [settings()->get('email')],
        );
    }

    public function content(): Content
    {
        $values = $this->templateValues();

        return new Content(markdown: 'mail.template', with: ['parts' => MailTemplates::parts($this->templateKey(), $values, [
            'overzicht' => [MailTemplates::table([
                'Kaart' => $values['kaartnummer'],
                'Opgeladen' => $values['bedrag'],
                'Nieuw saldo' => $values['saldo'],
                'Datum' => $values['datum'],
            ])],
            'knop' => $this->topUp->member->token
                ? [MailTemplates::button($this->topUp->member->selfServiceUrl(), 'Saldo en overzicht bekijken')]
                : [],
        ])]);
    }
}
