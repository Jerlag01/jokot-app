<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\TicketType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';

    protected static ?string $title = 'Tickettypes';

    protected static ?string $modelLabel = 'tickettype';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Naam')->placeholder('bv. Voorverkoop, Leden, Aan de deur')->required()->maxLength(100),
            TextInput::make('price')->label('Prijs')->numeric()->prefix('€')->minValue(0)->default(0)->required(),
            TextInput::make('description')->label('Omschrijving')->maxLength(255)->columnSpanFull(),
            TextInput::make('capacity')->label('Aantal beschikbaar')->numeric()->minValue(1)->helperText('Leeg = onbeperkt (enkel het totaal van het evenement telt).'),
            TextInput::make('max_per_order')->label('Max. per bestelling')->numeric()->minValue(1)->default(10)->required(),
            DateTimePicker::make('sales_start')->label('Verkoop vanaf')->seconds(false)->native(false)->displayFormat('d/m/Y H:i'),
            DateTimePicker::make('sales_end')->label('Verkoop tot')->seconds(false)->native(false)->displayFormat('d/m/Y H:i')->after('sales_start'),
            Toggle::make('is_active')->label('Actief')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Naam')->description(fn (TicketType $record): ?string => $record->description),
                TextColumn::make('price')->label('Prijs')->money('EUR'),
                TextColumn::make('sold')->label('Verkocht')->state(fn (TicketType $record): string => $record->tickets()->whereNull('cancelled_at')->count().($record->capacity ? ' / '.$record->capacity : '')),
                TextColumn::make('sales')->label('Verkoop')->state(fn (TicketType $record): string => ($record->sales_start?->format('d/m H:i') ?? 'nu').' - '.($record->sales_end?->format('d/m H:i') ?? 'start event')),
                ToggleColumn::make('is_active')->label('Actief'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (TicketType $record): bool => $record->orderItems()->exists()),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) $ownerRecord->tickets_enabled;
    }
}
