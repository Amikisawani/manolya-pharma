<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.import', 'products.export',
            'batches.view', 'batches.create', 'batches.update', 'batches.adjust',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.submit', 'purchase_orders.approve', 'purchase_orders.receive', 'purchase_orders.pay',
            'sales.pos', 'sales.view', 'sales.discount', 'sales.refund', 'sales.void',
            'stock_counts.view', 'stock_counts.create', 'stock_counts.count', 'stock_counts.validate',
            'expenses.view', 'expenses.create', 'expenses.update',
            'finance.reports.view',
            'cash_sessions.report', 'cash_sessions.approve',
            'documents.view', 'documents.upload', 'documents.delete',
            'alerts.view', 'alerts.ack',
            'audit.view', 'audit.export',
            'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.force_logout',
            'roles.manage',
            'sites.manage',
            'reports.daily.view', 'reports.monthly.view',
            'settings.manage',
            'tenants.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $all = Permission::all();

        Role::findOrCreate('super_admin')->syncPermissions($all);
        Role::findOrCreate('owner')->syncPermissions(
            $all->where('name', '!=', 'tenants.manage')
        );
        Role::findOrCreate('pharmacist')->syncPermissions([
            'dashboard.view',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.import', 'products.export',
            'batches.view', 'batches.create', 'batches.update', 'batches.adjust',
            'suppliers.view', 'suppliers.create', 'suppliers.update',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.submit', 'purchase_orders.approve', 'purchase_orders.receive',
            'sales.pos', 'sales.view', 'sales.discount', 'sales.refund', 'sales.void',
            'stock_counts.view', 'stock_counts.create', 'stock_counts.count', 'stock_counts.validate',
            'documents.view', 'documents.upload',
            'alerts.view', 'alerts.ack',
            'audit.view',
            'reports.daily.view', 'reports.monthly.view',
            'cash_sessions.report', 'cash_sessions.approve',
        ]);
        Role::findOrCreate('stock_manager')->syncPermissions([
            'dashboard.view',
            'products.view', 'products.create', 'products.update',
            'batches.view', 'batches.create', 'batches.update', 'batches.adjust',
            'suppliers.view', 'suppliers.create', 'suppliers.update',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.submit', 'purchase_orders.receive',
            'stock_counts.view', 'stock_counts.create', 'stock_counts.count',
            'documents.view',
            'alerts.view', 'alerts.ack',
        ]);
        Role::findOrCreate('cashier')->syncPermissions([
            'dashboard.view',
            'products.view',
            'sales.pos', 'sales.view', 'sales.discount',
            'alerts.view',
        ]);
        Role::findOrCreate('accountant')->syncPermissions([
            'dashboard.view',
            'products.view',
            'sales.view',
            'expenses.view', 'expenses.create', 'expenses.update',
            'finance.reports.view',
            'documents.view', 'documents.upload',
            'audit.view',
            'reports.daily.view', 'reports.monthly.view',
            'cash_sessions.report',
        ]);
        Role::findOrCreate('auditor')->syncPermissions([
            'dashboard.view',
            'products.view',
            'batches.view',
            'suppliers.view',
            'purchase_orders.view',
            'sales.view',
            'stock_counts.view',
            'expenses.view',
            'finance.reports.view',
            'documents.view',
            'alerts.view',
            'audit.view', 'audit.export',
            'users.view',
            'reports.daily.view', 'reports.monthly.view',
            'cash_sessions.report',
        ]);
    }
}
