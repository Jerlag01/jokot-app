<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Betalingen';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ! $ownerRecord->isDraft() && ! $ownerRecord->isCreditNote();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_on')
            ->columns([
                TextColumn::make('paid_on')->label('Datum')->date('d/m/Y'),
                TextColumn::make('amount')->label('Bedrag')->money('EUR'),
                TextColumn::make('method')->label('Hoe')->badge(),
                TextColumn::make('reference')->label('Referentie')->placeholder('-'),
            ])
            ->recordActions([
                DeleteAction::make()->after(fn () => $this->dispatch('$refresh')),
            ])
            ->emptyStateHeading('Nog geen betalingen')
            ->emptyStateDescription('Registreer een betaling met de knop "Betaling" bovenaan.');
    }
}
