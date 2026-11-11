<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Page;
use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml for search engines. Both are generated, so new pages and events are listed
 * automatically and the development site tells robots to stay away.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = Seo::indexable()
            ? [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /livewire/',
                'Disallow: /api/',
                'Disallow: /bestelling/',
                'Disallow: /contract/',
                'Disallow: /lid/',
                'Disallow: /opladen/',
                'Disallow: /kalender',
                'Disallow: /webhooks/',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = [];

        Page::query()->published()->orderBy('menu_order')->get(['slug', 'updated_at', 'show_in_menu'])->each(function (Page $page) use (&$urls): void {
            $urls[] = [
                'loc' => $page->url(),
                'lastmod' => $page->updated_at,
                'priority' => $page->isHome() ? '1.0' : ($page->show_in_menu ? '0.8' : '0.5'),
            ];
        });

        // Upcoming events first; past events stay listed for a year (they still get searched for).
        Event::query()
            ->where('is_published', true)
            ->where('starts_at', '>=', now()->subYear())
            ->orderByDesc('starts_at')
            ->get(['slug', 'starts_at', 'updated_at'])
            ->each(function (Event $event) use (&$urls): void {
                $urls[] = [
                    'loc' => route('events.show', $event),
                    'lastmod' => $event->updated_at,
                    'priority' => $event->starts_at->isFuture() ? '0.7' : '0.3',
                ];
            });

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
