<?php

namespace App\Filament\Resources\LoginAttempts\Pages;

use App\Filament\Resources\LoginAttempts\LoginAttemptResource;
use App\Filament\Widgets\SecurityStats;
use Filament\Resources\Pages\ListRecords;

class ListLoginAttempts extends ListRecords
{
    protected static string $resource = LoginAttemptResource::class;

    protected function getHeaderWidgets(): array
    {
        return [SecurityStats::class];
    }
}
