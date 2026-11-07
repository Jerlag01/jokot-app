<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Incoming (purchase) invoices from suppliers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vat_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('bic', 15)->nullable();
            $table->string('street')->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('BE');
            $table->foreignId('default_category_id')->nullable();
            $table->unsignedSmallInteger('payment_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreign('default_category_id')->references('id')->on('expense_categories')->nullOnDelete();
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type')->default('invoice');
            $table->string('status')->default('open')->index();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('description')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('paid_total', 10, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('stock_booked_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'number']);
        });

        Schema::create('purchase_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 10, 2);
            $table->string('method')->default('bank');
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // The finance role that already exists gets the new permission (new installs get it from the seeder).
        DB::table('roles')->where('name', 'Financieel')->get(['id', 'permissions'])->each(function (object $role): void {
            $permissions = json_decode((string) $role->permissions, true) ?: [];

            if (! in_array('purchases', $permissions, true)) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, 'purchases'])]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_payments');
        Schema::dropIfExists('purchase_invoices');
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropForeign(['default_category_id']));
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('suppliers');
    }
};
