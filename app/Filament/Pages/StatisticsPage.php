<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Support\Permissions;
use App\Support\Stats\VisitorStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Visitors of the public site: how many, where they came from (search engines, social media, other sites)
 * and which pages they viewed. No cookies, no IP addresses stored.
 */
class StatisticsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Website;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Statistieken';

    protected static ?string $title = 'Bezoekersstatistieken';

    protected static ?string $slug = 'statistics';

    protected string $view = 'filament.pages.statistics';

    #[Url]
    public string $period = '30d';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::STATISTICS);
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, VisitorStats::PERIODS) ? $period : '30d';
    }

    public function stats(): VisitorStats
    {
        return app(VisitorStats::class);
    }
}
