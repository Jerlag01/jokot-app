<?php

namespace App\Support;

use App\Contracts\HasContract;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Once a reservation or cup loan is confirmed, its contract is drawn up and the signing link is mailed
 * to the requester (once; resending stays possible by hand).
 */
class AutoContract
{
    public function __construct(private Contracts $contracts) {}

    public function onConfirmed(Model&HasContract $record): void
    {
        $contract = $this->contracts->forRecord($record);

        if ($contract->isSigned() || $contract->sent_at !== null) {
            return;
        }

        try {
            $this->contracts->send($contract);
            Notification::make()->success()->title('Contract verstuurd')->body('De ondertekenlink ging naar '.$record->contractSignerEmail().'.')->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->warning()->persistent()->title('Contract aangemaakt, maar niet verstuurd')
                ->body('Controleer de mailinstellingen en verstuur het opnieuw via "Contract". ('.$exception->getMessage().')')->send();
        }
    }
}
