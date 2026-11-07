<?php

namespace App\Filament\Resources\PurchaseInvoices\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Betalingen';

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
                TextColumn::make('reference')->label('Opmerking')->placeholder('-'),
            ])
            ->recordActions([DeleteAction::make()])
            ->emptyStateHeading('Nog niet betaald')
            ->emptyStateDescription('Registreer een betaling met de knop "Betaald" bovenaan.');
    }
}
