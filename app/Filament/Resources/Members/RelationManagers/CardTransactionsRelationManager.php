<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\CardTransactionStatus;
use App\Enums\CardTransactionType;
use App\Models\Member;
use App\Models\MemberCardTransaction;
use App\Support\CardTopUps;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The budget on the member card: online top-ups, purchases at the POS and movements booked by hand.
 * Rows are never edited or deleted; a mistake is put right with a correction.
 */
class CardTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'cardTransactions';

    protected static ?string $modelLabel = 'transactie';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        /** @var Member $ownerRecord */
        return 'Kaartsaldo: '.euro($ownerRecord->cardBalance());
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Nog geen transacties')
            ->columns([
                TextColumn::make('created_at')->label('Datum')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('description')->label('Omschrijving')->placeholder('-')->wrap(),
                TextColumn::make('amount')->label('Bedrag')->money('EUR', locale: 'nl_BE')->alignEnd()
                    ->color(fn (MemberCardTransaction $record): string => (float) $record->amount < 0 ? 'danger' : 'success'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('provider')->label('Betaald via')->badge()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')->label('Door')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Soort')->options(CardTransactionType::class),
                SelectFilter::make('status')->label('Status')->options(CardTransactionStatus::class)->default(CardTransactionStatus::Completed->value),
            ])
            ->headerActions([
                Action::make('topUpBar')
                    ->label('Opladen aan de toog')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->visible(fn (): bool => filled($this->getOwnerRecord()->card_number))
                    ->modalDescription('Het lid betaalde cash of met de kaart aan de toog.')
                    ->schema([
                        TextInput::make('amount')->label('Bedrag')->numeric()->prefix('€')->minValue(0.01)->required(),
                        TextInput::make('description')->label('Omschrijving')->default('Opgeladen aan de toog')->maxLength(255),
                    ])
                    ->action(fn (array $data) => $this->book((float) $data['amount'], CardTransactionType::TopUpBar, $data['description'] ?? null)),
                Action::make('correction')
                    ->label('Correctie of terugbetaling')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('gray')
                    ->modalDescription('Een positief bedrag zet geld op de kaart, een negatief bedrag haalt het eraf (bv. terugbetaald saldo).')
                    ->schema([
                        Select::make('type')->label('Soort')->options([
                            CardTransactionType::Correction->value => CardTransactionType::Correction->getLabel(),
                            CardTransactionType::Refund->value => CardTransactionType::Refund->getLabel(),
                        ])->default(CardTransactionType::Correction->value)->required(),
                        TextInput::make('amount')->label('Bedrag (+ of -)')->numeric()->prefix('€')->required(),
                        TextInput::make('description')->label('Reden')->required()->maxLength(255),
                    ])
                    ->action(fn (array $data) => $this->book((float) $data['amount'], CardTransactionType::from($data['type']), $data['description'])),
            ]);
    }

    private function book(float $amount, CardTransactionType $type, ?string $description): void
    {
        /** @var Member $member */
        $member = $this->getOwnerRecord();

        try {
            app(CardTopUps::class)->book($member, $amount, $type, $description, auth()->user());
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Geboekt. Nieuw saldo: '.euro($member->cardBalance()))->send();
    }
}
