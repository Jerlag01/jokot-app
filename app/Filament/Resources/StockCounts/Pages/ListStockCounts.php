<?php

namespace App\Filament\Resources\StockCounts\Pages;

use App\Filament\Resources\StockCounts\StockCountResource;
use App\Support\Stock;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStockCounts extends ListRecords
{
    protected static string $resource = StockCountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label('Nieuwe telling')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([TextInput::make('note')->label('Opmerking')->placeholder('bv. maandtelling, voor verhuur ...')])
                ->action(fn (array $data) => $this->redirect(StockCountResource::getUrl('edit', ['record' => app(Stock::class)->startCount($data['note'] ?? null)]))),
        ];
    }
}
