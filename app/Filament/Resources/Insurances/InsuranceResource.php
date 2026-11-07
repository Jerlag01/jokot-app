<?php

namespace App\Filament\Resources\Insurances;

use App\Enums\InsuranceType;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Insurances\Pages\CreateInsurance;
use App\Filament\Resources\Insurances\Pages\EditInsurance;
use App\Filament\Resources\Insurances\Pages\ListInsurances;
use App\Filament\Resources\Insurances\Pages\ViewInsurance;
use App\Models\Insurance;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * The insurance policies of the vzw, with their documents (policy, special conditions, certificates).
 */
class InsuranceResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::INSURANCES;

    protected static ?string $model = Insurance::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'verzekering';

    protected static ?string $pluralModelLabel = 'verzekeringen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $soon = Insurance::query()->renewingSoon()->count();

        return $soon > 0 ? (string) $soon : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Vervalt binnen 60 dagen';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Polis')->columnSpan(2)->columns(2)->schema([
                Select::make('type')->label('Soort')->options(InsuranceType::class)->required(),
                TextInput::make('name')->label('Naam')->placeholder('bv. BA + LO vrijwilligers')->required()->maxLength(255),
                TextInput::make('insurer')->label('Verzekeraar')->required()->maxLength(255),
                TextInput::make('policy_number')->label('Polisnummer')->maxLength(100),
                TextInput::make('broker')->label('Makelaar')->maxLength(255),
                TextInput::make('contact')->label('Contact (bij schade)')->maxLength(255),
                DatePicker::make('starts_on')->label('Ingangsdatum')->native(false)->displayFormat('d/m/Y'),
                DatePicker::make('renewal_date')->label('Vervaldag / hernieuwing')->native(false)->displayFormat('d/m/Y'),
                DatePicker::make('ends_on')->label('Opgezegd / einde')->native(false)->displayFormat('d/m/Y'),
                Toggle::make('is_active')->label('Lopend')->default(true)->inline(false),
                Textarea::make('coverage')->label('Dekking')->rows(3)->columnSpanFull(),
                Textarea::make('notes')->label('Notities')->rows(2)->columnSpanFull(),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextInput::make('premium')->label('Premie')->numeric()->prefix('€'),
                Select::make('premium_frequency')->label('Per')->options(['yearly' => 'jaar', 'quarterly' => 'kwartaal', 'monthly' => 'maand', 'once' => 'eenmalig'])->default('yearly'),
                FileUpload::make('documents')
                    ->label('Documenten')
                    ->helperText('Polis, bijzondere voorwaarden, attesten, ... (PDF of foto)')
                    ->multiple()
                    ->disk('local')
                    ->directory('insurances')
                    ->visibility('private')
                    ->storeFileNamesIn('document_names')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20 * 1024)
                    ->reorderable()
                    ->openable(false)
                    ->downloadable(false),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(3)->schema([
                TextEntry::make('type')->label('Soort')->badge(),
                TextEntry::make('insurer')->label('Verzekeraar'),
                TextEntry::make('policy_number')->label('Polisnummer')->placeholder('-')->copyable(),
                TextEntry::make('broker')->label('Makelaar')->placeholder('-'),
                TextEntry::make('contact')->label('Contact bij schade')->placeholder('-'),
                TextEntry::make('starts_on')->label('Sinds')->date('d/m/Y')->placeholder('-'),
                TextEntry::make('coverage')->label('Dekking')->placeholder('-')->columnSpanFull(),
                TextEntry::make('notes')->label('Notities')->placeholder('-')->columnSpanFull(),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextEntry::make('renewal_date')->label('Vervaldag')->date('d/m/Y')->placeholder('-')
                    ->color(fn (Insurance $record): ?string => $record->renewal_date && $record->renewal_date->isBefore(today()->addDays(60)) ? 'warning' : null),
                TextEntry::make('premium')->label('Premie')->money('EUR')->placeholder('-'),
                TextEntry::make('is_active')->label('Status')->badge()->state(fn (Insurance $record): string => $record->is_active ? 'Lopend' : 'Beëindigd')
                    ->color(fn (Insurance $record): string => $record->is_active ? 'success' : 'gray'),
            ]),
            Section::make('Documenten')->columnSpanFull()->schema([
                TextEntry::make('documents')->hiddenLabel()->placeholder('Nog geen documenten opgeladen.')
                    ->state(fn (Insurance $record): ?HtmlString => empty($record->documents) ? null : new HtmlString(collect(array_values($record->documents))
                        ->map(fn (string $path, int $index): string => '<a href="'.e(route('insurances.document', [$record, $index])).'" target="_blank" style="text-decoration:underline;">📄 '.e($record->documentName($path)).'</a>')
                        ->implode('<br>'))),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('renewal_date')
            ->columns([
                TextColumn::make('name')->label('Naam')->description(fn (Insurance $record): string => $record->type->getLabel())->searchable(),
                TextColumn::make('insurer')->label('Verzekeraar')->searchable(),
                TextColumn::make('policy_number')->label('Polisnr.')->placeholder('-')->toggleable(),
                TextColumn::make('renewal_date')->label('Vervaldag')->date('d/m/Y')->sortable()->placeholder('-')
                    ->color(fn (Insurance $record): ?string => $record->renewal_date && $record->renewal_date->isBefore(today()->addDays(60)) ? 'warning' : null),
                TextColumn::make('premium')->label('Premie')->money('EUR')->placeholder('-')->summarize(Sum::make()->money('EUR')->label('')),
                TextColumn::make('documents')->label('Docs')->state(fn (Insurance $record): int => count($record->documents ?? [])),
                IconColumn::make('is_active')->label('Lopend')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Soort')->options(InsuranceType::class),
                TernaryFilter::make('is_active')->label('Lopend')->default(true),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInsurances::route('/'),
            'create' => CreateInsurance::route('/create'),
            'view' => ViewInsurance::route('/{record}'),
            'edit' => EditInsurance::route('/{record}/edit'),
        ];
    }
}
