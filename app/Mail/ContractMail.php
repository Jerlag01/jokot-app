<?php

namespace App\Mail;

use App\Models\Contract;
use App\Support\Contracts;
use App\Support\MailTemplates;
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

    public function templateKey(): string
    {
        return $this->signedCopy ? 'contract_signed' : 'contract_sign';
    }

    /**
     * @return array<string, string>
     */
    public function templateValues(): array
    {
        return [
            'naam' => $this->contract->contractable->contractSignerName(),
            'contract' => $this->contract->title(),
            'referentie' => (string) $this->contract->contractable->reference,
            'ondertekend_op' => $this->contract->signed_at?->format('d/m/Y \o\m H:i') ?? '',
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
        return new Content(markdown: 'mail.template', with: ['parts' => MailTemplates::parts($this->templateKey(), $this->templateValues(), [
            'knop' => [MailTemplates::button($this->contract->signUrl(), 'Contract lezen en ondertekenen')],
        ])]);
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
