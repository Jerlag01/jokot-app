<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Lidmaatschappen';

    protected static ?string $modelLabel = 'lidmaatschap';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(MemberResource::membershipFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('year')->label('Jaar'),
                TextColumn::make('amount')->label('Lidgeld')->money('EUR'),
                TextColumn::make('method')->label('Betaald met')->badge()->placeholder('-'),
                TextColumn::make('paid_on')->label('Betaald op')->date('d/m/Y')->placeholder('-'),
                TextColumn::make('card_printed_at')->label('Kaart afgedrukt')->date('d/m/Y')->placeholder('Nog niet'),
            ])
            ->headerActions([CreateAction::make()->label('Lid maken voor een jaar')])
            ->recordActions([
                Action::make('card')->label('Lidkaart')->icon(Heroicon::OutlinedIdentification)->color('gray')
                    ->action(fn (Membership $record) => MemberResource::downloadCards(collect([$this->getOwnerRecord()]), $record->year, 'card')),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
