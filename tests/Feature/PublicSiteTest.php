<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\JokotContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JokotContentSeeder::class);
    }

    public function test_home_shows_the_welcome_banner_menu_and_footer(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Info', 'Verhuren', 'Winkel'])
            ->assertSee('Welkom bij')
            ->assertSee('Je event bij ons organiseren? Kan zeker!')
            ->assertSee('Landbouwstraat 145/3, 2845 Niel')
            ->assertSee('https://www.instagram.com/jh_jokot/', false);
    }

    public function test_seeded_pages_render_the_original_content(): void
    {
        $this->get('/info')->assertOk()->assertSee('In 2016 is het Jeugdhuis heropgebouwd')->assertSee('Woensdag')->assertSee('1013.578.536');
        $this->get('/verhuren')->assertOk()
            ->assertSee('Privaat/Bedrijf')
            ->assertSee('€400')
            ->assertSee('DJ Installatie Nexus 3')
            ->assertSee('Max. 350 personen')
            ->assertSee('id="kalender"', false)
            ->assertSee('id="huuraanvraag"', false)
            ->assertSee('La Chouffe Blond');
        $this->get('/algemene-voorwaarden')->assertOk()->assertSee('Tickets worden noch terugbetaald');
        $this->get('/winkel')->assertOk()->assertSee('Er zijn momenteel geen tickets te koop');
    }

    public function test_upcoming_published_events_appear_in_the_agenda(): void
    {
        $event = Event::factory()->create(['title' => 'Kwisavond']);
        Event::factory()->create(['title' => 'Verborgen feest', 'is_published' => false]);

        $this->get('/')->assertSee('Kwisavond')->assertDontSee('Verborgen feest');
        $this->get(route('events.show', $event))->assertOk()->assertSee('Kwisavond');
    }

    public function test_unpublished_events_and_unknown_pages_are_not_found(): void
    {
        $hidden = Event::factory()->create(['is_published' => false]);

        $this->get(route('events.show', $hidden))->assertNotFound();
        $this->get('/bestaat-niet')->assertNotFound();
    }

    public function test_old_wordpress_addresses_redirect(): void
    {
        $this->get('/wp-booking-calendar')->assertRedirect('/verhuren#huuraanvraag');
        $this->get('/winkelwagen')->assertRedirect('/winkel');
        $this->get('/home')->assertRedirect('/');
    }

    public function test_calendar_marks_rented_and_own_event_dates(): void
    {
        $date = now()->addDays(45);
        Event::factory()->create(['starts_at' => $date->copy()->setTime(20, 0), 'ends_at' => $date->copy()->addDay()->setTime(2, 0)]);

        $this->get('/verhuren?maand='.$date->format('Y-m'))
            ->assertOk()
            ->assertSee('calendar-day is-event', false)
            ->assertDontSee('data-pick-date="'.$date->toDateString().'"', false)
            ->assertSee('data-pick-date="'.$date->copy()->addDay()->toDateString().'"', false);
    }

    public function test_the_calendar_endpoint_returns_only_the_month_grid(): void
    {
        $date = now()->addDays(45);
        Event::factory()->create(['starts_at' => $date->copy()->setTime(20, 0)]);

        $response = $this->get(route('calendar', ['maand' => $date->format('Y-m'), 'form' => 1]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee(ucfirst($date->translatedFormat('F Y')))
            ->assertSee('calendar-day is-event', false)
            ->assertSee('href="#huuraanvraag"', false);

        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_the_calendar_endpoint_links_to_the_rental_page_without_a_form(): void
    {
        $this->get(route('calendar', ['maand' => now()->addMonths(2)->format('Y-m')]))
            ->assertOk()
            ->assertSee('/verhuren?datum=', false);
    }

    public function test_the_calendar_endpoint_clamps_and_rejects_months(): void
    {
        $this->get(route('calendar', ['maand' => '2000-01']))->assertOk()->assertSee(ucfirst(now()->translatedFormat('F Y')));
        $this->get(route('calendar', ['maand' => 'nope']))->assertNotFound();
        $this->get(route('calendar', ['maand' => '2026-13']))->assertNotFound();
        $this->get(route('calendar'))->assertNotFound();
    }
}
