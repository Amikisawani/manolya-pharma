<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('default_currency', 3)->default('XAF');
            $table->string('timezone')->default('Africa/Brazzaville');
            $table->string('locale', 10)->default('fr');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('address')->nullable();
            $table->boolean('is_main')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('login_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('success')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sessions_registry', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('sku');
            $table->string('commercial_name');
            $table->string('generic_name')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->string('qr_payload')->nullable();
            $table->string('manufacturer')->nullable();
            $table->decimal('purchase_price', 18, 2)->default(0);
            $table->decimal('sale_price', 18, 2)->default(0);
            $table->char('currency_code', 3)->default('XAF');
            $table->decimal('min_stock', 18, 3)->default(0);
            $table->decimal('critical_stock', 18, 3)->default(0);
            $table->string('allocation_strategy')->default('fefo');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number');
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('quantity_on_hand', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->char('currency_code', 3)->default('XAF');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
            $table->unique(['tenant_id', 'product_id', 'warehouse_id', 'lot_number'], 'batches_unique_lot');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_delta', 18, 3);
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('status')->default('draft');
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('expected_at')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->char('currency_code', 3)->default('XAF');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_ordered', 18, 3);
            $table->decimal('quantity_received', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lot_number');
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignUuid('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('cost_total', 18, 2)->default(0);
            $table->decimal('profit_total', 18, 2)->default(0);
            $table->char('currency_code', 3)->default('XAF');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('provider')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('provider_ref')->nullable();
            $table->string('status')->default('completed');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->boolean('restock')->default(true);
            $table->decimal('refund_total', 18, 2)->default(0);
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('open');
            $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('expected_qty', 18, 3)->default(0);
            $table->decimal('counted_qty', 18, 3)->nullable();
            $table->decimal('variance', 18, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->decimal('amount', 18, 2);
            $table->char('currency_code', 3)->default('XAF');
            $table->timestamp('spent_at');
            $table->text('description')->nullable();
            $table->uuid('document_id')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->unsignedInteger('current_version')->default(1);
            $table->longText('search_text')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->string('delete_reason')->nullable();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk_path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('ocr_status')->default('pending');
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('severity')->default('medium');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('raised_at');
            $table->foreignUuid('acked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'type']);
        });

        Schema::create('audit_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->uuid('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('disk_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'report_runs', 'audit_records', 'alerts', 'document_versions', 'documents',
            'expenses', 'stock_count_lines', 'stock_counts', 'sale_returns', 'sale_payments',
            'sale_lines', 'sales', 'goods_receipt_lines', 'goods_receipts', 'purchase_order_lines',
            'purchase_orders', 'stock_adjustments', 'stock_movements', 'batches', 'products',
            'suppliers', 'categories', 'sessions_registry', 'login_histories', 'warehouses', 'sites',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn([
                'phone', 'is_active', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'locked_until', 'failed_login_attempts',
                'deleted_at', 'deleted_by', 'delete_reason',
            ]);
        });

        Schema::dropIfExists('tenants');
    }
};
