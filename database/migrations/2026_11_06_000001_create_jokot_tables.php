<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('meta_description')->nullable();
            $table->json('blocks')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_menu')->default(false);
            $table->unsignedInteger('menu_order')->default(0);
            $table->timestamps();
        });

        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->boolean('is_closed')->default(false);
            $table->string('opens_at', 10)->nullable();
            $table->string('closes_at', 10)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->decimal('deposit', 8, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rental_extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('deposit', 8, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drinks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('package')->nullable();
            $table->decimal('bar_price', 8, 2)->nullable();
            $table->boolean('is_orderable')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('image')->nullable();
            $table->string('ticket_url')->nullable();
            $table->string('price_label')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('blocks_rentals')->default(true);
            $table->timestamps();
        });

        Schema::create('rental_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('status')->default('new')->index();
            $table->date('event_date')->index();
            $table->foreignId('rental_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_extra_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('organisation')->nullable();
            $table->string('company_number')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->string('street');
            $table->string('house_number', 20);
            $table->string('box', 20)->nullable();
            $table->string('postal_code', 10);
            $table->string('city');
            $table->text('details')->nullable();
            $table->unsignedInteger('expected_guests')->nullable();
            $table->boolean('brings_own_wine')->default(false);
            $table->decimal('quoted_price', 8, 2)->nullable();
            $table->decimal('quoted_deposit', 8, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('rental_request_drinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drink_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_request_drinks');
        Schema::dropIfExists('rental_requests');
        Schema::dropIfExists('events');
        Schema::dropIfExists('drinks');
        Schema::dropIfExists('rental_extras');
        Schema::dropIfExists('rental_rates');
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
