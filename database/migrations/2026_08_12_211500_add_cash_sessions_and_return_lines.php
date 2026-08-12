<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number');
            $table->string('status')->default('open'); // open|closed
            $table->decimal('opening_float', 18, 2)->default(0);
            $table->decimal('closing_counted', 18, 2)->nullable();
            $table->decimal('expected_cash', 18, 2)->nullable();
            $table->decimal('variance', 18, 2)->nullable();
            $table->char('currency_code', 3)->default('CDF');
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignUuid('cash_register_session_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('cash_register_sessions')
                ->nullOnDelete();
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->decimal('quantity_returned', 18, 3)->default(0)->after('quantity');
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('number');
            $table->string('reason')->nullable()->after('restock');
            $table->string('refund_method')->default('cash')->after('reason');
            $table->foreignUuid('cash_register_session_id')
                ->nullable()
                ->after('sale_id')
                ->constrained('cash_register_sessions')
                ->nullOnDelete();
        });

        Schema::create('sale_return_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_lines');

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_session_id');
            $table->dropColumn(['status', 'reason', 'refund_method']);
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropColumn('quantity_returned');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_session_id');
        });

        Schema::dropIfExists('cash_register_sessions');
    }
};
