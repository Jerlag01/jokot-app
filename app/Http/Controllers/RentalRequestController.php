<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRentalRequest;
use App\Mail\RentalRequestReceived;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\Mailing;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RentalRequestController extends Controller
{
    public function store(StoreRentalRequest $request): RedirectResponse
    {
        $rental = DB::transaction(function () use ($request): RentalRequest {
            $rental = RentalRequest::query()->create([
                ...$request->safe()->except(['drinks', 'terms', 'website']),
                'terms_accepted_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            $rental->drinks()->createMany($request->drinkLines());

            return $rental;
        });

        $this->notifyBoard($rental);

        return back()
            ->withFragment('huuraanvraag')
            ->with('rental_request_reference', $rental->reference);
    }

    /**
     * A bell notification for every active board member, plus mails. A mail failure must not lose the request.
     */
    private function notifyBoard(RentalRequest $rental): void
    {
        Notification::make()
            ->title("Nieuwe huuraanvraag {$rental->reference}")
            ->body($rental->fullName().' - '.$rental->event_date->format('d/m/Y'))
            ->icon('heroicon-o-calendar-days')
            ->actions([Action::make('open')->label('Openen')->url('/admin/rental-requests/'.$rental->id)])
            ->sendToDatabase(User::query()->where('is_active', true)->get());

        try {
            if (filled($boardAddress = settings()->get('notification_email')) && Mailing::allows('rental_board')) {
                Mail::to($boardAddress)->send(new RentalRequestReceived($rental, forBoard: true));
            }

            if (Mailing::allows('rental_requester')) {
                Mail::to($rental->email)->send(new RentalRequestReceived($rental));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
