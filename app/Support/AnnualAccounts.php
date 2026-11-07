<?php

namespace App\Support;

use App\Enums\AnnualLine;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Models\AnnualBudget;
use App\Models\AnnualEntry;
use App\Models\BarSession;
use App\Models\CupLoan;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Membership;
use App\Models\Order;
use App\Models\PurchaseInvoice;
use App\Models\RentalRequest;
use Illuminate\Support\Collection;

/**
 * The yearly accounts ("Jaarrekening"), with the same rows and totals as the board's Excel workbook.
 *
 * Where the figures come from:
 * - expenses: incoming invoices by invoice date, on the line of their cost category (credit notes negative);
 * - drinks: bar takings + drink lines of rental invoices; rentals: the other positive lines of rental invoices;
 * - cups: cup loan invoices; events and activities: paid ticket orders, by the kind of event;
 * - member cards: membership fees of the year; plus manual entries (subsidy, volunteer fees, ...).
 */
class AnnualAccounts
{
    /**
     * @var array<int, array<string, array{amount: float, items: list<array{date: ?string, source: string, description: string, amount: float, url: ?string}>}>>
     */
    private array $cache = [];

    /**
     * @return array<string, array{amount: float, items: list<array{date: ?string, source: string, description: string, amount: float, url: ?string}>}>
     */
    public function actuals(int $year): array
    {
        if (isset($this->cache[$year])) {
            return $this->cache[$year];
        }

        $lines = [];
        $add = function (AnnualLine $line, float $amount, string $source, string $description, ?string $date = null, ?string $url = null) use (&$lines): void {
            if (round($amount, 2) === 0.0) {
                return;
            }

            $lines[$line->value] ??= ['amount' => 0.0, 'items' => []];
            $lines[$line->value]['amount'] = round($lines[$line->value]['amount'] + $amount, 2);
            $lines[$line->value]['items'][] = ['date' => $date, 'source' => $source, 'description' => $description, 'amount' => round($amount, 2), 'url' => $url];
        };

        $this->purchaseInvoices($year, $add);
        $this->salesInvoices($year, $add);
        $this->ticketSales($year, $add);

        BarSession::query()->whereYear('date', $year)->orderBy('date')->get()->each(fn (BarSession $session) => $add(
            AnnualLine::DrinksIncome, (float) $session->revenue, 'Toogkassa', $session->label ?: 'Toog '.$session->date->format('d/m/Y'), $session->date->toDateString(),
        ));

        $memberships = Membership::query()->where('year', $year)->where('amount', '>', 0)->get();
        if ($memberships->isNotEmpty()) {
            $add(AnnualLine::MemberCards, (float) $memberships->sum('amount'), 'Ledenlijst', $memberships->count().' lidkaarten '.$year);
        }

        AnnualEntry::query()->where('year', $year)->orderBy('date')->get()->each(fn (AnnualEntry $entry) => $add(
            $entry->line, (float) $entry->amount, $entry->is_import ? 'Excel' : 'Manueel', $entry->description, $entry->date?->toDateString(),
        ));

        return $this->cache[$year] = $lines;
    }

    /**
     * @param  callable(AnnualLine, float, string, string, ?string, ?string): void  $add
     */
    private function purchaseInvoices(int $year, callable $add): void
    {
        PurchaseInvoice::query()
            ->with('category', 'supplier')
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->whereYear('issue_date', $year)
            ->orderBy('issue_date')
            ->get()
            ->each(function (PurchaseInvoice $invoice) use ($add): void {
                $amount = ($invoice->isCreditNote() ? -1 : 1) * (float) $invoice->total;

                $add(
                    $invoice->category?->annual_line ?? AnnualLine::OtherCost,
                    $amount,
                    'Inkomende factuur '.$invoice->reference,
                    trim($invoice->supplier->name.($invoice->description ? ' - '.$invoice->description : '')),
                    $invoice->issue_date->toDateString(),
                    route('filament.admin.resources.purchase-invoices.view', $invoice),
                );
            });
    }

    /**
     * Finalised outgoing invoices. Credit notes count negatively, on the lines of the invoice they credit.
     *
     * @param  callable(AnnualLine, float, string, string, ?string, ?string): void  $add
     */
    private function salesInvoices(int $year, callable $add): void
    {
        Invoice::query()
            ->with('lines', 'customer', 'creditedInvoice')
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->whereYear('issue_date', $year)
            ->orderBy('issue_date')
            ->get()
            ->each(function (Invoice $invoice) use ($add): void {
                $sign = $invoice->type === InvoiceType::CreditNote ? -1 : 1;
                $origin = $invoice->creditedInvoice ?? $invoice;
                $source = 'Factuur '.$invoice->number;
                $url = route('filament.admin.resources.invoices.view', $invoice);
                $date = $invoice->issue_date->toDateString();
                $vatFactor = fn (InvoiceLine $line): float => 1 + (float) $line->vat_rate / 100;

                $byLine = [];
                foreach ($invoice->lines as $line) {
                    $amount = $sign * (float) $line->line_total * $vatFactor($line);

                    $target = match (true) {
                        $origin->source_type === (new CupLoan)->getMorphClass() => AnnualLine::CupsIncome,
                        $line->product_id !== null => AnnualLine::DrinksIncome,
                        // The deposit deducted on a rental invoice was not revenue of this invoice.
                        $origin->source_type === (new RentalRequest)->getMorphClass() && (float) $line->line_total < 0 => null,
                        $origin->source_type === (new RentalRequest)->getMorphClass() => AnnualLine::RentalIncome,
                        default => AnnualLine::OtherIncome,
                    };

                    if ($target) {
                        $byLine[$target->value] = ($byLine[$target->value] ?? 0) + $amount;
                    }
                }

                foreach ($byLine as $line => $amount) {
                    $add(AnnualLine::from($line), $amount, $source, $invoice->customer->name.($invoice->subject ? ' - '.$invoice->subject : ''), $date, $url);
                }
            });
    }

    /**
     * Paid ticket orders (online and box office), by payment date, as event or activity income.
     *
     * @param  callable(AnnualLine, float, string, string, ?string, ?string): void  $add
     */
    private function ticketSales(int $year, callable $add): void
    {
        Order::query()
            ->with('event')
            ->where('status', OrderStatus::Paid)
            ->whereYear('paid_at', $year)
            ->where('total', '>', 0)
            ->get()
            ->groupBy('event_id')
            ->each(function (Collection $orders) use ($add): void {
                $event = $orders->first()->event;

                $add(
                    $event->kind === 'activity' ? AnnualLine::ActivitiesIncome : AnnualLine::EventsIncome,
                    (float) $orders->sum('total'),
                    'Tickets',
                    $event->title.' ('.$orders->count().' bestellingen)',
                    $event->starts_at->toDateString(),
                    route('filament.admin.resources.events.edit', $event),
                );
            });
    }

    /**
     * @return array<string, float>
     */
    public function budgets(int $year): array
    {
        return AnnualBudget::query()->where('year', $year)->get()->mapWithKeys(fn (AnnualBudget $budget): array => [$budget->line->value => (float) $budget->amount])->all();
    }

    /**
     * Totals of one column (budget or actuals) in the workbook's structure.
     *
     * @param  array<string, float>  $amounts
     * @return array<string, float>
     */
    public function totals(array $amounts): array
    {
        $sum = fn (array $lines): float => round(array_sum(array_map(fn (AnnualLine $line): float => $amounts[$line->value] ?? 0, $lines)), 2);

        $management = $sum(AnnualLine::management());
        $operating = $sum(AnnualLine::operatingCosts());
        $income = $sum(AnnualLine::operatingIncome());
        $subsidy = $sum([AnnualLine::Subsidy]);
        $investments = $sum([AnnualLine::Investments]);
        $operatingResult = round($income + $subsidy - $management - $operating, 2);

        return [
            'management' => $management,
            'operating_costs' => $operating,
            'expenses' => round($management + $operating, 2),
            'operating_income' => $income,
            'subsidy' => $subsidy,
            'income' => round($income + $subsidy, 2),
            'operating_result' => $operatingResult,
            'investments' => $investments,
            'result' => round($operatingResult - $investments, 2),
        ];
    }

    /**
     * The overview for a range of years: per year a budget and an actuals column, with the cumulated result.
     *
     * @param  list<int>  $years
     * @return array<int, array{budget: array<string, float>, actual: array<string, float>, budget_totals: array<string, float>, actual_totals: array<string, float>, carried: float, cumulated: float, budget_cumulated: float}>
     */
    public function overview(array $years): array
    {
        sort($years);
        $carried = $this->cumulatedBefore($years[0]);
        $overview = [];

        foreach ($years as $year) {
            $actual = array_map(fn (array $line): float => $line['amount'], $this->actuals($year));
            $budget = $this->budgets($year);
            $actualTotals = $this->totals($actual);
            $budgetTotals = $this->totals($budget);

            $overview[$year] = [
                'budget' => $budget,
                'actual' => $actual,
                'budget_totals' => $budgetTotals,
                'actual_totals' => $actualTotals,
                'carried' => $carried,
                'cumulated' => round($carried + $actualTotals['result'], 2),
                'budget_cumulated' => round($carried + $budgetTotals['result'], 2),
            ];

            $carried = $overview[$year]['cumulated'];
        }

        return $overview;
    }

    /**
     * The cumulated result of all years before $year, from the first year with any figures.
     */
    public function cumulatedBefore(int $year): float
    {
        $first = $this->firstYear();
        $total = 0.0;

        for ($y = $first; $y < $year; $y++) {
            $total += $this->totals(array_map(fn (array $line): float => $line['amount'], $this->actuals($y)))['result'];
        }

        return round($total, 2);
    }

    public function firstYear(): int
    {
        $candidates = array_filter([
            AnnualEntry::query()->min('year'),
            PurchaseInvoice::query()->min('issue_date') ? (int) substr((string) PurchaseInvoice::query()->min('issue_date'), 0, 4) : null,
            Invoice::query()->whereNotNull('number')->min('issue_date') ? (int) substr((string) Invoice::query()->whereNotNull('number')->min('issue_date'), 0, 4) : null,
            BarSession::query()->min('date') ? (int) substr((string) BarSession::query()->min('date'), 0, 4) : null,
            Membership::query()->min('year'),
            Order::query()->where('status', OrderStatus::Paid)->min('paid_at') ? (int) substr((string) Order::query()->where('status', OrderStatus::Paid)->min('paid_at'), 0, 4) : null,
        ]);

        return $candidates === [] ? (int) now()->year : (int) min($candidates);
    }

    /**
     * The workbook's rows, in order, for rendering and export.
     *
     * @return list<array{type: string, label: string, key?: string}>
     */
    public static function layout(): array
    {
        $line = fn (AnnualLine $line): array => ['type' => 'line', 'label' => $line->getLabel(), 'key' => $line->value];

        return [
            ['type' => 'heading', 'label' => '1. EXPLOITATIE JOC'],
            ['type' => 'section', 'label' => '1.1 Uitgaven exploitatie beheer JOC & Jokot'],
            ...array_map($line, AnnualLine::management()),
            ['type' => 'subtotal', 'label' => 'Subtotaal exploitatie beheer JOC', 'key' => 'management'],
            ...array_map($line, AnnualLine::operatingCosts()),
            ['type' => 'subtotal', 'label' => 'Subtotaal werkingskosten Jokot', 'key' => 'operating_costs'],
            ['type' => 'total', 'label' => 'Totaal uitgaven exploitatie beheer JOC & Jokot', 'key' => 'expenses'],
            ['type' => 'section', 'label' => '1.2 Ontvangsten exploitatie JOC'],
            ...array_map($line, AnnualLine::operatingIncome()),
            ['type' => 'subtotal', 'label' => 'Subtotaal ontvangsten werking Jokot', 'key' => 'operating_income'],
            $line(AnnualLine::Subsidy),
            ['type' => 'subtotal', 'label' => 'Subtotaal subsidie', 'key' => 'subsidy'],
            ['type' => 'total', 'label' => 'Totaal ontvangsten exploitatie JOC', 'key' => 'income'],
            ['type' => 'result', 'label' => 'Resultaat exploitatie JOC', 'key' => 'operating_result'],
            ['type' => 'heading', 'label' => '2. INVESTERINGEN JOC'],
            $line(AnnualLine::Investments),
            ['type' => 'total', 'label' => 'Totaal investeringen JOC', 'key' => 'investments'],
            ['type' => 'result', 'label' => 'Resultaat exploitatie & investeringen JOC', 'key' => 'result'],
            ['type' => 'carried', 'label' => 'Gecumuleerd budgettair resultaat vorig boekjaar', 'key' => 'carried'],
            ['type' => 'cumulated', 'label' => 'Gecumuleerd budgettair resultaat', 'key' => 'cumulated'],
        ];
    }
}
