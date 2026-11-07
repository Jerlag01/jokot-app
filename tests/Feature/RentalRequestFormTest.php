<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Mail\RentalRequestReceived;
use App\Models\Event;
use App\Models\Product;
use App\Models\RentalBlockedDate;
use App\Models\RentalExtra;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\Availability;
use App\Support\RentalWeekdays;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RentalRequestFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'event_date' => now()->addDays(60)->toDateString(),
            'rental_rate_id' => RentalRate::factory()->create(['price' => 250, 'deposit' => 200])->id,
            'first_name' => 'An',
            'last_name' => 'Peeters',
            'email' => 'an@example.com',
            'phone' => '0470 11 22 33',
            'street' => 'Kerkstraat',
            'house_number' => '1',
            'postal_code' => '2845',
            'city' => 'Niel',
            'expected_guests' => 120,
            'brings_own_wine' => '1',
            'terms' => '1',
            ...$overrides,
        ];
    }

    public function test_a_valid_request_is_stored_with_drinks_quote_and_notifications(): void
    {
        Mail::fake();
        $board = User::factory()->admin()->create();
        $extra = RentalExtra::factory()->create(['price' => 225, 'deposit' => 75]);
        $cola = Product::factory()->create(['name' => 'Cola 20cl']);
        $hidden = Product::factory()->create(['is_orderable' => false]);

        $this->from('/verhuren')
            ->post(route('rental-requests.store'), $this->payload([
                'rental_extra_id' => $extra->id,
                'drinks' => [$cola->id => '3', $hidden->id => '5'],
            ]))
            ->assertRedirect('/verhuren#huuraanvraag')
            ->assertSessionHas('rental_request_reference', 'HA-'.now()->year.'-001');

        $rental = RentalRequest::query()->sole();
        $this->assertSame(RentalStatus::New, $rental->status);
        $this->assertSame('475.00', $rental->quoted_price);
        $this->assertSame('275.00', $rental->quoted_deposit);
        $this->assertTrue($rental->brings_own_wine);
        $this->assertNotNull($rental->terms_accepted_at);
        $this->assertSame([['Cola 20cl (bak van 24 flesjes)', 3]], $rental->drinks->map(fn ($line) => [$line->name, $line->quantity])->all());

        Mail::assertSent(RentalRequestReceived::class, fn ($mail) => $mail->forBoard && $mail->hasTo('info@jokot.be'));
        Mail::assertSent(RentalRequestReceived::class, fn ($mail) => ! $mail->forBoard && $mail->hasTo('an@example.com'));
        $this->assertSame(1, $board->notifications()->count());
    }

    public function test_dates_outside_the_booking_window_are_rejected(): void
    {
        $this->post(route('rental-requests.store'), $this->payload(['event_date' => now()->addDays(10)->toDateString()]))
            ->assertSessionHasErrors('event_date');
        $this->post(route('rental-requests.store'), $this->payload(['event_date' => now()->addMonths(7)->toDateString()]))
            ->assertSessionHasErrors('event_date');

        $this->assertDatabaseCount('rental_requests', 0);
    }

    public function test_a_date_with_a_confirmed_rental_or_own_event_is_rejected(): void
    {
        $rented = now()->addDays(50);
        $ownEvent = now()->addDays(55);
        RentalRequest::factory()->confirmed()->create(['event_date' => $rented->toDateString()]);
        Event::factory()->create(['starts_at' => $ownEvent->copy()->setTime(20, 0)]);

        $this->post(route('rental-requests.store'), $this->payload(['event_date' => $rented->toDateString()]))->assertSessionHasErrors('event_date');
        $this->post(route('rental-requests.store'), $this->payload(['event_date' => $ownEvent->toDateString()]))->assertSessionHasErrors('event_date');
    }

    public function test_blocked_dates_and_weekdays_are_rejected_and_shown_as_closed(): void
    {
        $blocked = now()->addDays(60)->startOfDay();
        RentalBlockedDate::query()->create(['starts_on' => $blocked->toDateString(), 'ends_on' => $blocked->copy()->addDays(2)->toDateString(), 'reason' => 'Werken']);

        $this->post(route('rental-requests.store'), $this->payload(['event_date' => $blocked->copy()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['event_date' => 'Op deze datum kan je de zaal niet huren. Kies een andere datum in de kalender.']);

        $free = $blocked->copy()->addDays(7);
        RentalWeekdays::save([(string) $free->dayOfWeekIso]);
        $this->post(route('rental-requests.store'), $this->payload(['event_date' => $free->toDateString()]))->assertSessionHasErrors('event_date');

        RentalWeekdays::save([]);
        $this->post(route('rental-requests.store'), $this->payload(['event_date' => $free->toDateString()]))->assertSessionHasNoErrors();

        $availability = app(Availability::class);
        $this->assertSame(['Werken', 'Werken', 'Werken'], array_values($availability->blockedBetween($blocked->copy()->subDays(5), $blocked->copy()->addDays(5))));
        $this->assertFalse($availability->isRequestable($blocked));
        $this->assertTrue($availability->isAvailable($blocked), 'the board can still enter a rental itself');

        $this->get(route('calendar', ['maand' => $blocked->format('Y-m')]))
            ->assertOk()
            ->assertDontSee('data-pick-date="'.$blocked->toDateString().'"', false);
    }

    public function test_a_new_unhandled_request_does_not_block_the_date(): void
    {
        Mail::fake();
        $date = now()->addDays(50)->toDateString();
        RentalRequest::factory()->create(['event_date' => $date]);

        $this->post(route('rental-requests.store'), $this->payload(['event_date' => $date]))->assertSessionHasNoErrors();
    }

    public function test_terms_are_required_and_the_honeypot_blocks_bots(): void
    {
        $this->post(route('rental-requests.store'), $this->payload(['terms' => null]))->assertSessionHasErrors('terms');
        $this->post(route('rental-requests.store'), $this->payload(['website' => 'http://spam.example']))->assertSessionHasErrors('website');

        $this->assertDatabaseCount('rental_requests', 0);
    }

    public function test_requests_are_refused_when_rentals_are_closed(): void
    {
        app(Settings::class)->save(['rentals_open' => false]);

        $this->post(route('rental-requests.store'), $this->payload())->assertForbidden();
    }

    public function test_references_count_up_per_year(): void
    {
        RentalRequest::factory()->count(2)->create();

        $this->assertSame('HA-'.now()->year.'-003', RentalRequest::nextReference());
    }
}
