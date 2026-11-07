<?php

namespace App\Filament\Resources\Events\Pages;

use App\Enums\PaymentProvider;
use App\Filament\Pages\CheckInPage;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\TicketType;
use App\Support\Permissions;
use App\Support\Ticketing\TicketSales;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkin')
                ->label('Check-in')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->tickets_enabled && auth()->user()->hasPermission(Permissions::CHECKIN))
                ->url(fn (): string => CheckInPage::getUrl(['event' => $this->getRecord()->id])),
            Action::make('boxOffice')
                ->label('Kassaverkoop')
                ->icon(Heroicon::OutlinedTicket)
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord()->tickets_enabled && $this->getRecord()->ticketTypes()->exists() && auth()->user()->hasPermission(Permissions::TICKET_ORDERS))
                ->modalDescription('Tickets verkocht aan de deur of op voorhand (cash of kaart). Ze worden meteen als betaald en uitgereikt geregistreerd.')
                ->schema([
                    Select::make('ticket_type_id')->label('Tickettype')->required()
                        ->options(fn (): array => $this->getRecord()->ticketTypes->mapWithKeys(fn (TicketType $type): array => [$type->id => $type->name.' ('.euro($type->price).')'])->all()),
                    TextInput::make('quantity')->label('Aantal')->numeric()->minValue(1)->maxValue(100)->default(1)->required(),
                    Select::make('provider')->label('Betaald met')->required()->default(PaymentProvider::Cash->value)
                        ->options([PaymentProvider::Cash->value => 'Cash', PaymentProvider::Card->value => 'Kaart / Bancontact', PaymentProvider::Free->value => 'Gratis (gastenlijst)']),
                    TextInput::make('name')->label('Naam (optioneel)'),
                    TextInput::make('email')->label('E-mail (optioneel, tickets worden dan gemaild)')->email(),
                ])
                ->action(function (array $data) {
                    try {
                        $order = app(TicketSales::class)->boxOffice(
                            TicketType::query()->findOrFail($data['ticket_type_id']),
                            (int) $data['quantity'],
                            PaymentProvider::from($data['provider']),
                            auth()->user(),
                            $data['name'] ?? null,
                            $data['email'] ?? null,
                        );
                        Notification::make()->success()->title("Bestelling {$order->number}: {$data['quantity']} ticket(s)")->send();

                        return redirect(OrderResource::getUrl('view', ['record' => $order]));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
            Action::make('open')->label('Bekijk op de website')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (): string => EventResource::publicUrl($this->getRecord()), shouldOpenInNewTab: true),
            DeleteAction::make()->hidden(fn (): bool => $this->getRecord()->orders()->exists()),
        ];
    }
}
