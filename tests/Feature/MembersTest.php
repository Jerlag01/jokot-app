<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use App\Models\User;
use App\Support\MemberCards;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class MembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_member_gets_a_number_and_a_membership(): void
    {
        $this->actingAs(User::factory()->withPermissions([Permissions::MEMBERS])->create());

        Livewire::test(ListMembers::class)
            ->callAction('create', data: [
                'last_name' => 'Peeters', 'first_name' => 'An', 'email' => 'an@example.com', 'phone' => '0470 11 22 33',
                'street' => 'Kerkstraat', 'house_number' => '1', 'postal_code' => '2845', 'city' => 'Niel',
                'with_membership' => true, 'year' => 2026, 'amount' => 5, 'method' => 'cash', 'paid_on' => '2026-11-07',
            ])
            ->assertHasNoActionErrors();

        $member = Member::query()->sole();
        $this->assertSame('L0001', $member->member_number);
        $this->assertSame(2026, $member->memberships()->sole()->year);
        $this->assertSame('Kerkstraat 1, 2845 Niel', $member->address());
    }

    public function test_the_list_shows_the_members_of_the_chosen_year(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $current = Member::factory()->memberIn(now()->year)->create();
        $former = Member::factory()->memberIn(now()->year - 1)->create();

        Livewire::test(ListMembers::class)->assertCanSeeTableRecords([$current])->assertCanNotSeeTableRecords([$former]);
        Livewire::test(ListMembers::class)->filterTable('year', (string) (now()->year - 1))->assertCanSeeTableRecords([$former])->assertCanNotSeeTableRecords([$current]);
    }

    public function test_member_cards_render_in_card_and_a4_format(): void
    {
        $members = Member::factory()->count(12)->memberIn(2026)->create();

        foreach (['card', 'a4'] as $layout) {
            $pdf = app(MemberCards::class)->pdf($members, 2026, $layout)->output();
            $this->assertStringStartsWith('%PDF', $pdf);
        }

        $this->assertStringContainsString('/MediaBox [0.000 0.000 242.650 153.070]', app(MemberCards::class)->pdf($members->take(1), 2026, 'card')->output());
    }

    public function test_the_member_list_exports_to_excel(): void
    {
        $members = Member::factory()->count(3)->memberIn(2026)->create();

        $path = app(MemberCards::class)->xlsx($members->load('memberships'), 2026);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertStringContainsString($members[0]->last_name, $zip->getFromName('xl/worksheets/sheet1.xml'));
    }

    public function test_members_update_their_own_details_through_the_qr_link(): void
    {
        $member = Member::factory()->memberIn(2026)->create(['first_name' => 'An', 'email' => 'oud@example.com']);

        $this->get($member->selfServiceUrl())->assertOk()->assertSee('Hallo An!')->assertSee('oud@example.com');

        $this->put(route('members.update', $member->token), [
            'first_name' => 'An', 'last_name' => 'Peeters', 'email' => 'nieuw@example.com', 'phone' => '0499 99 99 99',
            'street' => 'Dorpsstraat', 'house_number' => '5', 'postal_code' => '2845', 'city' => 'Niel',
        ])->assertRedirect($member->selfServiceUrl());

        $member->refresh();
        $this->assertSame('nieuw@example.com', $member->email);
        $this->assertNotNull($member->details_updated_at);
    }

    public function test_a_new_qr_code_disables_the_old_link(): void
    {
        $member = Member::factory()->create();
        $oldUrl = $member->selfServiceUrl();

        $member->regenerateToken();

        $this->get($oldUrl)->assertNotFound();
        $this->get($member->selfServiceUrl())->assertOk();
    }

    public function test_members_screens_need_the_permission(): void
    {
        $member = Member::factory()->create();
        $this->actingAs(User::factory()->withPermissions([Permissions::CHECKIN])->create());

        $this->get('/admin/members')->assertForbidden();
        $this->get("/admin/members/{$member->id}/edit")->assertForbidden();
    }
}
