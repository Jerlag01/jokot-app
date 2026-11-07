<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Filament\Pages\Settings as SettingsPage;
use App\Filament\Resources\RentalRequests\Pages\ViewRentalRequest;
use App\Models\ContractTemplate;
use App\Models\CupLoan;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Page;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\Stock;
use Database\Seeders\JokotContentSeeder;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_inactive_users_cannot_enter(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]))->get('/admin')->assertForbidden();
    }

    public function test_every_admin_screen_loads(): void
    {
        $this->seed([JokotContentSeeder::class, JokotModulesSeeder::class]);
        $rental = RentalRequest::factory()->create();
        $invoice = Invoice::factory()->create();
        $cupLoan = CupLoan::factory()->create();
        $order = Order::factory()->create();
        $count = app(Stock::class)->startCount();
        $template = ContractTemplate::query()->first();
        $event = Event::factory()->create();
        $page = Page::query()->where('slug', 'verhuren')->sole();

        $this->actingAs(User::factory()->admin()->create());

        foreach ([
            '/admin', '/admin/calendar', '/admin/settings',
            '/admin/rental-requests', '/admin/rental-requests/create', "/admin/rental-requests/{$rental->id}", "/admin/rental-requests/{$rental->id}/edit",
            '/admin/events', '/admin/events/create', "/admin/events/{$event->id}/edit",
            '/admin/pages', '/admin/pages/create', "/admin/pages/{$page->id}/edit",
            '/admin/rental-rates', '/admin/rental-extras', '/admin/opening-hours', '/admin/users', '/admin/roles',
            '/admin/products', '/admin/stock-movements', '/admin/stock-counts', "/admin/stock-counts/{$count->id}/edit",
            '/admin/customers', '/admin/invoices', '/admin/invoices/create', "/admin/invoices/{$invoice->id}", "/admin/invoices/{$invoice->id}/edit",
            '/admin/cup-loans', '/admin/cup-loans/create', "/admin/cup-loans/{$cupLoan->id}", "/admin/cup-loans/{$cupLoan->id}/edit",
            '/admin/contract-templates', "/admin/contract-templates/{$template->id}/edit",
            '/admin/orders', "/admin/orders/{$order->id}", '/admin/checkin',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_confirming_a_request_blocks_the_date_and_a_second_confirmation_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $date = now()->addDays(60)->toDateString();
        $first = RentalRequest::factory()->create(['event_date' => $date]);
        $second = RentalRequest::factory()->create(['event_date' => $date]);

        Livewire::test(ViewRentalRequest::class, ['record' => $first->getRouteKey()])->callAction('confirm');
        Livewire::test(ViewRentalRequest::class, ['record' => $second->getRouteKey()])->callAction('confirm');

        $this->assertSame(RentalStatus::Confirmed, $first->fresh()->status);
        $this->assertSame(RentalStatus::New, $second->fresh()->status);
    }

    public function test_settings_are_saved(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SettingsPage::class)
            ->fillForm(['email' => 'bestuur@jokot.be', 'rental_min_days_ahead' => 14])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('bestuur@jokot.be', settings()->get('email'));
        $this->assertSame(14, settings()->get('rental_min_days_ahead'));
    }
}
