<?php

namespace App\Support;

use App\Contracts\HasContract;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Once a reservation or cup loan is confirmed, its contract is drawn up. Depending on the settings the signing
 * link is mailed at once (only once), or it waits until someone sends it by hand via "Contract".
 */
class AutoContract
{
    public function __construct(private Contracts $contracts) {}

    public function onConfirmed(Model&HasContract $record, bool $send = true): void
    {
        $contract = $this->contracts->forRecord($record);

        if ($contract->isSigned() || $contract->sent_at !== null) {
            return;
        }

        if (! $send || ! Mailing::enabled()) {
            Notification::make()->info()->title('Contract klaargezet')
                ->body('Het contract is nog niet verstuurd. Verstuur het via "Contract" → "Contract versturen om te ondertekenen".')->send();

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
