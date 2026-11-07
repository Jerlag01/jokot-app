<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Support\Ticketing\CheckIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Check-in for the mobile app: events of today and the coming week, scanning, stats and a ticket list for offline use.
 */
class CheckInController extends Controller
{
    public function events(CheckIn $checkIn): JsonResponse
    {
        $events = Event::query()
            ->where('tickets_enabled', true)
            ->where('starts_at', '>=', now()->subDay()->startOfDay())
            ->where('starts_at', '<=', now()->addDays(7)->endOfDay())
            ->orderBy('starts_at')
            ->get();

        return response()->json(['data' => $events->map(fn (Event $event): array => [
            'id' => $event->id,
            'title' => $event->title,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            ...$checkIn->stats($event),
        ])]);
    }

    public function stats(Event $event, CheckIn $checkIn): JsonResponse
    {
        return response()->json($checkIn->stats($event));
    }

    public function scan(Request $request, Event $event, CheckIn $checkIn): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        $result = $checkIn->scan($data['code'], $event, $request->user());

        return response()->json([...$result->toArray(), 'stats' => $checkIn->stats($event)], $result->isOk() ? 200 : 422);
    }

    public function undo(Request $request, Event $event, CheckIn $checkIn): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        $ticket = $event->tickets()->where('code', Ticket::normalizeCode($data['code']))->firstOrFail();
        $checkIn->undo($ticket);

        return response()->json(['message' => 'Check-in ongedaan gemaakt.', 'stats' => $checkIn->stats($event)]);
    }

    /**
     * All tickets of the event, so the app can keep scanning when the connection drops (sync check-ins later).
     */
    public function tickets(Event $event): JsonResponse
    {
        return response()->json(['data' => $event->tickets()->with('ticketType')->get()->map(fn (Ticket $ticket): array => [
            'code' => $ticket->code,
            'type' => $ticket->ticketType?->name,
            'holder' => $ticket->holder_name,
            'cancelled' => $ticket->cancelled_at !== null,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
        ])]);
    }
}
