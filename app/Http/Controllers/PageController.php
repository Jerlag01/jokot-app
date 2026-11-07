<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function home(): View
    {
        $page = Page::query()->published()->where('slug', Page::HOME_SLUG)->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        if ($slug === Page::HOME_SLUG) {
            return redirect()->route('home', status: 301);
        }

        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }
}
