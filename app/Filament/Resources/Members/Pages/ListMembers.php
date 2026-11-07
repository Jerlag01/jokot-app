<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Support\MemberCards;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    public function listYear(): int
    {
        return (int) ($this->tableFilters['year']['value'] ?? now()->year);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(fn (): string => 'Ledenlijst '.$this->listYear().' (Excel)')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function (): BinaryFileResponse {
                    $year = $this->listYear();
                    $members = Member::query()->memberIn($year)->with('memberships')->orderBy('last_name')->orderBy('first_name')->get();

                    return response()->download(app(MemberCards::class)->xlsx($members, $year), "ledenlijst-{$year}.xlsx")->deleteFileAfterSend();
                }),
            Action::make('cardsAll')
                ->label('Alle lidkaarten')
                ->icon(Heroicon::OutlinedIdentification)
                ->color('gray')
                ->modalDescription(fn (): string => 'Alle leden van '.$this->listYear().' op A4-bladen (10 per blad).')
                ->action(fn () => MemberResource::downloadCards(Member::query()->memberIn($this->listYear())->get(), $this->listYear(), 'a4')),
            CreateAction::make()
                ->label('Nieuw lid')
                ->schema([
                    Section::make('Gegevens')->columns(2)->schema(MemberResource::detailFields()),
                    Section::make('Lidmaatschap')->columns(4)->schema([
                        Toggle::make('with_membership')->label('Meteen lid maken')->default(true)->live()->columnSpanFull(),
                        ...array_map(fn ($field) => $field->visible(fn (Get $get): bool => (bool) $get('with_membership')), MemberResource::membershipFields()),
                    ]),
                ])
                ->using(function (array $data): Member {
                    $member = Member::query()->create(collect($data)->only((new Member)->getFillable())->all());

                    if ($data['with_membership'] ?? false) {
                        $member->memberships()->create(collect($data)->only(['year', 'amount', 'method', 'paid_on'])->all());
                    }

                    return $member;
                }),
        ];
    }
}
