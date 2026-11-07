<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Stats\VisitorStats;
use Database\Seeders\JokotContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JokotContentSeeder::class);
    }

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    public function test_public_page_views_are_counted_with_their_source(): void
    {
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => 'https://www.google.be/'])->get('/')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => url('/')])->get('/verhuren');
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => 'https://l.facebook.com/l.php'])->get('/');
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)', 'Referer' => 'https://www.gemeente.be/jeugd'])->get('/');

        $views = PageView::query()->orderBy('id')->get();
        $this->assertSame(['search', 'internal', 'social', 'referral'], $views->pluck('source')->all());
        $this->assertSame(['Google', null, 'Facebook', 'gemeente.be'], $views->pluck('source_name')->all());
        $this->assertSame(['/', '/verhuren', '/', '/'], $views->pluck('path')->all());
        $this->assertSame('mobile', $views[0]->device);
        $this->assertSame($views[0]->visitor, $views[1]->visitor, 'same browser = same visitor that day');
        $this->assertNotSame($views[0]->visitor, $views[3]->visitor);

        $totals = app(VisitorStats::class)->totals('30d');
        $this->assertSame(['views' => 4, 'visitors' => 2, 'entries' => 3, 'search' => 1, 'per_visitor' => 2.0], $totals);
    }

    public function test_bots_admins_and_admin_pages_are_not_counted(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/');
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/admin/login');
        $this->withHeaders(['User-Agent' => self::BROWSER])->get('/bestaat-niet');
        $this->actingAs(User::factory()->admin()->create())->withHeaders(['User-Agent' => self::BROWSER])->get('/');

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_statistics_page(): void
    {
        PageView::query()->create(['visitor' => 'abc', 'path' => '/verhuren', 'source' => 'search', 'source_name' => 'Bing', 'country_code' => 'NL', 'device' => 'desktop']);

        $this->actingAs(User::factory()->withPermissions([Permissions::STATISTICS])->create());
        $this->get('/admin/statistics?period=7d')->assertOk()->assertSee('Bezoekersstatistieken')->assertSee('Bing')->assertSee('/verhuren')->assertSee('Nederland');

        $this->actingAs(User::factory()->withPermissions([Permissions::PAGES])->create());
        $this->get('/admin/statistics')->assertForbidden();
    }
}
