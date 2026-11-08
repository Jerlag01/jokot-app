<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings as SettingsPage;
use App\Mail\RentalRequestReceived;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\Contracts;
use App\Support\Mailing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class MailSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function setMail(array $values): void
    {
        settings()->save(['mail' => [...(array) settings()->get('mail'), ...$values]]);
    }

    private function deliveredCount(): int
    {
        return app('mailer')->getSymfonyTransport()->messages()->count();
    }

    public function test_switching_mail_off_drops_every_message_except_the_test_mail(): void
    {
        $this->setMail(['enabled' => false]);

        Mail::raw('Hallo', fn ($message) => $message->to('an@example.com')->subject('Gewone mail'));
        $this->assertSame(0, $this->deliveredCount());

        Mail::raw('Test', fn ($message) => $message->to('an@example.com')->subject('Testmail')
            ->getSymfonyMessage()->getHeaders()->addTextHeader(Mailing::TEST_HEADER, '1'));
        $this->assertSame(1, $this->deliveredCount());

        $this->setMail(['enabled' => true]);
        Mail::raw('Hallo', fn ($message) => $message->to('an@example.com')->subject('Gewone mail'));
        $this->assertSame(2, $this->deliveredCount());
    }

    public function test_sending_a_contract_by_hand_fails_clearly_while_mail_is_off(): void
    {
        $this->setMail(['enabled' => false]);
        $contracts = app(Contracts::class);
        $contract = $contracts->forRecord(RentalRequest::factory()->create());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('staat uit');

        $contracts->send($contract);
    }

    public function test_automatic_mails_can_be_switched_off_one_by_one(): void
    {
        Mail::fake();
        $this->setMail(['auto_rental_requester' => false]);
        settings()->save(['notification_email' => 'bestuur@example.com']);

        $this->post(route('rental-requests.store'), [
            'event_date' => now()->addDays(60)->toDateString(),
            'rental_rate_id' => RentalRate::factory()->create()->id,
            'first_name' => 'An', 'last_name' => 'Peeters', 'email' => 'an@example.com', 'phone' => '0470 11 22 33',
            'street' => 'Kerkstraat', 'house_number' => '1', 'postal_code' => '2845', 'city' => 'Niel',
            'expected_guests' => 120, 'brings_own_wine' => '0', 'terms' => '1',
        ])->assertSessionHasNoErrors();

        Mail::assertSent(RentalRequestReceived::class, fn (RentalRequestReceived $mail): bool => $mail->hasTo('bestuur@example.com'));
        Mail::assertNotSent(RentalRequestReceived::class, fn (RentalRequestReceived $mail): bool => $mail->hasTo('an@example.com'));
    }

    public function test_the_switches_are_saved_from_the_settings_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SettingsPage::class)
            ->fillForm(['mail.enabled' => true, 'mail.auto_tickets' => false, 'rental_contract_auto_send' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Mailing::enabled());
        $this->assertFalse(Mailing::allows('tickets'));
        $this->assertTrue(Mailing::allows('rental_board'));
        $this->assertTrue((bool) settings()->get('rental_contract_auto_send'));

        Livewire::test(SettingsPage::class)->fillForm(['mail.enabled' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse(Mailing::enabled());
        $this->assertFalse(Mailing::allows('rental_board'));
    }
}
