<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles, stock, invoicing, contracts, cup loans (wisselbekers) and ticketing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Roles and permissions -------------------------------------------------------------
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Products (was: drinks) and stock ---------------------------------------------------
        Schema::rename('drinks', 'products');
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->default('soft')->after('name');
            $table->unsignedInteger('units_per_package')->nullable()->after('package');
            $table->decimal('rental_price', 8, 2)->nullable()->after('bar_price');
            $table->decimal('purchase_price', 8, 2)->nullable()->after('rental_price');
            $table->boolean('track_stock')->default(true)->after('is_orderable');
            $table->integer('stock_quantity')->default(0)->after('track_stock');
            $table->integer('min_stock')->nullable()->after('stock_quantity');
        });

        Schema::table('rental_request_drinks', function (Blueprint $table) {
            $table->dropForeign(['drink_id']);
        });
        Schema::rename('rental_request_drinks', 'rental_request_products');
        Schema::table('rental_request_products', function (Blueprint $table) {
            $table->renameColumn('drink_id', 'product_id');
        });
        Schema::table('rental_request_products', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->date('counted_on');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('expected')->default(0);
            $table->integer('counted')->nullable();
            $table->unique(['stock_count_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->string('note')->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        // Invoicing --------------------------------------------------------------------------
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('vat_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('street')->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('box', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('BE');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('email');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('invoice');
            $table->string('number')->nullable()->unique();
            $table->string('status')->default('draft')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('credited_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('structured_reference', 20)->nullable();
            $table->string('subject')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('paid_total', 10, 2)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 10, 2);
            $table->string('method')->default('bank');
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Contracts --------------------------------------------------------------------------
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->string('title');
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->morphs('contractable');
            $table->string('token', 64)->unique();
            $table->string('status')->default('draft');
            $table->longText('body')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_ip', 45)->nullable();
            $table->string('signature_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        // Rental requests: customer, deposit, drink count, invoice ---------------------------
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('rental_extra_id')->constrained()->nullOnDelete();
            $table->timestamp('deposit_paid_at')->nullable()->after('quoted_deposit');
            $table->unsignedInteger('corkage_bottles')->default(0)->after('brings_own_wine');
            $table->foreignId('invoice_id')->nullable()->after('corkage_bottles')->constrained()->nullOnDelete();
            $table->timestamp('drinks_booked_at')->nullable()->after('invoice_id');
        });

        Schema::create('rental_drink_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_before')->default(0);
            $table->unsignedInteger('quantity_after')->default(0);
            $table->unique(['rental_request_id', 'product_id']);
        });

        // Cup loans (wisselbekers) -----------------------------------------------------------
        Schema::create('cup_loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('status')->default('requested')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('organisation');
            $table->string('representative');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('street');
            $table->string('house_number', 20);
            $table->string('box', 20)->nullable();
            $table->string('postal_code', 10);
            $table->string('city');
            $table->string('vat_number', 30)->nullable();
            $table->string('event_name')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('cups');
            $table->unsignedInteger('crates')->default(0);
            $table->unsignedInteger('cups_returned')->nullable();
            $table->unsignedInteger('cups_dirty')->default(0);
            $table->unsignedInteger('crates_returned')->nullable();
            $table->unsignedInteger('crates_damaged')->default(0);
            $table->decimal('rental_fee', 8, 2);
            $table->decimal('cup_fee', 8, 2);
            $table->decimal('crate_fee', 8, 2);
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('details')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // Ticketing --------------------------------------------------------------------------
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('tickets_enabled')->default(false)->after('blocks_rentals');
            $table->unsignedInteger('capacity')->nullable()->after('tickets_enabled');
            $table->text('ticket_info')->nullable()->after('capacity');
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('max_per_order')->default(10);
            $table->dateTime('sales_start')->nullable();
            $table->dateTime('sales_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('token', 64)->unique();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_transaction')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 8, 2);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('holder_name')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'checked_in_at']);
        });

        // The pseudo-products for the old price list ("Frisdrank", "Pils", "Fruitbier") are not real stock items.
        DB::table('products')->whereNull('package')->whereIn('name', ['Frisdrank', 'Pils', 'Fruitbier'])->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('ticket_types');
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['tickets_enabled', 'capacity', 'ticket_info']));
        Schema::dropIfExists('cup_loans');
        Schema::dropIfExists('rental_drink_counts');
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn(['deposit_paid_at', 'corkage_bottles', 'drinks_booked_at']);
        });
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::table('rental_request_products', fn (Blueprint $table) => $table->dropForeign(['product_id']));
        Schema::table('rental_request_products', fn (Blueprint $table) => $table->renameColumn('product_id', 'drink_id'));
        Schema::rename('rental_request_products', 'rental_request_drinks');
        Schema::table('rental_request_drinks', fn (Blueprint $table) => $table->foreign('drink_id')->references('id')->on('products')->nullOnDelete());
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['category', 'units_per_package', 'rental_price', 'purchase_price', 'track_stock', 'stock_quantity', 'min_stock']));
        Schema::rename('products', 'drinks');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
