<?php

namespace Tests\Feature;

use App\Enums\ProductCategory;
use App\Filament\Pages\PricingPage;
use App\Models\Product;
use App\Models\ProductServing;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Pricing;
use Database\Seeders\PricingImportSeeder;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    private function cola(): ProductServing
    {
        $product = Product::factory()->create(['name' => 'Cola', 'category' => ProductCategory::Soft, 'units_per_package' => 24, 'package_price' => 15.41, 'vat_rate' => 6]);

        return $product->servings()->create(['label' => 'Flesje', 'servings_per_package' => 24, 'price' => 1.5, 'proposed_price' => 1.3, 'is_primary' => true]);
    }

    public function test_factor_matches_the_sheet(): void
    {
        $pricing = app(Pricing::class);
        $cola = $this->cola();

        // Sheet: K3 = 2.203910717, R3 = 1.910055955
        $this->assertEqualsWithDelta(2.203910717, $pricing->factor($cola, $cola->price), 0.000001);
        $this->assertEqualsWithDelta(1.910055955, $pricing->factor($cola, $cola->proposed_price), 0.000001);

        $keg = Product::factory()->create(['category' => ProductCategory::Beer, 'units_per_package' => 1, 'package_price' => 136.97, 'vat_rate' => 21]);
        $meter = $keg->servings()->create(['label' => 'Meter', 'servings_per_package' => 18, 'price' => 18]);
        $this->assertEqualsWithDelta(1.954943382, $pricing->factor($meter, $meter->price), 0.000001);

        $noCost = Product::factory()->create()->servings()->create(['label' => 'Flesje', 'servings_per_package' => 24, 'price' => 4]);
        $this->assertNull($pricing->factor($noCost, 4));
        $this->assertNull($pricing->recommendedPrice($noCost));
    }

    public function test_recommended_price_uses_the_target_factor_and_rounds_up(): void
    {
        $cola = $this->cola();
        $pricing = app(Pricing::class);

        // 16.3346 / 24 = 0.6806 per bottle; × 2 = 1.361 → 1.40
        $this->assertSame(1.4, $pricing->recommendedPrice($cola));

        settings()->save(['pricing' => ['target_factor' => 2, 'rounding' => 0.5, 'target_soft' => 2.5]]);
        // × 2.5 = 1.70 → 2.00
        $this->assertSame(2.0, $pricing->recommendedPrice($cola->fresh()));
    }

    public function test_the_imported_sheet_gives_the_same_average_factor(): void
    {
        $this->seed(PricingImportSeeder::class);

        $summary = app(Pricing::class)->summary();

        // Sheet cell A2: average of the current factors.
        $this->assertEqualsWithDelta(2.172974932, $summary['current'], 0.000001);
        $this->assertSame(['Glas', 'Fles'], Product::query()->where('name', 'Witte wijn')->sole()->servings->pluck('label')->all());
        $this->assertSame(['25cl', '50cl', 'Meter'], Product::query()->where('name', 'Vat 50L')->sole()->servings->pluck('label')->all());

        // Running it again changes nothing.
        $count = ProductServing::query()->count();
        $this->seed(PricingImportSeeder::class);
        $this->assertSame($count, ProductServing::query()->count());
    }

    public function test_applying_new_prices_updates_the_bar_price(): void
    {
        $cola = $this->cola();
        $extra = $cola->product->servings()->create(['label' => 'Halve bak', 'servings_per_package' => 2, 'price' => 15, 'proposed_price' => 16]);

        $this->assertSame(2, app(Pricing::class)->applyProposed());

        $this->assertSame('1.30', $cola->fresh()->price);
        $this->assertNull($cola->fresh()->proposed_price);
        $this->assertSame('16.00', $extra->fresh()->price);
        $this->assertSame('1.30', $cola->product->fresh()->bar_price);

        // And the other way round: editing the bar price on the product updates the primary serving.
        $cola->product->update(['bar_price' => 1.6]);
        $this->assertSame('1.60', $cola->fresh()->price);
    }

    public function test_page_access_and_actions(): void
    {
        $cola = $this->cola();

        $this->actingAs(User::factory()->withPermissions([Permissions::PRICING])->create());
        $this->get('/admin/pricing')->assertOk()->assertSee('Factorberekening')->assertSee('2,20');

        Livewire::test(PricingPage::class)
            ->assertCanSeeTableRecords([$cola])
            ->callAction('proposeAll')
            ->assertNotified();
        $this->assertSame('1.40', $cola->fresh()->proposed_price);

        Livewire::test(PricingPage::class)
            ->callAction('target', ['target_factor' => 2.5, 'rounding' => '0.1'])
            ->assertHasNoActionErrors();
        $this->assertSame(2.5, app(Pricing::class)->targetFactor());

        Livewire::test(PricingPage::class)->callAction('excel')->assertFileDownloaded();

        $this->actingAs(User::factory()->withPermissions([Permissions::PRODUCTS])->create());
        $this->get('/admin/pricing')->assertForbidden();
    }
}
