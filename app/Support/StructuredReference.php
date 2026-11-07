<?php

namespace App\Support;

/**
 * Belgian structured communication (gestructureerde mededeling / OGM): 10 digits + a mod-97 check of 2 digits.
 */
class StructuredReference
{
    public static function forInvoice(int $year, int $sequence, bool $creditNote = false): string
    {
        $base = sprintf('%04d%d%05d', $year, $creditNote ? 2 : 1, $sequence % 100000);

        return self::format($base);
    }

    public static function format(string $tenDigits): string
    {
        $check = (int) $tenDigits % 97;
        $digits = $tenDigits.str_pad((string) ($check === 0 ? 97 : $check), 2, '0', STR_PAD_LEFT);

        return '+++'.substr($digits, 0, 3).'/'.substr($digits, 3, 4).'/'.substr($digits, 7, 5).'+++';
    }

    public static function isValid(string $reference): bool
    {
        $digits = preg_replace('/\D/', '', $reference);

        if (strlen($digits) !== 12) {
            return false;
        }

        $check = (int) substr($digits, 0, 10) % 97;

        return (int) substr($digits, 10) === ($check === 0 ? 97 : $check);
    }
}
