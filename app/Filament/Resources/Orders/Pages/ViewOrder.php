<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Bestelling '.$this->getRecord()->number;
    }

    protected function getHeaderActions(): array
    {
        return OrderResource::orderActions();
    }
}
