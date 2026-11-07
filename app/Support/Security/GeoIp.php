<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Http;
use Locale;
use MaxMind\Db\Reader;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Country of an IP address, looked up locally in the free DB-IP "IP to Country Lite" database
 * (CC BY 4.0, https://db-ip.com). No address is sent to an outside service. Updated monthly by geoip:update.
 */
class GeoIp
{
    private ?Reader $reader = null;

    private bool $opened = false;

    /** @var array<string, ?string> */
    private array $cache = [];

    public static function path(): string
    {
        return storage_path('app/geoip/dbip-country-lite.mmdb');
    }

    public function available(): bool
    {
        return is_file(self::path());
    }

    public function country(?string $ip): ?string
    {
        if (blank($ip) || IpUtils::isPrivateIp($ip)) {
            return null;
        }

        if (array_key_exists($ip, $this->cache)) {
            return $this->cache[$ip];
        }

        $code = null;

        try {
            $record = $this->reader()?->get($ip);
            $code = is_array($record) ? ($record['country']['iso_code'] ?? null) : null;
        } catch (Throwable) {
            $code = null;
        }

        return $this->cache[$ip] = $code ? strtoupper($code) : null;
    }

    public static function countryName(?string $code): string
    {
        if (blank($code)) {
            return 'Onbekend';
        }

        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-'.$code, 'nl') : '';

        return $name && $name !== $code ? $name : $code;
    }

    public static function flag(?string $code): string
    {
        if (blank($code) || strlen($code) !== 2) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }

    public static function label(?string $code): string
    {
        return trim(self::flag($code).' '.self::countryName($code));
    }

    /**
     * Downloads this month's database (falls back to last month's at the start of a month).
     */
    public function update(): string
    {
        $directory = dirname(self::path());
        is_dir($directory) || mkdir($directory, 0775, true);

        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $url = 'https://download.db-ip.com/free/dbip-country-lite-'.$month->format('Y-m').'.mmdb.gz';
            $response = Http::timeout(120)->withUserAgent('jokot-app geoip updater')->get($url);

            if (! $response->successful()) {
                continue;
            }

            $data = gzdecode($response->body());

            if ($data === false || strlen($data) < 100000) {
                continue;
            }

            $tmp = self::path().'.tmp';
            file_put_contents($tmp, $data);
            new Reader($tmp); // throws when the file is not a valid database
            rename($tmp, self::path());
            $this->reader = null;
            $this->opened = false;

            return $url;
        }

        throw new \RuntimeException('Kon de DB-IP-database niet downloaden.');
    }

    private function reader(): ?Reader
    {
        if (! $this->opened) {
            $this->opened = true;
            $this->reader = $this->available() ? new Reader(self::path()) : null;
        }

        return $this->reader;
    }
}
