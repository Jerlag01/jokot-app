<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return ($this->getRecord()->isCreditNote() ? 'Creditnota ' : 'Factuur ').$this->getRecord()->displayNumber();
    }

    protected function getHeaderActions(): array
    {
        return [...InvoiceResource::lifecycleActions(), EditAction::make()];
    }
}
