<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A user with the admin role (all permissions).
     */
    public function admin(): static
    {
        return $this->withRole('Admin', isAdmin: true);
    }

    /**
     * A user with a role granting only the given permissions.
     *
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->withRole('Test '.implode(',', $permissions), permissions: $permissions);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function withRole(string $name, bool $isAdmin = false, array $permissions = []): static
    {
        return $this->afterCreating(function (User $user) use ($name, $isAdmin, $permissions): void {
            $role = Role::query()->firstOrCreate(['name' => $name], ['is_admin' => $isAdmin, 'permissions' => $permissions]);
            $user->roles()->attach($role);
        });
    }
}
