<?php

namespace App\Support\Security;

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Logs sign-ins and blocks IP addresses: by hand, or automatically after too many failed sign-ins
 * (settings security.*). Whitelisted addresses are never blocked.
 */
class IpBlocker
{
    private const CACHE_KEY = 'security.blocked_ips';

    public function __construct(private GeoIp $geo) {}

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The active block that matches this address, if any.
     */
    public function blockFor(?string $ip): ?array
    {
        if (blank($ip) || $this->isWhitelisted($ip)) {
            return null;
        }

        $blocks = Cache::remember(self::CACHE_KEY, 60, fn (): array => BlockedIp::query()->active()->get(['id', 'ip', 'blocked_until'])
            ->map(fn (BlockedIp $block): array => ['id' => $block->id, 'ip' => $block->ip, 'until' => $block->blocked_until?->getTimestamp()])
            ->all());

        foreach ($blocks as $block) {
            if (($block['until'] === null || $block['until'] > time()) && IpUtils::checkIp($ip, $block['ip'])) {
                return $block;
            }
        }

        return null;
    }

    public function registerHit(array $block): void
    {
        BlockedIp::query()->whereKey($block['id'])->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);
    }

    /**
     * @return list<string>
     */
    public function whitelist(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) settings()->get('security.whitelist')) ?: [])));
    }

    public function isWhitelisted(string $ip): bool
    {
        $whitelist = $this->whitelist();

        return $whitelist !== [] && IpUtils::checkIp($ip, $whitelist);
    }

    public function record(?string $email, ?string $ip, bool $successful, string $channel = 'admin', ?User $user = null, ?string $reason = null, ?string $userAgent = null): LoginAttempt
    {
        $attempt = LoginAttempt::query()->create([
            'email' => $email !== null ? mb_substr(mb_strtolower(trim($email)), 0, 255) : null,
            'user_id' => $user?->id,
            'ip' => (string) $ip,
            'country_code' => $this->geo->country($ip),
            'channel' => $channel,
            'successful' => $successful,
            'reason' => $reason,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
        ]);

        if (! $successful && $ip) {
            $this->autoBlock($ip);
        }

        return $attempt;
    }

    /**
     * Blocks the address when it reached the maximum number of failed sign-ins within the window.
     */
    public function autoBlock(string $ip): ?BlockedIp
    {
        $settings = (array) settings()->get('security');

        if (! ($settings['auto_block'] ?? true) || $this->isWhitelisted($ip) || $this->blockFor($ip)) {
            return null;
        }

        $max = max(1, (int) ($settings['max_attempts'] ?? 5));
        $window = max(1, (int) ($settings['window_minutes'] ?? 15));

        $failures = LoginAttempt::query()
            ->where('ip', $ip)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($window))
            ->count();

        if ($failures < $max) {
            return null;
        }

        $minutes = (int) ($settings['block_minutes'] ?? 60);

        return BlockedIp::query()->updateOrCreate(['ip' => $ip], [
            'reason' => "{$failures} mislukte aanmeldingen in {$window} minuten",
            'is_automatic' => true,
            'blocked_until' => $minutes > 0 ? now()->addMinutes($minutes) : null,
        ]);
    }

    public function block(string $ip, ?string $reason = null, ?int $minutes = null, ?User $by = null): BlockedIp
    {
        return BlockedIp::query()->updateOrCreate(['ip' => $ip], [
            'reason' => $reason,
            'is_automatic' => false,
            'blocked_until' => $minutes ? now()->addMinutes($minutes) : null,
            'created_by' => $by?->id,
        ]);
    }
}
