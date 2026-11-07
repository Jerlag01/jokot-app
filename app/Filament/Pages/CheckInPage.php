<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Event;
use App\Models\Ticket;
use App\Support\Permissions;
use App\Support\Ticketing\CheckIn;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The door: scan QR codes with the camera (or type the code) and see at once whether a ticket is valid.
 */
class CheckInPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tickets;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Check-in';

    protected static ?string $title = 'Check-in';

    protected static ?string $slug = 'checkin';

    protected string $view = 'filament.pages.check-in';

    #[Url]
    public ?int $event = null;

    public string $code = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::CHECKIN);
    }

    public function mount(): void
    {
        $this->event ??= $this->events()->first(fn (Event $event): bool => $event->starts_at->isToday() || ($event->ends_at && now()->between($event->starts_at, $event->ends_at)))?->id
            ?? $this->events()->first()?->id;
    }

    /**
     * Events with ticket sales from yesterday up to two weeks ahead.
     *
     * @return Collection<int, Event>
     */
    public function events(): Collection
    {
        return Event::query()
            ->where('tickets_enabled', true)
            ->where('starts_at', '>=', now()->subDay()->startOfDay())
            ->where('starts_at', '<=', now()->addDays(14)->endOfDay())
            ->orderBy('starts_at')
            ->get();
    }

    public function selectedEvent(): ?Event
    {
        return $this->event ? Event::query()->find($this->event) : null;
    }

    public function scan(?string $code = null): void
    {
        $event = $this->selectedEvent();
        $code = trim($code ?? $this->code);

        if (! $event || $code === '') {
            return;
        }

        $this->result = app(CheckIn::class)->scan($code, $event, auth()->user())->toArray();
        $this->code = '';
        $this->dispatch('checkin-result', ok: $this->result['ok'], status: $this->result['status']);
    }

    public function undo(string $code): void
    {
        $event = $this->selectedEvent();
        $ticket = $event?->tickets()->where('code', Ticket::normalizeCode($code))->first();

        if ($ticket) {
            app(CheckIn::class)->undo($ticket);
            $this->result = null;
        }
    }

    /**
     * @return array{tickets: int, checked_in: int}
     */
    public function stats(): array
    {
        $event = $this->selectedEvent();

        return $event ? app(CheckIn::class)->stats($event) : ['tickets' => 0, 'checked_in' => 0];
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function recent(): Collection
    {
        return Ticket::query()
            ->with('ticketType', 'checkedInBy')
            ->where('event_id', $this->event)
            ->whereNotNull('checked_in_at')
            ->latest('checked_in_at')
            ->limit(8)
            ->get();
    }
}
