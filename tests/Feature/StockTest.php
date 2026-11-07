<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Support\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_movements_update_the_stock_and_keep_a_ledger(): void
    {
        $cola = Product::factory()->create(['stock_quantity' => 10]);
        $stock = app(Stock::class);

        $stock->move($cola, StockMovementType::Purchase, 48, 'Levering');
        $stock->move($cola, StockMovementType::Sale, -20);

        $this->assertSame(38, $cola->fresh()->stock_quantity);
        $this->assertSame([58, 38], $cola->stockMovements()->orderBy('id')->pluck('balance_after')->all());
    }

    public function test_finalising_a_count_books_only_the_differences(): void
    {
        $cola = Product::factory()->create(['stock_quantity' => 30, 'track_stock' => true]);
        $chips = Product::factory()->create(['stock_quantity' => 12, 'track_stock' => true]);
        $untracked = Product::factory()->create(['track_stock' => false]);

        $count = app(Stock::class)->startCount('maandtelling');
        $this->assertSame(2, $count->lines()->count());
        $this->assertFalse($count->lines()->where('product_id', $untracked->id)->exists());

        $count->lines()->where('product_id', $cola->id)->update(['counted' => 27]);
        $count->lines()->where('product_id', $chips->id)->update(['counted' => 12]);

        $this->assertSame(1, app(Stock::class)->finalizeCount($count));
        $this->assertSame(27, $cola->fresh()->stock_quantity);
        $this->assertSame(StockMovementType::Count, $cola->stockMovements()->sole()->type);
        $this->assertNotNull($count->fresh()->finalized_at);
    }

    public function test_low_stock_and_crate_labels(): void
    {
        $low = Product::factory()->create(['stock_quantity' => 5, 'min_stock' => 24, 'units_per_package' => 24]);
        Product::factory()->create(['stock_quantity' => 50, 'min_stock' => 24]);

        $this->assertSame([$low->id], Product::query()->lowStock()->pluck('id')->all());
        $this->assertSame('50 (2 bakken + 2)', $low->stockLabel(50));
    }
}
