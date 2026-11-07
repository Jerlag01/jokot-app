<?php

namespace App\Filament\Actions;

use App\Contracts\HasContract;
use App\Support\Contracts;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Contract actions for a rental request or cup loan: send for online signing, open, download, reset.
 */
class ContractActions
{
    /**
     * @param  (Closure(Model&HasContract): bool)|null  $when  extra condition for every action (e.g. only for confirmed records)
     */
    public static function group(?Closure $when = null): ActionGroup
    {
        $contracts = fn (): Contracts => app(Contracts::class);
        $allowed = fn (?Model $record): bool => $record !== null && ($when === null || $when($record));

        return ActionGroup::make([
            Action::make('contractSend')
                ->label(fn (Model&HasContract $record): string => $record->contract?->sent_at ? 'Contract opnieuw versturen' : 'Contract versturen om te ondertekenen')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription(fn (Model&HasContract $record): string => 'De huurder krijgt een mail op '.$record->contractSignerEmail().' met een link om het contract online te lezen en te ondertekenen.')
                ->visible(fn (?Model $record): bool => $allowed($record) && ! $record->contract?->isSigned())
                ->action(function (Model&HasContract $record) use ($contracts): void {
                    try {
                        $contracts()->send($contracts()->forRecord($record));
                        Notification::make()->success()->title('Contract verstuurd naar '.$record->contractSignerEmail())->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Versturen mislukt')->body($exception->getMessage())->persistent()->send();
                    }
                }),
            Action::make('contractOpen')
                ->label('Contract bekijken (ondertekenpagina)')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->visible(fn (?Model $record): bool => $allowed($record))
                ->url(fn (Model&HasContract $record): string => $contracts()->forRecord($record)->signUrl(), shouldOpenInNewTab: true),
            Action::make('contractPdf')
                ->label(fn (Model&HasContract $record): string => $record->contract?->isSigned() ? 'Ondertekend contract (PDF)' : 'Contract als PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (?Model $record): bool => $allowed($record))
                ->action(function (Model&HasContract $record) use ($contracts) {
                    $contract = $contracts()->forRecord($record);
                    $pdf = $contracts()->pdfContents($contract);

                    return response()->streamDownload(fn () => print ($pdf), $contracts()->filename($contract), ['Content-Type' => 'application/pdf']);
                }),
            Action::make('contractReset')
                ->label('Handtekening intrekken')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('De handtekening en het ondertekende PDF worden verwijderd, zodat je het contract kan aanpassen en opnieuw laten ondertekenen.')
                ->visible(fn (?Model $record): bool => $allowed($record) && (bool) $record->contract?->isSigned())
                ->action(function (Model&HasContract $record) use ($contracts): void {
                    $contracts()->reset($record->contract);
                    Notification::make()->success()->title('Handtekening ingetrokken')->send();
                }),
        ])
            ->label('Contract')
            ->icon(Heroicon::OutlinedDocumentText)
            ->button()
            ->color('gray');
    }
}
