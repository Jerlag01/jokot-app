<?php

namespace App\Support;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only way stock changes: every change is a movement, and the product row is locked while it is booked.
 */
class Stock
{
    public function move(Product $product, StockMovementType $type, int $quantity, ?string $note = null, ?Model $source = null): StockMovement
    {
        return DB::transaction(function () use ($product, $type, $quantity, $note, $source): StockMovement {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $locked->stock_quantity += $quantity;
            $locked->save();

            $product->stock_quantity = $locked->stock_quantity;

            return $locked->stockMovements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $locked->stock_quantity,
                'note' => $note,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * A new count with one line per tracked product, the current stock as expected quantity.
     */
    public function startCount(?string $note = null): StockCount
    {
        return DB::transaction(function () use ($note): StockCount {
            $count = StockCount::query()->create(['counted_on' => today(), 'note' => $note, 'user_id' => auth()->id()]);

            Product::query()->tracked()->orderBy('category')->orderBy('sort')->get()
                ->each(fn (Product $product) => $count->lines()->create(['product_id' => $product->id, 'expected' => $product->stock_quantity]));

            return $count;
        });
    }

    /**
     * Books the difference between counted and the stock at this moment (sales may have happened since the count began).
     * Lines left empty are skipped.
     */
    public function finalizeCount(StockCount $count): int
    {
        if ($count->isFinalized()) {
            throw new RuntimeException('Deze telling is al afgesloten.');
        }

        return DB::transaction(function () use ($count): int {
            $corrections = 0;

            foreach ($count->lines()->with('product')->whereNotNull('counted')->get() as $line) {
                $current = (int) Product::query()->lockForUpdate()->whereKey($line->product_id)->value('stock_quantity');
                $difference = $line->counted - $current;

                if ($difference !== 0) {
                    $this->move($line->product, StockMovementType::Count, $difference, 'Telling van '.$count->counted_on->format('d/m/Y'), $count);
                    $corrections++;
                }
            }

            $count->update(['finalized_at' => now()]);

            return $corrections;
        });
    }
}
