<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Models\CupLoan;
use App\Models\RentalRequest;
use App\Models\User;
use App\Support\Contracts;
use App\Support\Permissions;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class ContractsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JokotModulesSeeder::class);
        Storage::fake('local');
    }

    public function test_contracts_are_listed_per_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $rental = app(Contracts::class)->forRecord(RentalRequest::factory()->create(['organisation' => 'Chiro Niel']));
        $cups = app(Contracts::class)->forRecord(CupLoan::factory()->create(['organisation' => 'Oudercomité']));

        $this->get('/admin/contracts')->assertOk()->assertSee('Zaalverhuur')->assertSee('Wisselbekers');

        Livewire::test(ListContracts::class)->assertCanSeeTableRecords([$rental, $cups]);
        Livewire::test(ListContracts::class, ['activeTab' => 'rental'])->assertCanSeeTableRecords([$rental])->assertCanNotSeeTableRecords([$cups]);
        Livewire::test(ListContracts::class, ['activeTab' => 'cup_loan'])->assertCanSeeTableRecords([$cups])->assertCanNotSeeTableRecords([$rental]);
    }

    public function test_users_only_see_the_categories_they_have_rights_for(): void
    {
        $rental = app(Contracts::class)->forRecord(RentalRequest::factory()->create());
        $cups = app(Contracts::class)->forRecord(CupLoan::factory()->create());

        $this->actingAs(User::factory()->withPermissions([Permissions::CUP_LOANS])->create());
        Livewire::test(ListContracts::class)->assertCanSeeTableRecords([$cups])->assertCanNotSeeTableRecords([$rental]);

        $this->actingAs(User::factory()->withPermissions([Permissions::PAGES])->create());
        $this->get('/admin/contracts')->assertForbidden();
    }

    public function test_selected_contracts_export_as_a_zip_of_pdfs(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = app(Contracts::class)->forRecord(RentalRequest::factory()->create());
        $second = app(Contracts::class)->forRecord(CupLoan::factory()->create());
        app(Contracts::class)->sign($second, 'Jan', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', null);

        $response = ContractResource::zip(collect([$first, $second->fresh()]));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $this->assertSame(2, $zip->numFiles);
        $this->assertStringStartsWith('%PDF', $zip->getFromIndex(0));
        $this->assertStringContainsString('ondertekend', $zip->getNameIndex(1));
    }
}
