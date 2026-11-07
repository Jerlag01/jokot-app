<?php

namespace App\Mail;

use App\Models\Order;
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

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Je tickets voor '.$this->order->event->title.' ('.$this->order->number.')',
            replyTo: [settings()->get('email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-tickets');
    }

    public function attachments(): array
    {
        $tickets = $this->order->tickets()->valid()->get();

        return [Attachment::fromData(fn (): string => app(TicketSales::class)->pdf($tickets)->output(), 'tickets-'.$this->order->number.'.pdf')->withMime('application/pdf')];
    }
}
