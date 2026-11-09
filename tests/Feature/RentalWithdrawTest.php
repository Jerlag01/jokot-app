<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Filament\Resources\RentalRequests\Pages\ViewRentalRequest;
use App\Models\Contract;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\Availability;
use App\Support\Contracts;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RentalWithdrawTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JokotModulesSeeder::class);
        Mail::fake();
        Storage::fake('local');
        $this->actingAs($this->admin = User::factory()->admin()->create());
    }

    private User $admin;

    public function test_withdrawing_removes_the_contract_keeps_the_request_and_frees_the_date(): void
    {
        $date = now()->addDays(60)->startOfDay();
        $rental = RentalRequest::factory()->create(['event_date' => $date, 'status' => RentalStatus::Confirmed]);
        $contracts = app(Contracts::class);
        $contract = $contracts->forRecord($rental);
        ob_start();
        imagepng(imagecreatetruecolor(20, 10));
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        $contracts->sign($contract, 'An Peeters', $png, '127.0.0.1');
        $files = [$contract->fresh()->signature_path, $contract->fresh()->pdf_path];
        Storage::disk('local')->assertExists($files);
        $this->assertFalse(app(Availability::class)->isAvailable($date));

        Livewire::test(ViewRentalRequest::class, ['record' => $rental->getRouteKey()])
            ->callAction('withdraw', data: ['reason' => 'Feest gaat niet door wegens ziekte.'])
            ->assertHasNoActionErrors();

        $rental->refresh();
        $this->assertSame(RentalStatus::Withdrawn, $rental->status);
        $this->assertSame('Feest gaat niet door wegens ziekte.', $rental->cancellation_reason);
        $this->assertNotNull($rental->cancelled_at);
        $this->assertSame($this->admin->id, $rental->cancelled_by);
        $this->assertSame(0, Contract::query()->count());
        Storage::disk('local')->assertMissing($files);

        $this->assertTrue(app(Availability::class)->isAvailable($date));
        $this->post(route('rental-requests.store'), [
            'event_date' => $date->toDateString(),
            'rental_rate_id' => RentalRate::factory()->create()->id,
            'first_name' => 'Jan', 'last_name' => 'Smet', 'email' => 'jan@example.com', 'phone' => '0470 11 22 33',
            'street' => 'Kerkstraat', 'house_number' => '1', 'postal_code' => '2845', 'city' => 'Niel',
            'expected_guests' => 50, 'brings_own_wine' => '0', 'terms' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, RentalRequest::query()->count());
    }

    public function test_a_reason_is_required_and_closed_requests_cannot_be_withdrawn(): void
    {
        $rental = RentalRequest::factory()->create(['status' => RentalStatus::Option]);

        Livewire::test(ViewRentalRequest::class, ['record' => $rental->getRouteKey()])
            ->callAction('withdraw', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        $this->assertSame(RentalStatus::Option, $rental->fresh()->status);

        $rejected = RentalRequest::factory()->create(['status' => RentalStatus::Rejected]);
        Livewire::test(ViewRentalRequest::class, ['record' => $rejected->getRouteKey()])->assertActionHidden('withdraw');
    }
}
