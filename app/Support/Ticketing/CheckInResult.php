<?php

namespace App\Support\Ticketing;

use App\Models\Ticket;

/**
 * The answer to one scan, shown on the door screen and returned by the API.
 */
final class CheckInResult
{
    public const OK = 'ok';

    public const ALREADY = 'already_checked_in';

    public const NOT_FOUND = 'not_found';

    public const WRONG_EVENT = 'wrong_event';

    public const CANCELLED = 'cancelled';

    public function __construct(public readonly string $status, public readonly string $message, public readonly ?Ticket $ticket = null) {}

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'ok' => $this->isOk(),
            'message' => $this->message,
            'ticket' => $this->ticket ? [
                'code' => $this->ticket->code,
                'type' => $this->ticket->ticketType?->name,
                'holder' => $this->ticket->holder_name,
                'order' => $this->ticket->order?->number,
                'checked_in_at' => $this->ticket->checked_in_at?->toIso8601String(),
                'checked_in_by' => $this->ticket->checkedInBy?->name,
            ] : null,
        ];
    }
}
