<?php

namespace App\Mail;

use App\Models\RentalRequest;
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

    public function envelope(): Envelope
    {
        $date = $this->rentalRequest->event_date->translatedFormat('j F Y');

        return new Envelope(
            subject: $this->forBoard
                ? "Nieuwe huuraanvraag {$this->rentalRequest->reference} voor {$date}"
                : 'Je huuraanvraag bij '.settings()->siteName()." ({$this->rentalRequest->reference})",
            replyTo: $this->forBoard ? [$this->rentalRequest->email] : [settings()->get('email')],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rental-request-received');
    }
}
