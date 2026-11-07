<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Mail\ContractMail;
use App\Models\CupLoan;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use App\Support\Contracts;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JokotModulesSeeder::class);
        Storage::fake('local');
    }

    private function rental(): RentalRequest
    {
        return RentalRequest::factory()->create([
            'first_name' => 'An', 'last_name' => 'Peeters', 'organisation' => 'Chiro Niel', 'details' => 'Verjaardagsfeest',
            'rental_rate_id' => RentalRate::factory()->create(['name' => 'Jeugdverenigingen', 'price' => 150, 'deposit' => 200])->id,
        ]);
    }

    public function test_the_rental_contract_is_filled_in_from_the_request(): void
    {
        $contract = app(Contracts::class)->forRecord($this->rental());

        $this->get($contract->signUrl())
            ->assertOk()
            ->assertSee('Chiro Niel - An Peeters')
            ->assertSee('Verjaardagsfeest')
            ->assertSee('Jeugdverenigingen')
            ->assertSee('BE65 7380 4975 8796')
            ->assertSee('90dB')
            ->assertDontSee('{huurder}');
    }

    public function test_sending_mails_the_signing_link(): void
    {
        Mail::fake();
        $rental = $this->rental();
        $contract = app(Contracts::class)->forRecord($rental);

        app(Contracts::class)->send($contract);

        $this->assertSame(ContractStatus::Sent, $contract->fresh()->status);
        Mail::assertSent(ContractMail::class, fn (ContractMail $mail): bool => $mail->hasTo($rental->email) && ! $mail->signedCopy);
    }

    public function test_signing_online_freezes_the_text_and_stores_the_pdf(): void
    {
        Mail::fake();
        $contract = app(Contracts::class)->forRecord($this->rental());

        $this->post(route('contracts.sign', $contract->token), ['signer_name' => 'An Peeters', 'signature' => self::PNG, 'agree' => '1'])
            ->assertRedirect(route('contracts.show', $contract->token));

        $contract->refresh();
        $this->assertTrue($contract->isSigned());
        $this->assertStringContainsString('Chiro Niel', $contract->body);
        Storage::disk('local')->assertExists($contract->signature_path);
        Storage::disk('local')->assertExists($contract->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($contract->pdf_path));
        Mail::assertSent(ContractMail::class, fn (ContractMail $mail): bool => $mail->signedCopy);

        // Later changes to the request do not alter the signed text.
        $contract->contractable->update(['organisation' => 'Iets anders']);
        $this->get($contract->signUrl())->assertSee('Chiro Niel')->assertSee('Ondertekend door An Peeters');
        $this->get(route('contracts.pdf', $contract->token))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_signature_is_required_and_must_be_a_png(): void
    {
        $contract = app(Contracts::class)->forRecord($this->rental());

        $this->post(route('contracts.sign', $contract->token), ['signer_name' => 'An', 'agree' => '1'])->assertSessionHasErrors('signature');
        $this->post(route('contracts.sign', $contract->token), ['signer_name' => 'An', 'signature' => 'data:image/png;base64,aGFsbG8=', 'agree' => '1'])->assertSessionHasErrors('signature');

        $this->assertFalse($contract->fresh()->isSigned());
    }

    public function test_reset_allows_signing_again(): void
    {
        $contract = app(Contracts::class)->forRecord($this->rental());
        app(Contracts::class)->sign($contract, 'An', self::PNG, '127.0.0.1');
        $pdf = $contract->pdf_path;

        app(Contracts::class)->reset($contract);

        $this->assertSame(ContractStatus::Draft, $contract->fresh()->status);
        Storage::disk('local')->assertMissing($pdf);
    }

    public function test_the_cup_loan_contract_uses_its_own_template(): void
    {
        $loan = CupLoan::factory()->create(['organisation' => 'Oudercomité De Klimop', 'cups' => 250, 'crates' => 5]);

        $this->get(app(Contracts::class)->forRecord($loan)->signUrl())
            ->assertOk()
            ->assertSee('Overeenkomst uitlenen wisselbekers')
            ->assertSee('Oudercomité De Klimop')
            ->assertSee('<strong>250</strong>', false)
            ->assertSee('€0,25');
    }

    public function test_unknown_tokens_are_not_found(): void
    {
        $this->get('/contract/'.str_repeat('x', 48))->assertNotFound();
    }
}
