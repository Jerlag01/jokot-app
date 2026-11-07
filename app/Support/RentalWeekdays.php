<?php

namespace App\Support;

/**
 * The weekdays without rental requests (setting rental_blocked_weekdays, ISO 1 = monday ... 7 = sunday).
 */
class RentalWeekdays
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['1' => 'Maandag', '2' => 'Dinsdag', '3' => 'Woensdag', '4' => 'Donderdag', '5' => 'Vrijdag', '6' => 'Zaterdag', '7' => 'Zondag'];
    }

    /**
     * @param  array<int|string>  $weekdays
     */
    public static function save(array $weekdays): void
    {
        $clean = array_values(array_unique(array_map('intval', array_filter($weekdays, fn ($day): bool => is_numeric($day) && $day >= 1 && $day <= 7))));
        sort($clean);
        settings()->save(['rental_blocked_weekdays' => $clean]);
    }

    /**
     * @param  list<int>  $weekdays
     */
    public static function describe(array $weekdays): string
    {
        sort($weekdays);

        return implode(', ', array_map(fn (int $day): string => mb_strtolower(self::options()[(string) $day] ?? '?'), $weekdays));
    }
}
