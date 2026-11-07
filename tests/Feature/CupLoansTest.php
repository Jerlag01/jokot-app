<?php

namespace Tests\Feature;

use App\Enums\CupLoanStatus;
use App\Filament\Resources\CupLoans\Pages\ViewCupLoan;
use App\Models\CupLoan;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\JokotContentSeeder;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CupLoansTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'organisation' => 'Chiro Niel', 'representative' => 'An Peeters', 'email' => 'an@example.com', 'phone' => '0470 11 22 33',
            'street' => 'Kerkstraat', 'house_number' => '1', 'postal_code' => '2845', 'city' => 'Niel',
            'starts_on' => now()->addDays(10)->toDateString(), 'ends_on' => now()->addDays(12)->toDateString(),
            'cups' => 300, 'terms' => '1', ...$overrides,
        ];
    }

    public function test_a_public_request_is_stored_with_the_current_fees(): void
    {
        User::factory()->admin()->create();

        $this->from('/wisselbekers')->post(route('cup-loans.store'), $this->payload())
            ->assertRedirect('/wisselbekers#wisselbekers')
            ->assertSessionHas('cup_loan_reference', 'WB-'.now()->year.'-001');

        $loan = CupLoan::query()->sole();
        $this->assertSame(CupLoanStatus::Requested, $loan->status);
        $this->assertSame('15.00', $loan->rental_fee);
        $this->assertSame('0.25', $loan->cup_fee);
    }

    public function test_requests_beyond_the_cups_in_stock_are_refused(): void
    {
        app(Settings::class)->save(['cups_total' => 500]);
        CupLoan::factory()->create(['status' => CupLoanStatus::Confirmed, 'cups' => 300, 'starts_on' => now()->addDays(9)->toDateString(), 'ends_on' => now()->addDays(11)->toDateString()]);

        $this->post(route('cup-loans.store'), $this->payload())->assertSessionHasErrors('cups');
        $this->post(route('cup-loans.store'), $this->payload(['cups' => 200]))->assertSessionHasNoErrors();
    }

    public function test_the_public_page_shows_the_form(): void
    {
        $this->seed([JokotContentSeeder::class, JokotModulesSeeder::class]);

        $this->get('/wisselbekers')->assertOk()->assertSee('Wisselbekers aanvragen')->assertSee('name="cups"', false);
    }

    public function test_confirm_pick_up_and_return_from_the_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $loan = CupLoan::factory()->create(['cups' => 200, 'crates' => 4]);

        Livewire::test(ViewCupLoan::class, ['record' => $loan->getRouteKey()])
            ->callAction('confirm')
            ->callAction('pickUp', data: ['cups' => 200, 'crates' => 4])
            ->callAction('return', data: ['cups_returned' => 195, 'cups_dirty' => 3, 'crates_returned' => 4, 'crates_damaged' => 0]);

        $loan->refresh();
        $this->assertSame(CupLoanStatus::Returned, $loan->status);
        $this->assertSame(8, $loan->cupsCharged());
        $this->assertNotNull($loan->picked_up_at);
    }

    public function test_confirming_is_refused_when_not_enough_cups_are_free(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app(Settings::class)->save(['cups_total' => 250]);
        $other = CupLoan::factory()->create(['status' => CupLoanStatus::PickedUp, 'cups' => 100]);
        $loan = CupLoan::factory()->create(['cups' => 200, 'starts_on' => $other->starts_on, 'ends_on' => $other->ends_on]);

        Livewire::test(ViewCupLoan::class, ['record' => $loan->getRouteKey()])->callAction('confirm')->assertNotified('Niet genoeg materiaal');

        $this->assertSame(CupLoanStatus::Requested, $loan->fresh()->status);
    }
}
