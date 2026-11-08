<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\Invoicing;
use App\Support\MailTemplates;
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

    public function templateKey(): string
    {
        return $this->reminder ? 'invoice_reminder' : 'invoice';
    }

    /**
     * @return array<string, string>
     */
    public function templateValues(): array
    {
        $invoice = $this->invoice;

        return [
            'naam' => (string) ($invoice->customer->contact_name ?: $invoice->customer->name),
            'document' => $invoice->isCreditNote() ? 'Creditnota' : 'Factuur',
            'soort' => $invoice->isCreditNote() ? 'creditnota' : 'factuur',
            'nummer' => (string) $invoice->number,
            'onderwerp' => $invoice->subject ? '('.$invoice->subject.')' : '',
            'datum' => $invoice->issue_date?->format('d/m/Y') ?? '',
            'vervaldatum' => $invoice->due_date?->format('d/m/Y') ?? '',
            'bedrag' => euro($invoice->balance()),
            'iban' => (string) settings()->get('iban'),
            'mededeling' => (string) $invoice->structured_reference,
            'organisatie' => (string) settings()->get('legal_name'),
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
        $invoice = $this->invoice;
        $payment = $invoice->isCreditNote() ? [] : [MailTemplates::table([
            'Bedrag' => euro($invoice->balance()),
            'Te betalen voor' => $invoice->due_date?->format('d/m/Y'),
            'Rekeningnummer' => settings()->get('iban'),
            'Mededeling' => $invoice->structured_reference,
        ])];

        return new Content(markdown: 'mail.template', with: ['parts' => MailTemplates::parts($this->templateKey(), $this->templateValues(), [
            'betaalgegevens' => $payment,
        ])]);
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
