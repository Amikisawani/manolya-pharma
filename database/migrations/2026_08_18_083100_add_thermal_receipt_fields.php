<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('legal_rccm')->nullable()->after('email');
            $table->string('legal_id_nat')->nullable()->after('legal_rccm');
            $table->string('legal_nif')->nullable()->after('legal_id_nat');
            $table->string('logo_path')->nullable()->after('legal_nif');
            $table->text('receipt_footer')->nullable()->after('logo_path');
            $table->text('receipt_return_policy')->nullable()->after('receipt_footer');
            $table->boolean('receipt_auto_print')->default(false)->after('receipt_return_policy');
            $table->boolean('receipt_show_qr')->default(true)->after('receipt_auto_print');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('cashier_id');
            $table->string('note')->nullable()->after('customer_name');
            $table->decimal('amount_tendered', 18, 2)->nullable()->after('grand_total');
            $table->decimal('change_given', 18, 2)->nullable()->after('amount_tendered');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'email',
                'legal_rccm',
                'legal_id_nat',
                'legal_nif',
                'logo_path',
                'receipt_footer',
                'receipt_return_policy',
                'receipt_auto_print',
                'receipt_show_qr',
            ]);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'note',
                'amount_tendered',
                'change_given',
            ]);
        });
    }
};
