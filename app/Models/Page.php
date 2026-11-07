<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A website page built from Filament Builder blocks (see App\Filament\Resources\Pages\PageBlocks).
 */
class Page extends Model
{
    use HasFactory;

    public const HOME_SLUG = 'home';

    protected $fillable = ['title', 'slug', 'meta_description', 'blocks', 'is_published', 'show_in_menu', 'menu_order'];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function isHome(): bool
    {
        return $this->slug === self::HOME_SLUG;
    }

    public function url(): string
    {
        return $this->isHome() ? route('home') : route('page', $this->slug);
    }

    /**
     * Whether the page has a block of the given type, e.g. to load the calendar script only when needed.
     */
    public function hasBlock(string $type): bool
    {
        return collect($this->blocks ?? [])->contains(fn (array $block): bool => ($block['type'] ?? null) === $type);
    }
}
