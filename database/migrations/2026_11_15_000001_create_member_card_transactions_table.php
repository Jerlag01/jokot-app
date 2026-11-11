<?php

use App\Support\MailTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Budget on the member card: one row per movement. Balance = sum of the completed amounts.
        // The POS writes its purchases here directly: member_id, amount (negative), type 'purchase'.
        Schema::create('member_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('type', 20)->default('purchase')->index();
            $table->string('status', 20)->default('completed')->index();
            $table->string('description')->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_transaction')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('email')->nullable();
            $table->string('token', 40)->nullable()->unique();
            $table->string('ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'status']);
        });

        DB::statement("CREATE OR REPLACE VIEW member_card_balances AS
            SELECT members.id AS member_id, members.card_number,
                COALESCE(SUM(CASE WHEN t.status = 'completed' THEN t.amount END), 0) AS balance
            FROM members LEFT JOIN member_card_transactions t ON t.member_id = members.id
            GROUP BY members.id, members.card_number");

        MailTemplates::sync();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS member_card_balances');
        Schema::dropIfExists('member_card_transactions');
    }
};
