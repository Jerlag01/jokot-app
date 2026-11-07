<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Filament\Pages\CheckInPage;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Ticketing\CheckIn;
use App\Support\Ticketing\CheckInResult;
use App\Support\Ticketing\TicketSales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $doorman;

    private Event $event;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->doorman = User::factory()->withPermissions([Permissions::CHECKIN])->create(['email' => 'deur@jokot.be', 'password' => 'geheim123']);
        $type = TicketType::factory()->create(['event_id' => Event::factory()->create(['tickets_enabled' => true, 'starts_at' => now()->addHours(3)])->id]);
        $this->event = $type->event;
        $order = app(TicketSales::class)->boxOffice($type, 1, PaymentProvider::Cash, $this->doorman, 'Jan');
        $this->ticket = $order->tickets()->sole();
    }

    public function test_a_ticket_gets_in_once(): void
    {
        $checkIn = app(CheckIn::class);

        $first = $checkIn->scan($this->ticket->code, $this->event, $this->doorman);
        $second = $checkIn->scan($this->ticket->code, $this->event, $this->doorman);

        $this->assertSame(CheckInResult::OK, $first->status);
        $this->assertSame(CheckInResult::ALREADY, $second->status);
        $this->assertSame(['tickets' => 1, 'checked_in' => 1], $checkIn->stats($this->event));
    }

    public function test_typed_codes_are_normalised(): void
    {
        $typed = strtolower(implode('-', str_split($this->ticket->code, 4)));

        $this->assertTrue(app(CheckIn::class)->scan(" {$typed} ", $this->event, $this->doorman)->isOk());
    }

    public function test_unknown_cancelled_and_other_event_tickets_are_refused(): void
    {
        $checkIn = app(CheckIn::class);
        $otherEvent = Event::factory()->create(['tickets_enabled' => true]);

        $this->assertSame(CheckInResult::NOT_FOUND, $checkIn->scan('JKNOPE', $this->event, $this->doorman)->status);
        $this->assertSame(CheckInResult::WRONG_EVENT, $checkIn->scan($this->ticket->code, $otherEvent, $this->doorman)->status);

        $this->ticket->update(['cancelled_at' => now()]);
        $this->assertSame(CheckInResult::CANCELLED, $checkIn->scan($this->ticket->code, $this->event, $this->doorman)->status);
    }

    public function test_the_admin_check_in_screen_scans(): void
    {
        $this->actingAs($this->doorman);

        Livewire::test(CheckInPage::class)
            ->assertSet('event', $this->event->id)
            ->call('scan', $this->ticket->code)
            ->assertSet('result.status', CheckInResult::OK)
            ->assertSee('Geldig');

        $this->assertNotNull($this->ticket->fresh()->checked_in_at);
    }

    public function test_the_api_flow_for_the_mobile_app(): void
    {
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'deur@jokot.be', 'password' => 'geheim123', 'device_name' => 'Pixel deur'])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name']]);
        $headers = ['Authorization' => 'Bearer '.$login->json('token')];

        $this->getJson('/api/v1/events', $headers)->assertOk()->assertJsonPath('data.0.id', $this->event->id)->assertJsonPath('data.0.tickets', 1);
        $this->getJson("/api/v1/events/{$this->event->id}/tickets", $headers)->assertOk()->assertJsonPath('data.0.code', $this->ticket->code);

        $this->postJson("/api/v1/events/{$this->event->id}/checkin", ['code' => $this->ticket->code], $headers)
            ->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('stats.checked_in', 1);
        $this->postJson("/api/v1/events/{$this->event->id}/checkin", ['code' => $this->ticket->code], $headers)
            ->assertStatus(422)->assertJsonPath('status', 'already_checked_in');
        $this->postJson("/api/v1/events/{$this->event->id}/checkin/undo", ['code' => $this->ticket->code], $headers)
            ->assertOk()->assertJsonPath('stats.checked_in', 0);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();
        $this->getJson('/api/v1/events', $headers)->assertUnauthorized();
    }

    public function test_the_api_refuses_wrong_passwords_and_users_without_the_check_in_right(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'deur@jokot.be', 'password' => 'fout', 'device_name' => 'x'])->assertStatus(422);

        User::factory()->withPermissions([Permissions::PAGES])->create(['email' => 'editor@jokot.be', 'password' => 'geheim123']);
        $this->postJson('/api/v1/auth/login', ['email' => 'editor@jokot.be', 'password' => 'geheim123', 'device_name' => 'x'])->assertForbidden();

        $this->getJson('/api/v1/events')->assertUnauthorized();
    }
}
