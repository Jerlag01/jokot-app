<?php

namespace App\Mail;

use App\Models\Contract;
use App\Support\Contracts;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The link to read and sign a contract online, or (signed) a copy of the signed contract.
 */
class ContractMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Contract $contract, public bool $signedCopy = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->signedCopy ? 'Ondertekend: ' : 'Te ondertekenen: ').$this->contract->title(),
            replyTo: [settings()->get('email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contract');
    }

    public function attachments(): array
    {
        if (! $this->signedCopy) {
            return [];
        }

        $contracts = app(Contracts::class);

        return [Attachment::fromData(fn (): string => $contracts->pdfContents($this->contract), $contracts->filename($this->contract))->withMime('application/pdf')];
    }
}
