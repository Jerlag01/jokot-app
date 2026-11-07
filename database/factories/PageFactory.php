<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->word());

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'blocks' => [['type' => 'text', 'data' => ['content' => '<p>'.fake()->sentence().'</p>']]],
            'is_published' => true,
            'show_in_menu' => false,
            'menu_order' => 0,
        ];
    }
}
