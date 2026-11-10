<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MemberCardNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_card_number_is_typed_in_the_admin_site_and_must_be_five_unique_digits(): void
    {
        $this->actingAs(User::factory()->withPermissions([Permissions::MEMBERS])->create());
        Member::factory()->create(['card_number' => '12345']);

        $create = fn (string $card) => Livewire::test(ListMembers::class)->callAction('create', data: [
            'card_number' => $card, 'last_name' => 'Peeters', 'first_name' => 'An', 'with_membership' => false,
        ]);

        $create('1234')->assertHasActionErrors(['card_number' => 'regex']);
        $create('12a45')->assertHasActionErrors(['card_number' => 'regex']);
        $create('12345')->assertHasActionErrors(['card_number' => 'unique']);
        $create('00042')->assertHasNoActionErrors();

        $member = Member::query()->where('card_number', '00042')->sole();
        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm(['card_number' => ''])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($member->fresh()->card_number);

        $carded = Member::query()->where('card_number', '12345')->sole();
        $carded->memberships()->create(['year' => now()->year, 'amount' => 5]);
        Livewire::test(ListMembers::class)->searchTable('12345')->assertCanSeeTableRecords([$carded])->assertCanNotSeeTableRecords([$member]);
    }

    public function test_members_inserted_by_the_pos_get_a_member_number_and_token(): void
    {
        Member::factory()->create();
        DB::table('members')->insert(['card_number' => '04711', 'first_name' => 'An', 'last_name' => 'Peeters', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('members:complete')->assertSuccessful();

        $member = Member::findByCard('04711');
        $this->assertSame('L0002', $member->member_number);
        $this->assertSame(40, strlen($member->token));
        $this->assertSame(0, Member::completeMissing());
    }

    public function test_the_list_completes_pos_members_when_opened(): void
    {
        $this->actingAs(User::factory()->withPermissions([Permissions::MEMBERS])->create());
        DB::table('members')->insert(['card_number' => '00001', 'first_name' => 'Jan', 'last_name' => 'Smet', 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(ListMembers::class)->assertOk();

        $this->assertSame('L0001', Member::findByCard('00001')->member_number);
    }
}
