<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\CupLoans\CupLoanResource;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Models\Contract;
use App\Models\CupLoan;
use App\Models\RentalRequest;
use App\Support\Contracts;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use UnitEnum;
use ZipArchive;

/**
 * All generated contracts in one place, per category (hall rental, cups): status, signed PDF, resend.
 */
class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'contract';

    protected static ?string $pluralModelLabel = 'contracten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 2;

    /**
     * The contract types the user may see: hall rentals need the rentals right, cups the cup loans right.
     *
     * @return list<ContractType>
     */
    public static function allowedTypes(): array
    {
        $user = auth()->user();

        return array_values(array_filter([
            $user?->hasPermission(Permissions::RENTALS) ? ContractType::Rental : null,
            $user?->hasPermission(Permissions::CUP_LOANS) ? ContractType::CupLoan : null,
        ]));
    }

    public static function canViewAny(): bool
    {
        return static::allowedTypes() !== [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('type', static::allowedTypes())->with('contractable');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getEloquentQuery()->where('status', ContractStatus::Sent)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Wacht op handtekening';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('type')->label('Categorie')->badge(),
                TextColumn::make('contractable.reference')->label('Referentie')->searchable(query: fn (Builder $query, string $search) => $query->whereHasMorph('contractable', [RentalRequest::class, CupLoan::class], fn (Builder $query) => $query->where('reference', 'like', "%{$search}%"))),
                TextColumn::make('party')->label('Huurder')->state(fn (Contract $record): string => $record->contractable instanceof RentalRequest
                    ? ($record->contractable->organisation ?: $record->contractable->fullName())
                    : $record->contractable->organisation),
                TextColumn::make('when')->label('Datum event / periode')->state(fn (Contract $record): string => $record->contractable instanceof RentalRequest
                    ? $record->contractable->event_date->format('d/m/Y')
                    : $record->contractable->starts_on->format('d/m').' - '.$record->contractable->ends_on->format('d/m/Y')),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('sent_at')->label('Verstuurd')->dateTime('d/m/Y H:i')->placeholder('-')->sortable(),
                TextColumn::make('signed_at')->label('Ondertekend')->dateTime('d/m/Y H:i')->placeholder('-')->sortable()
                    ->description(fn (Contract $record): ?string => $record->signer_name),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ContractStatus::class)->multiple(),
            ])
            ->recordUrl(fn (Contract $record): string => self::recordUrl($record))
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                    ->action(function (Contract $record) {
                        $contracts = app(Contracts::class);
                        $pdf = $contracts->pdfContents($record);

                        return response()->streamDownload(fn () => print ($pdf), $contracts->filename($record), ['Content-Type' => 'application/pdf']);
                    }),
                Action::make('resend')->label('Opnieuw versturen')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                    ->visible(fn (Contract $record): bool => ! $record->isSigned())
                    ->requiresConfirmation()
                    ->action(function (Contract $record): void {
                        try {
                            app(Contracts::class)->send($record);
                            Notification::make()->success()->title('Verstuurd naar '.$record->contractable->contractSignerEmail())->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Versturen mislukt')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('open')->label('Ondertekenpagina')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Contract $record): string => $record->signUrl(), shouldOpenInNewTab: true),
            ])
            ->toolbarActions([
                BulkAction::make('zip')->label('Exporteren als PDF (zip)')->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->action(fn (Collection $records): BinaryFileResponse => self::zip($records)),
            ]);
    }

    public static function recordUrl(Contract $record): string
    {
        return $record->contractable instanceof RentalRequest
            ? RentalRequestResource::getUrl('view', ['record' => $record->contractable])
            : CupLoanResource::getUrl('view', ['record' => $record->contractable]);
    }

    /**
     * @param  Collection<int, Contract>  $records
     */
    public static function zip(Collection $records): BinaryFileResponse
    {
        $contracts = app(Contracts::class);
        $path = tempnam(sys_get_temp_dir(), 'contracten').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($records as $contract) {
            $zip->addFromString($contracts->filename($contract), $contracts->pdfContents($contract));
        }

        $zip->close();

        return response()->download($path, 'contracten-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend();
    }

    public static function getPages(): array
    {
        return ['index' => ListContracts::route('/')];
    }
}
