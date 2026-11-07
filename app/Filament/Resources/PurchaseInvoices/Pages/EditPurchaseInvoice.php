<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    private ?string $previousFile = null;

    public function getTitle(): string
    {
        return 'Inkomende factuur '.$this->getRecord()->reference;
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->payments()->exists() || $this->getRecord()->stockMovements()->exists())];
    }

    protected function beforeSave(): void
    {
        $this->previousFile = $this->getRecord()->getOriginal('file_path');
    }

    /**
     * A replaced scan is removed from the disk.
     */
    protected function afterSave(): void
    {
        if ($this->previousFile && $this->previousFile !== $this->getRecord()->file_path) {
            Storage::disk('local')->delete($this->previousFile);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
