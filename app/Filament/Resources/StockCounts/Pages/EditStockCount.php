<?php

namespace App\Filament\Resources\StockCounts\Pages;

use App\Filament\Resources\StockCounts\StockCountResource;
use App\Support\Stock;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStockCount extends EditRecord
{
    protected static string $resource = StockCountResource::class;

    public function getTitle(): string
    {
        return 'Telling '.$this->getRecord()->counted_on->format('d/m/Y').($this->getRecord()->isFinalized() ? ' (afgesloten)' : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label('Telling afsluiten')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('De verschillen tussen het getelde aantal en de huidige stock worden geboekt. Dit kan niet ongedaan gemaakt worden.')
                ->hidden(fn (): bool => $this->getRecord()->isFinalized())
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $corrections = app(Stock::class)->finalizeCount($this->getRecord());
                    Notification::make()->success()->title('Telling afgesloten')->body($corrections === 0 ? 'Alles klopte.' : "{$corrections} producten gecorrigeerd.")->send();
                    $this->redirect(StockCountResource::getUrl('index'));
                }),
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->getRecord()->isFinalized() ? [] : parent::getFormActions();
    }
}
