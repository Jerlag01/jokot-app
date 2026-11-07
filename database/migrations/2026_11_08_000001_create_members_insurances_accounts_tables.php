<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Members and membership years, insurances, and the yearly accounts (budgets, manual entries, bar takings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('street')->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('box', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('details_updated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 8, 2)->default(0);
            $table->string('method')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamp('card_printed_at')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'year']);
            $table->index('year');
        });

        Schema::create('insurances', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('insurer');
            $table->string('broker')->nullable();
            $table->string('policy_number')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('renewal_date')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('premium', 10, 2)->nullable();
            $table->string('premium_frequency')->default('yearly');
            $table->text('coverage')->nullable();
            $table->string('contact')->nullable();
            $table->json('documents')->nullable();
            $table->json('document_names')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bar_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('label')->nullable();
            $table->decimal('opening_cash', 10, 2)->nullable();
            $table->decimal('closing_cash', 10, 2)->nullable();
            $table->decimal('card_total', 10, 2)->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->unsignedInteger('member_cards_sold')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('date');
        });

        Schema::create('annual_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('line');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['year', 'line']);
        });

        Schema::create('annual_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('line');
            $table->date('date')->nullable();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_import')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['year', 'line']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('annual_line')->nullable()->after('name');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('kind')->default('event')->after('title');
        });

        // The new permissions go to the existing finance and editor roles (new installs get them from the seeder).
        $grants = ['Financieel' => ['members', 'insurances', 'accounts'], 'Editor' => ['members']];

        foreach ($grants as $roleName => $newPermissions) {
            DB::table('roles')->where('name', $roleName)->get(['id', 'permissions'])->each(function (object $role) use ($newPermissions): void {
                $permissions = json_decode((string) $role->permissions, true) ?: [];
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique([...$permissions, ...$newPermissions])))]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropColumn('annual_line'));
        Schema::dropIfExists('annual_entries');
        Schema::dropIfExists('annual_budgets');
        Schema::dropIfExists('bar_sessions');
        Schema::dropIfExists('insurances');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('members');
    }
};
