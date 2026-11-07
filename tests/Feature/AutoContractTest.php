<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\CupLoanStatus;
use App\Enums\RentalStatus;
use App\Filament\Resources\CupLoans\Pages\ViewCupLoan;
use App\Filament\Resources\RentalRequests\Pages\ViewRentalRequest;
use App\Mail\ContractMail;
use App\Models\CupLoan;
use App\Models\RentalRequest;
use App\Models\User;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AutoContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JokotModulesSeeder::class);
        Mail::fake();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_confirming_a_reservation_sends_the_contract_to_sign(): void
    {
        $rental = RentalRequest::factory()->create();

        Livewire::test(ViewRentalRequest::class, ['record' => $rental->getRouteKey()])->callAction('confirm');

        $contract = $rental->fresh()->contract;
        $this->assertSame(ContractStatus::Sent, $contract->status);
        Mail::assertSent(ContractMail::class, fn (ContractMail $mail): bool => $mail->hasTo($rental->email) && ! $mail->signedCopy);
    }

    public function test_an_option_does_not_send_a_contract_and_it_is_only_sent_once(): void
    {
        $rental = RentalRequest::factory()->create();

        $rental->update(['status' => RentalStatus::Option]);
        Mail::assertNothingSent();

        $rental->update(['status' => RentalStatus::Confirmed]);
        $rental->update(['admin_notes' => 'betaald']);
        $rental->update(['status' => RentalStatus::Option]);
        $rental->update(['status' => RentalStatus::Confirmed]);

        Mail::assertSentCount(1);
    }

    public function test_confirming_a_cup_loan_sends_its_contract(): void
    {
        $loan = CupLoan::factory()->create();

        Livewire::test(ViewCupLoan::class, ['record' => $loan->getRouteKey()])->callAction('confirm');

        $this->assertSame(CupLoanStatus::Confirmed, $loan->fresh()->status);
        $this->assertSame(ContractStatus::Sent, $loan->fresh()->contract->status);
        Mail::assertSent(ContractMail::class, fn (ContractMail $mail): bool => $mail->hasTo($loan->email));
    }

    public function test_a_mail_failure_does_not_block_the_confirmation(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $rental = RentalRequest::factory()->create();

        $rental->update(['status' => RentalStatus::Confirmed]);

        $this->assertSame(RentalStatus::Confirmed, $rental->fresh()->status);
        $this->assertSame(ContractStatus::Draft, $rental->fresh()->contract->status);
    }
}
