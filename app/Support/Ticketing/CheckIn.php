<?php

namespace App\Support\Ticketing;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admitting tickets at the door. The ticket row is locked, so two scanners cannot admit the same ticket twice.
 */
class CheckIn
{
    public function scan(string $input, Event $event, User $user): CheckInResult
    {
        $code = Ticket::normalizeCode($input);

        return DB::transaction(function () use ($code, $event, $user): CheckInResult {
            $ticket = Ticket::query()->with('ticketType', 'order', 'checkedInBy')->where('code', $code)->lockForUpdate()->first();

            if (! $ticket) {
                return new CheckInResult(CheckInResult::NOT_FOUND, 'Onbekend ticket.');
            }

            if ($ticket->event_id !== $event->id) {
                return new CheckInResult(CheckInResult::WRONG_EVENT, 'Dit ticket is voor een ander evenement: '.$ticket->event->title.'.', $ticket);
            }

            if ($ticket->cancelled_at) {
                return new CheckInResult(CheckInResult::CANCELLED, 'Dit ticket is geannuleerd.', $ticket);
            }

            if ($ticket->checked_in_at) {
                return new CheckInResult(CheckInResult::ALREADY, 'Al binnen sinds '.$ticket->checked_in_at->format('H:i').($ticket->checkedInBy ? ' ('.$ticket->checkedInBy->name.')' : '').'.', $ticket);
            }

            $ticket->update(['checked_in_at' => now(), 'checked_in_by' => $user->id]);

            return new CheckInResult(CheckInResult::OK, 'Welkom! '.($ticket->ticketType?->name ?? 'Ticket').' - '.$ticket->holder_name, $ticket->load('checkedInBy'));
        });
    }

    public function undo(Ticket $ticket): void
    {
        $ticket->update(['checked_in_at' => null, 'checked_in_by' => null]);
    }

    /**
     * @return array{tickets: int, checked_in: int}
     */
    public function stats(Event $event): array
    {
        $tickets = $event->tickets()->valid();

        return ['tickets' => (clone $tickets)->count(), 'checked_in' => (clone $tickets)->whereNotNull('checked_in_at')->count()];
    }
}
