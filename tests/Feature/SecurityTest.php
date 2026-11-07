<?php

namespace Tests\Feature;

use App\Filament\Resources\BlockedIps\Pages\ManageBlockedIps;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Security\IpBlocker;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Database\Seeders\JokotContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JokotContentSeeder::class);
    }

    public function test_admin_logins_are_logged_with_the_login_and_reason(): void
    {
        $user = User::factory()->admin()->create(['email' => 'jeroen@jokot.be', 'password' => 'juist-wachtwoord']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)->fillForm(['email' => 'jeroen@jokot.be', 'password' => 'fout'])->call('authenticate');
        Livewire::test(Login::class)->fillForm(['email' => 'hacker@example.com', 'password' => 'x'])->call('authenticate');
        Livewire::test(Login::class)->fillForm(['email' => 'jeroen@jokot.be', 'password' => 'juist-wachtwoord'])->call('authenticate');

        $attempts = LoginAttempt::query()->orderBy('id')->get();
        $this->assertSame(['jeroen@jokot.be', 'hacker@example.com', 'jeroen@jokot.be'], $attempts->pluck('email')->all());
        $this->assertSame(['Verkeerd wachtwoord', 'Onbekend e-mailadres', null], $attempts->pluck('reason')->all());
        $this->assertSame([false, false, true], $attempts->pluck('successful')->all());
        $this->assertSame($user->id, $attempts[2]->user_id);
        $this->assertSame('admin', $attempts[0]->channel);
    }

    public function test_check_in_app_logins_are_logged(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'niemand@example.com', 'password' => 'x', 'device_name' => 'telefoon'])->assertStatus(422);

        $attempt = LoginAttempt::query()->sole();
        $this->assertSame('api', $attempt->channel);
        $this->assertSame('Onbekend e-mailadres', $attempt->reason);
    }

    public function test_too_many_failures_block_the_ip_until_unblocked(): void
    {
        $blocker = app(IpBlocker::class);

        foreach (range(1, 4) as $try) {
            $blocker->record('admin@jokot.be', '127.0.0.1', false);
        }
        $this->get('/')->assertOk();

        $blocker->record('admin@jokot.be', '127.0.0.1', false);
        $block = BlockedIp::query()->sole();
        $this->assertTrue($block->is_automatic);
        $this->assertTrue($block->blocked_until->between(now()->addMinutes(59), now()->addMinutes(61)));

        $this->get('/')->assertForbidden();
        $this->get('/admin/login')->assertForbidden();
        $this->assertSame(2, $block->fresh()->hits);

        $this->artisan('security:unblock', ['ip' => '127.0.0.1'])->assertSuccessful();
        $this->get('/')->assertOk();
    }

    public function test_ranges_whitelist_and_expiry(): void
    {
        $blocker = app(IpBlocker::class);
        BlockedIp::query()->create(['ip' => '203.0.113.0/24']);
        BlockedIp::query()->create(['ip' => '198.51.100.9', 'blocked_until' => now()->subMinute()]);

        $this->assertNotNull($blocker->blockFor('203.0.113.77'));
        $this->assertNull($blocker->blockFor('198.51.100.9'), 'expired');

        settings()->save(['security' => ['whitelist' => "203.0.113.77\n10.0.0.0/8"]]);
        $this->assertNull($blocker->blockFor('203.0.113.77'));

        foreach (range(1, 6) as $try) {
            $blocker->record('x@example.com', '10.1.2.3', false);
        }
        $this->assertFalse(BlockedIp::query()->where('ip', '10.1.2.3')->exists());
    }

    public function test_security_pages_and_own_ip_protection(): void
    {
        LoginAttempt::query()->create(['email' => 'a@b.be', 'ip' => '91.181.119.167', 'country_code' => 'BE', 'successful' => false, 'reason' => 'Verkeerd wachtwoord']);

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/login-attempts')->assertOk()->assertSee('a@b.be')->assertSee('België');
        $this->get('/admin/blocked-ips')->assertOk();

        Livewire::test(ManageBlockedIps::class)
            ->callAction('create', ['ip' => '127.0.0.1'])
            ->assertHasActionErrors(['ip']);
        Livewire::test(ManageBlockedIps::class)
            ->callAction('create', ['ip' => '203.0.113.0/24', 'reason' => 'Spam'])
            ->assertHasNoActionErrors();
        $this->assertTrue(BlockedIp::query()->where('ip', '203.0.113.0/24')->exists());

        $this->actingAs(User::factory()->withPermissions([Permissions::SETTINGS])->create());
        $this->get('/admin/login-attempts')->assertForbidden();
    }
}
