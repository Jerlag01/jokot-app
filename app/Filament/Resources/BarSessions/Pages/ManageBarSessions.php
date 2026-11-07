<?php

namespace App\Filament\Resources\BarSessions\Pages;

use App\Filament\Resources\BarSessions\BarSessionResource;
use App\Support\PosReportImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

class ManageBarSessions extends ManageRecords
{
    protected static string $resource = BarSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPosReport')
                ->label('Kassa-rapport importeren')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('Dagrapport van de kassa importeren')
                ->modalDescription('Kies het .json-bestand uit de dagrapport-mail van de kassa (POS). Een rapport dat al eerder is ingelezen wordt niet dubbel toegevoegd.')
                ->modalSubmitActionLabel('Importeren')
                ->schema([
                    FileUpload::make('report')->label('Dagrapport (.json)')->required()->storeFiles(false)->acceptedFileTypes(['application/json', 'text/plain', '.json']),
                ])
                ->action(function (array $data): void {
                    try {
                        [$session, $created] = app(PosReportImporter::class)->import($data['report']->get(), auth()->id());
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    $created
                        ? Notification::make()->title('Kassa-rapport van '.$session->date->format('d/m/Y').' geïmporteerd')->success()->send()
                        : Notification::make()->title('Dit rapport was al geïmporteerd')->warning()->send();
                }),
            CreateAction::make()->label('Kassa afsluiten')->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
        ];
    }
}
