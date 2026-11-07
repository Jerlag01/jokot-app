<?php

namespace App\Support\Stats;

use App\Models\PageView;
use App\Support\Security\GeoIp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts page views on the public site without cookies and without storing IP addresses:
 * a visitor is a hash of IP + browser + date, so the same person counts once per day and cannot be traced.
 */
class VisitorTracker
{
    /** Hosts that are search engines, AI assistants or social networks (checked as suffix of the referrer host). */
    public const SEARCH = [
        'google.' => 'Google', 'bing.com' => 'Bing', 'duckduckgo.com' => 'DuckDuckGo', 'search.yahoo.' => 'Yahoo',
        'yahoo.com' => 'Yahoo', 'ecosia.org' => 'Ecosia', 'qwant.com' => 'Qwant', 'yandex.' => 'Yandex', 'baidu.com' => 'Baidu',
        'search.brave.com' => 'Brave', 'startpage.com' => 'Startpage', 'ask.com' => 'Ask', 'aol.com' => 'AOL', 'seznam.cz' => 'Seznam',
    ];

    public const AI = [
        'chatgpt.com' => 'ChatGPT', 'chat.openai.com' => 'ChatGPT', 'perplexity.ai' => 'Perplexity', 'copilot.microsoft.com' => 'Copilot',
        'gemini.google.com' => 'Gemini', 'claude.ai' => 'Claude',
    ];

    public const SOCIAL = [
        'facebook.com' => 'Facebook', 'fb.me' => 'Facebook', 'instagram.com' => 'Instagram', 't.co' => 'X (Twitter)', 'x.com' => 'X (Twitter)',
        'twitter.com' => 'X (Twitter)', 'linkedin.com' => 'LinkedIn', 'lnkd.in' => 'LinkedIn', 'tiktok.com' => 'TikTok', 'youtube.com' => 'YouTube',
        'whatsapp.com' => 'WhatsApp', 'wa.me' => 'WhatsApp', 'reddit.com' => 'Reddit', 'pinterest.' => 'Pinterest', 'snapchat.com' => 'Snapchat',
        'messenger.com' => 'Messenger',
    ];

    private const BOTS = '/bot|crawl|spider|slurp|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|uptime|monitor|pingdom|headless|lighthouse|preview|facebookexternalhit|whatsapp\/|embedly|scrapy|semrush|ahrefs|mj12|petalbot|bytespider|gptbot|claudebot|ccbot|httpclient|libwww|feed/i';

    private const EXCLUDED_PATHS = ['admin', 'admin/*', 'livewire/*', 'filament/*', 'api/*', 'webhooks/*', 'up', 'kalender*', 'storage/*', 'files/*', 'contract/*', 'lid/*', 'bestelling/*'];

    public function __construct(private GeoIp $geo) {}

    public function shouldTrack(Request $request, Response $response): bool
    {
        if (! settings()->get('stats.enabled', true) || ! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson() || $request->is(...self::EXCLUDED_PATHS)) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        if (settings()->get('stats.exclude_logged_in', true) && $request->user()) {
            return false;
        }

        $agent = (string) $request->userAgent();

        return $agent !== '' && ! preg_match(self::BOTS, $agent) && $request->header('Purpose') !== 'prefetch' && $request->header('Sec-Purpose') === null;
    }

    public function track(Request $request): PageView
    {
        [$source, $name, $host] = $this->source($request);

        return PageView::query()->create([
            'visitor' => substr(hash('sha256', now()->toDateString().'|'.$request->ip().'|'.$request->userAgent().'|'.config('app.key')), 0, 16),
            'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
            'source' => $source,
            'source_name' => $name !== null ? mb_substr($name, 0, 100) : null,
            'referrer_host' => $host !== null ? mb_substr($host, 0, 255) : null,
            'country_code' => $this->geo->country($request->ip()),
            'device' => $this->device((string) $request->userAgent()),
        ]);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} source (direct|search|ai|social|referral|campaign|internal), name, referrer host
     */
    public function source(Request $request): array
    {
        $campaign = $request->query('utm_source');
        $referrer = (string) $request->headers->get('referer');
        $host = $referrer !== '' ? strtolower((string) parse_url($referrer, PHP_URL_HOST)) : null;
        $host = $host ? preg_replace('/^(www|m|l|lm|mobile)\./', '', $host) : null;

        if (is_string($campaign) && $campaign !== '') {
            return ['campaign', $campaign, $host];
        }

        if (! $host) {
            return ['direct', null, null];
        }

        $ownHosts = array_map(fn (string $own): string => (string) preg_replace('/^www\./', '', strtolower($own)), array_filter([$request->getHost(), (string) parse_url((string) config('app.url'), PHP_URL_HOST)]));

        if (in_array($host, $ownHosts, true)) {
            return ['internal', null, null];
        }

        foreach (['ai' => self::AI, 'search' => self::SEARCH, 'social' => self::SOCIAL] as $type => $list) {
            foreach ($list as $needle => $name) {
                $match = str_ends_with($needle, '.')
                    ? str_starts_with($host, $needle) || str_contains($host, '.'.$needle)
                    : $host === $needle || str_ends_with($host, '.'.$needle);

                if ($match) {
                    return [$type, $name, $host];
                }
            }
        }

        return ['referral', $host, $host];
    }

    public function device(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $agent) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $agent) => 'mobile',
            default => 'desktop',
        };
    }
}
