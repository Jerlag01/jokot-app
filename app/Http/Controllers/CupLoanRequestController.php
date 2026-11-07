<?php

namespace App\Http\Controllers;

use App\Models\CupLoan;
use App\Models\User;
use App\Support\CupInventory;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CupLoanRequestController extends Controller
{
    public function store(Request $request, CupInventory $inventory): RedirectResponse
    {
        abort_unless((bool) settings()->get('cup_loans_open', true), 403);

        $data = $request->validate([
            'organisation' => ['required', 'string', 'max:150'],
            'representative' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'street' => ['required', 'string', 'max:150'],
            'house_number' => ['required', 'string', 'max:20'],
            'box' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'event_name' => ['nullable', 'string', 'max:150'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'cups' => ['required', 'integer', 'min:1', 'max:20000'],
            'crates' => ['nullable', 'integer', 'min:0', 'max:500'],
            'details' => ['nullable', 'string', 'max:3000'],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ], [
            'terms.accepted' => 'Je moet akkoord gaan met de voorwaarden.',
        ], [
            'organisation' => 'organisatie', 'representative' => 'vertegenwoordiger', 'phone' => 'telefoon',
            'street' => 'straat', 'house_number' => 'huisnummer', 'postal_code' => 'postcode', 'city' => 'gemeente',
            'starts_on' => 'ophaaldatum', 'ends_on' => 'terugbrengdatum', 'cups' => 'aantal bekers', 'crates' => 'aantal bakken',
        ]);

        $problem = $inventory->problem(CarbonImmutable::parse($data['starts_on']), CarbonImmutable::parse($data['ends_on']), (int) $data['cups'], (int) ($data['crates'] ?? 0));

        if ($problem) {
            return back()->withInput()->withErrors(['cups' => $problem])->withFragment('wisselbekers');
        }

        $loan = CupLoan::query()->create([
            ...collect($data)->except(['terms', 'website'])->all(),
            'crates' => (int) ($data['crates'] ?? 0),
            'terms_accepted_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        Notification::make()
            ->title("Nieuwe aanvraag wisselbekers {$loan->reference}")
            ->body("{$loan->organisation} - {$loan->cups} bekers, {$loan->starts_on->format('d/m')} tot {$loan->ends_on->format('d/m')}")
            ->icon('heroicon-o-beaker')
            ->actions([Action::make('open')->label('Openen')->url('/admin/cup-loans/'.$loan->id)])
            ->sendToDatabase(User::query()->where('is_active', true)->get()->filter(fn (User $user): bool => $user->hasPermission(Permissions::CUP_LOANS)));

        return back()->withFragment('wisselbekers')->with('cup_loan_reference', $loan->reference);
    }
}
