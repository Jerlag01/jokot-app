<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function getTitle(): string
    {
        return 'Inkomende factuur '.$this->getRecord()->reference;
    }

    protected function getHeaderActions(): array
    {
        return [...PurchaseInvoiceResource::invoiceActions(), EditAction::make()];
    }
}
