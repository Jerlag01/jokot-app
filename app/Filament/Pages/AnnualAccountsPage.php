<?php

namespace App\Filament\Pages;

use App\Enums\AnnualLine;
use App\Enums\NavigationGroup;
use App\Models\AnnualBudget;
use App\Support\AnnualAccounts;
use App\Support\AnnualAccountsExport;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * The yearly accounts in the layout of the board's Excel workbook, for three years side by side.
 */
class AnnualAccountsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Jaarrekening';

    protected static ?string $slug = 'annual-accounts';

    protected string $view = 'filament.pages.annual-accounts';

    #[Url]
    public ?int $year = null;

    public ?string $openLine = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::ACCOUNTS);
    }

    public function getTitle(): string
    {
        return 'Jaarrekening '.$this->selectedYear();
    }

    public function selectedYear(): int
    {
        return $this->year ?? (int) now()->year;
    }

    /**
     * @return list<int>
     */
    public function years(): array
    {
        $year = $this->selectedYear();

        return [$year - 1, $year, $year + 1];
    }

    public function toggleLine(string $line): void
    {
        $this->openLine = $this->openLine === $line ? null : $line;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previous')->label('')->icon(Heroicon::ChevronLeft)->color('gray')->tooltip('Vorig jaar')
                ->action(fn () => $this->year = $this->selectedYear() - 1),
            Action::make('next')->label('')->icon(Heroicon::ChevronRight)->color('gray')->tooltip('Volgend jaar')
                ->action(fn () => $this->year = $this->selectedYear() + 1),
            Action::make('budget')
                ->label(fn (): string => 'Budget '.$this->selectedYear())
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->modalHeading(fn (): string => 'Budget/schatting '.$this->selectedYear())
                ->modalWidth('4xl')
                ->fillForm(fn (): array => ['lines' => app(AnnualAccounts::class)->budgets($this->selectedYear())])
                ->schema(fn (): array => [
                    Section::make('Uitgaven')->schema([Grid::make(3)->schema(array_map(fn (AnnualLine $line) => TextInput::make('lines.'.$line->value)->label($line->getLabel())->numeric()->prefix('€'), [...AnnualLine::management(), ...AnnualLine::operatingCosts()]))]),
                    Section::make('Ontvangsten')->schema([Grid::make(3)->schema(array_map(fn (AnnualLine $line) => TextInput::make('lines.'.$line->value)->label($line->getLabel())->numeric()->prefix('€'), [...AnnualLine::operatingIncome(), AnnualLine::Subsidy]))]),
                    Section::make('Investeringen')->schema([TextInput::make('lines.'.AnnualLine::Investments->value)->label('Investeringen')->numeric()->prefix('€')]),
                ])
                ->action(function (array $data): void {
                    foreach ($data['lines'] ?? [] as $line => $amount) {
                        AnnualBudget::query()->updateOrCreate(['year' => $this->selectedYear(), 'line' => $line], ['amount' => (float) $amount]);
                    }

                    Notification::make()->success()->title('Budget opgeslagen')->send();
                }),
            Action::make('excel')
                ->label('Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('success')
                ->action(fn (): BinaryFileResponse => response()
                    ->download(app(AnnualAccountsExport::class)->xlsx($this->years()), 'jaarrekening-'.$this->selectedYear().'.xlsx')
                    ->deleteFileAfterSend()),
            Action::make('pdf')
                ->label('PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function () {
                    $pdf = app(AnnualAccountsExport::class)->pdf($this->years())->output();

                    return response()->streamDownload(fn () => print ($pdf), 'jaarrekening-'.$this->selectedYear().'.pdf', ['Content-Type' => 'application/pdf']);
                }),
        ];
    }
}
