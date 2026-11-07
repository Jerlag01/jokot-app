<?php

namespace App\Filament\Widgets;

use App\Enums\RentalStatus;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Models\RentalRequest;
use App\Support\Permissions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingRentals extends TableWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyPermission(Permissions::RENTALS);
    }

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Komende verhuren en open aanvragen';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => RentalRequest::query()
                ->whereIn('status', [RentalStatus::New, RentalStatus::Option, RentalStatus::Confirmed])
                ->whereDate('event_date', '>=', today())
                ->orderBy('event_date'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (RentalRequest $record): string => RentalRequestResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('event_date')->label('Datum')->date('D d/m/Y'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('name')->label('Aanvrager')
                    ->state(fn (RentalRequest $record): string => $record->fullName())
                    ->description(fn (RentalRequest $record): ?string => $record->organisation),
                TextColumn::make('expected_guests')->label('Volk'),
                TextColumn::make('reference')->label('Ref.')->color('gray'),
            ])
            ->emptyStateHeading('Geen komende verhuren');
    }
}
