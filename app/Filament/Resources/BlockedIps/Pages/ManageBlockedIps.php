<?php

namespace App\Filament\Resources\BlockedIps\Pages;

use App\Filament\Resources\BlockedIps\BlockedIpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedIps extends ManageRecords
{
    protected static string $resource = BlockedIpResource::class;

    public function getSubheading(): ?string
    {
        return 'Je eigen IP-adres: '.request()->ip().'. Buitengesloten? Op de server: php artisan security:unblock <ip>';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('IP blokkeren')->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id(), 'is_automatic' => false])];
    }
}
