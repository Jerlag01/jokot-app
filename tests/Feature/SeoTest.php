<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Database\Seeders\JokotContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JokotContentSeeder::class);
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_a_non_production_site_is_never_indexed(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSeeText('Disallow: /')->assertDontSeeText('Sitemap');
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertDontSee('rel="canonical"', false);
    }

    public function test_production_pages_have_meta_open_graph_and_structured_data(): void
    {
        $this->production();
        settings()->save(['seo' => ['indexable' => true, 'google_verification' => 'abc123']]);

        $this->get('/robots.txt')->assertSeeText('Disallow: /admin')->assertSeeText('Sitemap: '.url('/sitemap.xml'));

        $this->get('/verhuren')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<link rel="canonical" href="'.url('/verhuren').'">', false)
            ->assertSee('<meta name="description" content="Het jeugdhuis huren voor je feest of event', false)
            ->assertSee('<meta property="og:title" content="Verhuren">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="google-site-verification" content="abc123">', false)
            ->assertSee('"@type":["Organization","LocalBusiness"]', false)
            ->assertSee('"postalCode":"2845","addressLocality":"Niel"', false);

        $this->get('/')->assertSee('<title>JH Jokot - Jeugdhuis in Niel</title>', false)->assertSee('<link rel="canonical" href="'.url('/').'">', false);
    }

    public function test_indexing_can_be_switched_off_in_the_settings(): void
    {
        $this->production();
        $this->get('/')->assertHeaderMissing('X-Robots-Tag')->assertSee('index, follow', false);

        settings()->save(['seo' => ['indexable' => false]]);

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/robots.txt')->assertSeeText('Disallow: /')->assertDontSeeText('Sitemap');
    }

    public function test_events_carry_event_structured_data_and_private_pages_are_noindex(): void
    {
        $this->production();
        $event = Event::factory()->create(['title' => 'Grote Fuif', 'tickets_enabled' => true, 'is_published' => true]);
        TicketType::factory()->for($event)->create(['name' => 'Voorverkoop', 'price' => 8, 'is_active' => true]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('"@type":"Event"', false)
            ->assertSee('"name":"Grote Fuif"', false)
            ->assertSee('"price":"8.00","priceCurrency":"EUR"', false)
            ->assertSee('<meta property="og:type" content="article">', false);

        $order = Order::factory()->create();
        $this->get(route('orders.show', $order))->assertSee('noindex, nofollow', false);
    }

    public function test_sitemap_lists_published_pages_and_events_only(): void
    {
        $published = Event::factory()->create(['is_published' => true]);
        $hidden = Event::factory()->create(['is_published' => false]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/').'</loc>', false)
            ->assertSee('<loc>'.url('/verhuren').'</loc>', false)
            ->assertSee('<loc>'.route('events.show', $published).'</loc>', false)
            ->assertDontSee(route('events.show', $hidden), false);
    }
}
