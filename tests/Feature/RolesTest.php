<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\JokotModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_the_five_roles_and_makes_role_less_users_admin(): void
    {
        $existing = User::factory()->create();

        $this->seed(JokotModulesSeeder::class);

        $this->assertEqualsCanonicalizing(['Admin', 'Editor', 'Financieel', 'Evenementen', 'Check-in'], Role::query()->pluck('name')->all());
        $this->assertTrue($existing->fresh()->isAdmin());
    }

    public function test_a_check_in_user_only_reaches_the_check_in_screen(): void
    {
        $this->actingAs(User::factory()->withPermissions([Permissions::CHECKIN])->create());

        $this->get('/admin/checkin')->assertOk();

        foreach (['/admin/invoices', '/admin/rental-requests', '/admin/pages', '/admin/products', '/admin/users', '/admin/settings', '/admin/orders', '/admin/cup-loans'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_an_editor_manages_the_website_but_not_the_money(): void
    {
        $this->actingAs(User::factory()->withPermissions([Permissions::PAGES, Permissions::EVENTS])->create());

        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/events')->assertOk();
        $this->get('/admin/invoices')->assertForbidden();
        $this->get('/admin/checkin')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
    }

    public function test_permissions_add_up_over_roles(): void
    {
        $user = User::factory()->withPermissions([Permissions::CHECKIN])->create();
        $user->roles()->attach(Role::query()->create(['name' => 'Kas', 'permissions' => [Permissions::INVOICES]]));
        $user->refreshPermissions();

        $this->assertTrue($user->hasPermission(Permissions::CHECKIN));
        $this->assertTrue($user->hasPermission(Permissions::INVOICES));
        $this->assertFalse($user->hasPermission(Permissions::USERS));
    }
}
