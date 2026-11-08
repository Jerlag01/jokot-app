<?php

namespace Tests\Feature;

use App\Filament\Resources\MailTemplates\Pages\EditMailTemplate;
use App\Filament\Resources\MailTemplates\Pages\ListMailTemplates;
use App\Mail\ContractMail;
use App\Mail\RentalRequestReceived;
use App\Models\MailTemplate;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\Contracts;
use App\Support\MailTemplates;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_template_exists_after_migrating_with_its_default_text(): void
    {
        $this->assertSame(array_keys(MailTemplates::definitions()), MailTemplate::query()->pluck('key')->sort()->values()->pipe(fn ($keys) => collect(array_keys(MailTemplates::definitions()))->filter(fn ($key) => $keys->contains($key))->values()->all()));
        $this->assertSame(count(MailTemplates::definitions()), MailTemplate::query()->count());
    }

    public function test_the_default_rental_confirmation_still_contains_the_overview(): void
    {
        $rental = RentalRequest::factory()->create(['first_name' => 'An']);
        $mail = new RentalRequestReceived($rental);

        $this->assertSame('Je huuraanvraag bij JH Jokot ('.$rental->reference.')', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('Hallo An,', $html);
        $this->assertStringContainsString('Bedankt voor je aanvraag', $html);
        $this->assertStringContainsString('Gebruikersgroep', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringNotContainsString('{overzicht}', $html);
        $this->assertStringNotContainsString('{voornaam}', $html);
    }

    public function test_an_edited_template_is_used_and_filled_in_safely(): void
    {
        MailTemplate::query()->whereKey('rental_board')->update([
            'subject' => 'Aanvraag van {naam} op {datum_kort}',
            'body' => "Hoi bestuur,\n\n**{naam}** wil huren. {onbekend}\n\n{knop}\n\nGroetjes",
        ]);
        $rental = RentalRequest::factory()->create(['first_name' => '<b>Jan</b>', 'last_name' => 'Smet']);

        $mail = new RentalRequestReceived($rental, forBoard: true);

        $this->assertStringStartsWith('Aanvraag van <b>Jan</b> Smet op ', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('&lt;b&gt;Jan&lt;/b&gt; Smet', $html);
        $this->assertStringNotContainsString('<b>Jan</b>', $html);
        $this->assertStringContainsString('{onbekend}', $html);
        $this->assertStringContainsString('Aanvraag openen', $html);
        $this->assertStringContainsString('/admin/rental-requests/'.$rental->id, $html);
        $this->assertStringNotContainsString('Gebruikersgroep', $html);
    }

    public function test_the_contract_link_template_has_its_button(): void
    {
        $this->seed(JokotModulesSeeder::class);
        $contract = app(Contracts::class)->forRecord(RentalRequest::factory()->create());

        $html = (new ContractMail($contract))->render();

        $this->assertStringContainsString('Contract lezen en ondertekenen', $html);
        $this->assertStringContainsString($contract->signUrl(), $html);
    }

    public function test_admins_can_edit_preview_and_reset_a_template(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        RentalRequest::factory()->create();

        Livewire::test(ListMailTemplates::class)->assertOk()->assertSee('Huuraanvraag: bevestiging aan de aanvrager');

        Livewire::test(EditMailTemplate::class, ['record' => 'rental_requester'])
            ->fillForm(['subject' => 'Bedankt {voornaam}', 'body' => 'Hallo {voornaam}, we bekijken het.'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->mountAction('preview')
            ->assertActionMounted('preview');

        $this->assertStringContainsString('we bekijken het.', MailTemplates::sample('rental_requester')->render());

        $this->assertSame('Bedankt {voornaam}', MailTemplate::query()->find('rental_requester')->subject);

        Livewire::test(EditMailTemplate::class, ['record' => 'rental_requester'])->callAction('reset');
        $this->assertSame(MailTemplates::definition('rental_requester')['body'], MailTemplate::query()->find('rental_requester')->body);
    }
}
