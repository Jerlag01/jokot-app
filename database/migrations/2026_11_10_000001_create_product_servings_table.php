<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factorberekening: the purchase price per package and the ways a product is sold (bottle, 25cl/50cl/meter
 * from a keg, glass/bottle of wine), each with its current and proposed selling price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('package_price', 10, 4)->nullable()->after('units_per_package');
            $table->decimal('vat_rate', 5, 2)->default(21)->after('package_price');
        });

        DB::table('products')->whereIn('category', ['soft', 'snack'])->update(['vat_rate' => 6]);

        Schema::create('product_servings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('servings_per_package', 10, 2);
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('proposed_price', 8, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Every product with a bar price is sold per piece; that serving drives the bar price.
        $now = now();
        DB::table('products')->whereNotNull('bar_price')->orderBy('id')->get()->each(function (object $product) use ($now): void {
            DB::table('product_servings')->insert([
                'product_id' => $product->id,
                'label' => 'Stuk',
                'servings_per_package' => max(1, (int) $product->units_per_package),
                'price' => $product->bar_price,
                'is_primary' => true,
                'sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        DB::table('roles')->where('name', 'Financieel')->get(['id', 'permissions'])->each(function (object $role): void {
            $permissions = json_decode((string) $role->permissions, true) ?: [];
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique([...$permissions, 'pricing'])))]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_servings');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['package_price', 'vat_rate']));
    }
};
