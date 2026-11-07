<?php

namespace App\Filament\Resources\LoginAttempts;

/**
 * Durations offered when blocking an IP address (minutes; 0 = permanent).
 */
class BlockedIpDurations
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['60' => '1 uur', '1440' => '1 dag', '10080' => '1 week', '43200' => '30 dagen', '0' => 'Permanent'];
    }
}
