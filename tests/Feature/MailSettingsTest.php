<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create(['email' => 'bestuur@example.com']));
    }

    private function saveSmtp(?string $password): void
    {
        Livewire::test(SettingsPage::class)
            ->fillForm([
                'mail.mailer' => 'smtp',
                'mail.host' => 'smtp.example.com',
                'mail.port' => 465,
                'mail.scheme' => 'smtps',
                'mail.username' => 'web@jokot.be',
                'mail.password' => $password,
                'mail.from_address' => 'noreply@jokot.be',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('data.mail.password', null);
    }

    public function test_smtp_settings_are_saved_with_an_encrypted_password_that_survives_a_blank_field(): void
    {
        $this->saveSmtp('geheim123');

        $stored = Setting::query()->where('key', 'mail')->sole()->value;
        $this->assertSame('smtp.example.com', $stored['host']);
        $this->assertSame(465, $stored['port']);
        $this->assertNotSame('geheim123', $stored['password']);
        $this->assertSame('geheim123', Crypt::decryptString($stored['password']));

        $this->saveSmtp(null);

        $this->assertSame('geheim123', Crypt::decryptString(Setting::query()->where('key', 'mail')->sole()->value['password']));
    }

    public function test_the_password_is_never_sent_back_to_the_form(): void
    {
        $this->saveSmtp('geheim123');

        Livewire::test(SettingsPage::class)->assertSet('data.mail.password', null)->assertSet('data.mail.host', 'smtp.example.com');
    }

    public function test_saved_settings_override_the_mail_config(): void
    {
        $this->saveSmtp('geheim123');
        app(Settings::class)->applyMailConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('geheim123', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@jokot.be', config('mail.from.address'));
        $this->assertSame('JH Jokot', config('mail.from.name'));
    }

    public function test_without_a_mailer_the_env_config_stays(): void
    {
        config(['mail.default' => 'array']);

        app(Settings::class)->applyMailConfig();

        $this->assertSame('array', config('mail.default'));
    }

    public function test_the_test_mail_is_sent_to_the_chosen_address(): void
    {
        app(Settings::class)->save(['mail' => [...Settings::defaults()['mail'], 'mailer' => 'array']]);

        Livewire::test(SettingsPage::class)
            ->callAction('sendTestMail', data: ['recipient' => 'test@example.com'])
            ->assertHasNoActionErrors()
            ->assertNotified('Testmail verstuurd naar test@example.com');

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('test@example.com', $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_a_failing_mail_server_shows_the_error(): void
    {
        app(Settings::class)->save(['mail' => [...Settings::defaults()['mail'], 'mailer' => 'smtp', 'host' => '127.0.0.1', 'port' => 1]]);

        Livewire::test(SettingsPage::class)
            ->callAction('sendTestMail', data: ['recipient' => 'test@example.com'])
            ->assertNotified('Testmail mislukt');
    }
}
