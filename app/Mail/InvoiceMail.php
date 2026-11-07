<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\Invoicing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $kind = $this->invoice->isCreditNote() ? 'Creditnota' : 'Factuur';

        return new Envelope(
            subject: ($this->reminder ? 'Herinnering: ' : '').$kind.' '.$this->invoice->number.' van '.settings()->siteName(),
            replyTo: [settings()->get('email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $invoicing = app(Invoicing::class);

        return [Attachment::fromData(fn (): string => $invoicing->pdf($this->invoice)->output(), $invoicing->filename($this->invoice))->withMime('application/pdf')];
    }
}
