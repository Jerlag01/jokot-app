<?php

namespace Tests\Feature;

use App\Enums\AnnualLine;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentProvider;
use App\Enums\RentalStatus;
use App\Models\AnnualEntry;
use App\Models\BarSession;
use App\Models\CupLoan;
use App\Models\Event;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use App\Models\TicketType;
use App\Models\User;
use App\Support\AnnualAccounts;
use App\Support\AnnualAccountsExport;
use App\Support\Invoicing;
use App\Support\Permissions;
use App\Support\Ticketing\TicketSales;
use Database\Seeders\AnnualAccountsImportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use ZipArchive;

class AnnualAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function actual(int $year, AnnualLine $line): float
    {
        return app(AnnualAccounts::class)->actuals($year)[$line->value]['amount'] ?? 0.0;
    }

    public function test_the_imported_workbook_figures_reproduce_its_results(): void
    {
        $this->seed(AnnualAccountsImportSeeder::class);
        $overview = app(AnnualAccounts::class)->overview([2024, 2025]);

        $this->assertSame(5826.07, $overview[2024]['actual_totals']['expenses']);
        $this->assertSame(6353.71, $overview[2024]['actual_totals']['income']);
        $this->assertSame(359.33, $overview[2024]['cumulated']);
        $this->assertSame(10177.98, $overview[2025]['actual_totals']['expenses']);
        $this->assertSame(14689.04, $overview[2025]['actual_totals']['income']);
        $this->assertSame(2956.66, $overview[2025]['actual_totals']['result']);
        $this->assertSame(3315.99, $overview[2025]['cumulated']);
        $this->assertSame(37938.74, $overview[2025]['budget_totals']['expenses']);

        $this->seed(AnnualAccountsImportSeeder::class);
        $this->assertSame(359.33, app(AnnualAccounts::class)->overview([2024])[2024]['cumulated']);
    }

    public function test_incoming_invoices_land_on_the_line_of_their_category(): void
    {
        $music = ExpenseCategory::query()->create(['name' => 'muziek', 'annual_line' => AnnualLine::MusicLight]);
        $uncategorised = ExpenseCategory::query()->create(['name' => 'zonder lijn']);
        PurchaseInvoice::factory()->create(['issue_date' => '2027-02-01', 'total' => 1122.15, 'expense_category_id' => $music->id]);
        PurchaseInvoice::factory()->create(['issue_date' => '2027-03-01', 'total' => 100, 'expense_category_id' => $music->id, 'type' => InvoiceType::CreditNote]);
        PurchaseInvoice::factory()->create(['issue_date' => '2027-03-01', 'total' => 40, 'expense_category_id' => $uncategorised->id]);
        PurchaseInvoice::factory()->create(['issue_date' => '2026-12-31', 'total' => 999, 'expense_category_id' => $music->id]);

        $this->assertSame(1022.15, $this->actual(2027, AnnualLine::MusicLight));
        $this->assertSame(40.0, $this->actual(2027, AnnualLine::OtherCost));
    }

    public function test_income_from_rentals_cups_tickets_bar_and_members(): void
    {
        Mail::fake();
        $year = (int) now()->year;

        $rental = RentalRequest::factory()->create(['status' => RentalStatus::Confirmed, 'rental_rate_id' => RentalRate::factory()->create(['price' => 150, 'deposit' => 200])->id]);
        $rental->update(['deposit_paid_at' => now()]);
        $rental->drinkCounts()->create(['product_id' => Product::factory()->create(['rental_price' => 2])->id, 'quantity_before' => 100, 'quantity_after' => 40]);
        app(Invoicing::class)->forRental($rental)->finalize();

        $loan = CupLoan::factory()->create(['rental_fee' => 15]);
        app(Invoicing::class)->forCupLoan($loan)->finalize();

        $party = TicketType::factory()->create(['price' => 10, 'event_id' => Event::factory()->create(['tickets_enabled' => true, 'kind' => 'event'])->id]);
        $quiz = TicketType::factory()->create(['price' => 3, 'event_id' => Event::factory()->create(['tickets_enabled' => true, 'kind' => 'activity'])->id]);
        $seller = User::factory()->create();
        app(TicketSales::class)->boxOffice($party, 4, PaymentProvider::Cash, $seller);
        app(TicketSales::class)->boxOffice($quiz, 2, PaymentProvider::Card, $seller);

        BarSession::factory()->create(['opening_cash' => 100, 'closing_cash' => 300, 'card_total' => 50]);
        Member::factory()->count(3)->memberIn($year, 5)->create();
        AnnualEntry::factory()->create(['year' => $year, 'line' => AnnualLine::Subsidy, 'amount' => 1500]);

        $this->assertSame(150.0, $this->actual($year, AnnualLine::RentalIncome));
        $this->assertSame(370.0, $this->actual($year, AnnualLine::DrinksIncome));
        $this->assertSame(15.0, $this->actual($year, AnnualLine::CupsIncome));
        $this->assertSame(40.0, $this->actual($year, AnnualLine::EventsIncome));
        $this->assertSame(6.0, $this->actual($year, AnnualLine::ActivitiesIncome));
        $this->assertSame(15.0, $this->actual($year, AnnualLine::MemberCards));
        $this->assertSame(1500.0, $this->actual($year, AnnualLine::Subsidy));

        $totals = app(AnnualAccounts::class)->overview([$year])[$year]['actual_totals'];
        $this->assertSame(596.0, $totals['operating_income']);
        $this->assertSame(2096.0, $totals['income']);
    }

    public function test_drafts_and_cancelled_documents_do_not_count(): void
    {
        $music = ExpenseCategory::query()->create(['name' => 'muziek', 'annual_line' => AnnualLine::MusicLight]);
        PurchaseInvoice::factory()->create(['issue_date' => '2027-02-01', 'total' => 500, 'expense_category_id' => $music->id, 'status' => InvoiceStatus::Cancelled]);
        Invoice::factory()->withLines([['Zaal', 1, 150]])->create(['issue_date' => '2027-02-01']);

        $this->assertSame([], app(AnnualAccounts::class)->actuals(2027));
    }

    public function test_exports_and_page(): void
    {
        $this->seed(AnnualAccountsImportSeeder::class);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(app(AnnualAccountsExport::class)->xlsx([2024, 2025, 2026])));
        $this->assertStringContainsString('Overzicht Jaarrekening', $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('Details 2025', $zip->getFromName('xl/workbook.xml'));
        $this->assertStringStartsWith('%PDF', app(AnnualAccountsExport::class)->pdf([2024, 2025, 2026])->output());

        $this->actingAs(User::factory()->withPermissions([Permissions::ACCOUNTS])->create());
        $this->get('/admin/annual-accounts?year=2025')->assertOk()->assertSee('Resultaat exploitatie JOC')->assertSee('3.315,99');
        $this->get('/admin/bar-sessions')->assertOk();
        $this->get('/admin/annual-entries')->assertOk();

        $this->actingAs(User::factory()->withPermissions([Permissions::INVOICES])->create());
        $this->get('/admin/annual-accounts')->assertForbidden();
    }
}
