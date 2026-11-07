<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->fullName().' ('.$this->getRecord()->member_number.')';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selfService')->label('Pagina van het lid')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (): string => $this->getRecord()->selfServiceUrl(), shouldOpenInNewTab: true),
            Action::make('newQr')->label('Nieuwe QR-code')->icon(Heroicon::OutlinedQrCode)->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Bv. bij een verloren kaart: de QR-code op eerder afgedrukte kaarten werkt dan niet meer. Druk daarna een nieuwe kaart af.')
                ->action(function (): void {
                    $this->getRecord()->regenerateToken();
                    Notification::make()->success()->title('Nieuwe QR-code aangemaakt')->send();
                }),
            DeleteAction::make(),
        ];
    }
}
