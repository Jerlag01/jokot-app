<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\MailTemplates;
use App\Support\Ticketing\TicketSales;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderTicketsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function templateKey(): string
    {
        return 'order_tickets';
    }

    /**
     * @return array<string, string>
     */
    public function templateValues(): array
    {
        $event = $this->order->event;

        return [
            'naam' => (string) $this->order->name,
            'evenement' => (string) $event->title,
            'wanneer' => ucfirst($event->starts_at->translatedFormat('l j F Y')).' om '.$event->starts_at->format('H:i'),
            'aantal' => (string) $this->order->tickets()->valid()->count(),
            'bestelling' => (string) $this->order->number,
            'totaal' => euro($this->order->total),
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
                'Evenement' => $values['evenement'],
                'Wanneer' => $values['wanneer'],
                'Waar' => settings()->get('address'),
                'Bestelling' => $values['bestelling'],
                'Totaal' => $values['totaal'],
            ])],
            'knop' => [MailTemplates::button($this->order->publicUrl(), 'Bestelling en tickets bekijken')],
        ])]);
    }

    public function attachments(): array
    {
        $tickets = $this->order->tickets()->valid()->get();

        return [Attachment::fromData(fn (): string => app(TicketSales::class)->pdf($tickets)->output(), 'tickets-'.$this->order->number.'.pdf')->withMime('application/pdf')];
    }
}
