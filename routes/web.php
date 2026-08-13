<?php

use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\Audit\AuditController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Document\DocumentController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\OwnerReportController;
use App\Http\Controllers\InventoryCount\StockCountController;
use App\Http\Controllers\Pos\CashRegisterSessionController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleReturnController;
use App\Http\Controllers\Stock\BatchController;
use App\Http\Controllers\Stock\StockAdjustmentController;
use App\Http\Controllers\Stock\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
Route::post('/two-factor/challenge', [TwoFactorController::class, 'verify'])->name('two-factor.verify');

Route::middleware('auth')->group(function () {
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // POS & ventes
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
    Route::get('/pos/sessions', [CashRegisterSessionController::class, 'index'])->name('pos.sessions.index');
    Route::post('/pos/sessions', [CashRegisterSessionController::class, 'store'])->name('pos.sessions.store');
    Route::get('/pos/sessions/{session}', [CashRegisterSessionController::class, 'show'])->name('pos.sessions.show');
    Route::post('/pos/sessions/{session}/close', [CashRegisterSessionController::class, 'close'])->name('pos.sessions.close');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/export', [SaleController::class, 'export'])->name('sales.export');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/returns', [SaleReturnController::class, 'store'])->name('sales.returns.store');

    // Catalogue
    Route::get('/catalog/products', [ProductController::class, 'index'])->name('catalog.products.index');
    Route::get('/catalog/products/create', [ProductController::class, 'create'])->name('catalog.products.create');
    Route::post('/catalog/products', [ProductController::class, 'store'])->name('catalog.products.store');
    Route::get('/catalog/products/export', [ProductController::class, 'export'])->name('catalog.products.export');
    Route::post('/catalog/products/import', [ProductController::class, 'import'])->name('catalog.products.import');
    Route::get('/catalog/products/{product}/edit', [ProductController::class, 'edit'])->name('catalog.products.edit');
    Route::put('/catalog/products/{product}', [ProductController::class, 'update'])->name('catalog.products.update');
    Route::delete('/catalog/products/{product}', [ProductController::class, 'destroy'])->name('catalog.products.destroy');

    // Stock
    Route::get('/stock/batches', [BatchController::class, 'index'])->name('stock.batches.index');
    Route::get('/stock/movements', [StockMovementController::class, 'index'])->name('stock.movements.index');
    Route::get('/stock/movements/export', [StockMovementController::class, 'export'])->name('stock.movements.export');
    Route::get('/stock/adjustments/create', [StockAdjustmentController::class, 'create'])->name('stock.adjustments.create');
    Route::post('/stock/adjustments', [StockAdjustmentController::class, 'store'])->name('stock.adjustments.store');

    // Achats
    Route::get('/purchasing/suppliers', [SupplierController::class, 'index'])->name('purchasing.suppliers.index');
    Route::post('/purchasing/suppliers', [SupplierController::class, 'store'])->name('purchasing.suppliers.store');
    Route::put('/purchasing/suppliers/{supplier}', [SupplierController::class, 'update'])->name('purchasing.suppliers.update');
    Route::delete('/purchasing/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('purchasing.suppliers.destroy');

    Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])->name('purchasing.orders.index');
    Route::get('/purchasing/orders/create', [PurchaseOrderController::class, 'create'])->name('purchasing.orders.create');
    Route::post('/purchasing/orders', [PurchaseOrderController::class, 'store'])->name('purchasing.orders.store');
    Route::get('/purchasing/orders/{order}', [PurchaseOrderController::class, 'show'])->name('purchasing.orders.show');
    Route::post('/purchasing/orders/{order}/submit', [PurchaseOrderController::class, 'submit'])->name('purchasing.orders.submit');
    Route::post('/purchasing/orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchasing.orders.approve');
    Route::post('/purchasing/orders/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchasing.orders.receive');

    // Inventaires
    Route::get('/inventory/counts', [StockCountController::class, 'index'])->name('inventory.counts.index');
    Route::get('/inventory/counts/create', [StockCountController::class, 'create'])->name('inventory.counts.create');
    Route::post('/inventory/counts', [StockCountController::class, 'store'])->name('inventory.counts.store');
    Route::get('/inventory/counts/{count}', [StockCountController::class, 'show'])->name('inventory.counts.show');
    Route::post('/inventory/counts/{count}/submit', [StockCountController::class, 'submit'])->name('inventory.counts.submit');
    Route::post('/inventory/counts/{count}/validate', [StockCountController::class, 'validateCount'])->name('inventory.counts.validate');

    // Finance
    Route::get('/finance', FinanceReportController::class)->name('finance.index');
    Route::get('/finance/expenses', [ExpenseController::class, 'index'])->name('finance.expenses.index');
    Route::post('/finance/expenses', [ExpenseController::class, 'store'])->name('finance.expenses.store');
    Route::get('/finance/reports/{report}/download', [OwnerReportController::class, 'download'])->name('finance.reports.download');
    Route::post('/finance/reports/daily', [OwnerReportController::class, 'generateDaily'])->name('finance.reports.daily');
    Route::post('/finance/reports/monthly', [OwnerReportController::class, 'generateMonthly'])->name('finance.reports.monthly');

    // Documents
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/{document}/reprocess', [DocumentController::class, 'reprocess'])->name('documents.reprocess');

    // Audit
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

    Route::get('/settings/sites', [\App\Http\Controllers\Settings\SiteController::class, 'index'])->name('settings.sites.index');
    Route::post('/settings/sites', [\App\Http\Controllers\Settings\SiteController::class, 'store'])->name('settings.sites.store');

    // Alertes
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
});

require __DIR__.'/auth.php';
