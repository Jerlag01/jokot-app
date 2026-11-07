<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billit integration: link invoices, incoming invoices and customers to their Billit counterparts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('billit_order_id')->nullable()->unique()->after('reminded_at');
            $table->string('billit_transport')->nullable()->after('billit_order_id');
            $table->timestamp('billit_sent_at')->nullable()->after('billit_transport');
            $table->string('billit_delivery_status')->nullable()->after('billit_sent_at');
            $table->string('billit_delivery_info')->nullable()->after('billit_delivery_status');
            $table->string('billit_recipient_provider')->nullable()->after('billit_delivery_info');
            $table->timestamp('billit_synced_at')->nullable()->after('billit_recipient_provider');
            $table->text('billit_error')->nullable()->after('billit_synced_at');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('billit_order_id')->nullable()->unique()->after('stock_booked_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('peppol_identifier')->nullable()->after('vat_number');
            $table->string('peppol_provider')->nullable()->after('peppol_identifier');
            $table->timestamp('peppol_checked_at')->nullable()->after('peppol_provider');
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['peppol_identifier', 'peppol_provider', 'peppol_checked_at']));
        Schema::table('purchase_invoices', fn (Blueprint $table) => $table->dropColumn('billit_order_id'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn([
            'billit_order_id', 'billit_transport', 'billit_sent_at', 'billit_delivery_status', 'billit_delivery_info',
            'billit_recipient_provider', 'billit_synced_at', 'billit_error',
        ]));
    }
};
