<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // The 5-digit barcode on the pre-printed member card (scanned at the POS or typed in the admin site).
            $table->string('card_number', 5)->nullable()->unique()->after('member_number');

            // The POS inserts members straight into the database: it does not know our member number or the
            // QR token, so both may be empty for a moment until `members:complete` fills them in.
            $table->string('member_number')->nullable()->change();
            $table->string('token', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['card_number']);
            $table->dropColumn('card_number');
            $table->string('member_number')->nullable(false)->change();
            $table->string('token', 64)->nullable(false)->change();
        });
    }
};
