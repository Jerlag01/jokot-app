<?php

use App\Support\Settings;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

if (! function_exists('settings')) {
    function settings(): Settings
    {
        return app(Settings::class);
    }
}

if (! function_exists('euro')) {
    /**
     * Belgian notation without trailing zeros for round amounts: €150, €2,50.
     */
    function euro(float|int|string|null $amount): string
    {
        $amount = (float) $amount;
        $decimals = fmod($amount, 1.0) === 0.0 ? 0 : 2;

        return '€'.number_format($amount, $decimals, ',', '.');
    }
}

if (! function_exists('media_url')) {
    /**
     * Seeded images live in public/images, uploads on the public disk.
     */
    function media_url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http') || str_starts_with($path, 'images/')) {
            return str_starts_with($path, 'http') ? $path : asset($path);
        }

        return Storage::disk('public')->url($path);
    }
}

if (! function_exists('rich_text')) {
    /**
     * Renders (and sanitises) HTML from Filament's rich editor.
     */
    function rich_text(?string $html): HtmlString
    {
        return new HtmlString(
            blank($html) ? '' : RichContentRenderer::make($html)->toHtml()
        );
    }
}
