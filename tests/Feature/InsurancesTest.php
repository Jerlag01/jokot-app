<?php

namespace Tests\Feature;

use App\Filament\Resources\Insurances\Pages\CreateInsurance;
use App\Filament\Resources\Insurances\Pages\EditInsurance;
use App\Models\Insurance;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InsurancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_policy_is_stored_with_its_documents(): void
    {
        Livewire::test(CreateInsurance::class)
            ->fillForm([
                'type' => 'fire', 'name' => 'Brandverzekering JOC', 'insurer' => 'KBC', 'policy_number' => 'BR-77',
                'renewal_date' => today()->addDays(20)->toDateString(), 'premium' => 62.88,
                'documents' => [UploadedFile::fake()->create('polis.pdf', 80, 'application/pdf'), UploadedFile::fake()->create('voorwaarden.pdf', 60, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $insurance = Insurance::query()->sole();
        $this->assertCount(2, $insurance->documents);
        $this->assertContains('polis.pdf', $insurance->document_names);
        Storage::disk('local')->assertExists($insurance->documents);
        $this->assertSame([$insurance->id], Insurance::query()->renewingSoon()->pluck('id')->all());

        $this->get(route('insurances.document', [$insurance, 0]))->assertOk();
        $this->get('/admin/insurances/'.$insurance->id)->assertOk()->assertSee('polis.pdf');
    }

    public function test_documents_are_private(): void
    {
        Storage::disk('local')->put('insurances/polis.pdf', '%PDF-1.4');
        $insurance = Insurance::factory()->create(['documents' => ['insurances/polis.pdf'], 'document_names' => ['insurances/polis.pdf' => 'polis.pdf']]);

        $this->actingAs(User::factory()->withPermissions([Permissions::MEMBERS])->create());
        $this->get(route('insurances.document', [$insurance, 0]))->assertForbidden();
        $this->get('/admin/insurances')->assertForbidden();

        auth()->logout();
        $this->get(route('insurances.document', [$insurance, 0]))->assertForbidden();
    }

    public function test_a_removed_document_is_deleted_from_the_disk(): void
    {
        Storage::disk('local')->put('insurances/a.pdf', 'a');
        Storage::disk('local')->put('insurances/b.pdf', 'b');
        $insurance = Insurance::factory()->create(['documents' => ['insurances/a.pdf', 'insurances/b.pdf']]);

        Livewire::test(EditInsurance::class, ['record' => $insurance->getRouteKey()])
            ->fillForm(['documents' => ['insurances/a.pdf']])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('local')->assertExists('insurances/a.pdf');
        Storage::disk('local')->assertMissing('insurances/b.pdf');
    }
}
