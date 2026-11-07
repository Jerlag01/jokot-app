<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security (login attempts, blocked IPs), visitor statistics and dates/weekdays without rentals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->index();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('channel', 20)->default('admin');
            $table->boolean('successful')->default(false);
            $table->string('reason')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 64)->unique();
            $table->string('reason')->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->timestamp('blocked_until')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // No IP addresses are stored: the visitor is a hash of IP + browser + day, so it changes every day.
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->char('visitor', 16);
            $table->string('path')->index();
            $table->string('source', 20)->index();
            $table->string('source_name', 100)->nullable();
            $table->string('referrer_host')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('device', 10)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['created_at', 'visitor']);
        });

        Schema::create('rental_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('rental_blocked_dates');
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('login_attempts');
    }
};
