<?php

use App\Http\Controllers\Gondal\GondalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'XSS', 'revalidate'])
    ->group(function (): void {
        Route::get('/gondal-dashboard', [GondalController::class, 'dashboard'])->name('gondal.dashboard');
        Route::get('/gondal-dashboard/standard', [GondalController::class, 'standardDashboard'])->name('gondal.dashboard.standard');
        Route::get('/gondal-dashboard/role/{dashboard}', [GondalController::class, 'roleDashboard'])->name('gondal.dashboard.role');
        Route::redirect('/gondal', '/gondal-dashboard');

        Route::prefix('gondal')
            ->name('gondal.')
            ->group(function (): void {

                Route::get('/farmers', [GondalController::class, 'farmers'])->name('farmers');
                Route::post('/farmers', [GondalController::class, 'storeFarmer'])->name('farmers.store');
                Route::put('/farmers/{farmer}', [GondalController::class, 'updateFarmer'])->name('farmers.update');
                Route::delete('/farmers/{farmer}', [GondalController::class, 'destroyFarmer'])->name('farmers.destroy');

                Route::get('/cooperatives', [GondalController::class, 'cooperatives'])->name('cooperatives');
                Route::post('/cooperatives', [GondalController::class, 'storeCooperative'])->name('cooperatives.store');
                Route::get('/cooperatives/{id}', [GondalController::class, 'cooperativeDetail'])->name('cooperatives.show');

                Route::get('/milk-collection', [GondalController::class, 'milkCollection'])->name('milk-collection');
                Route::post('/milk-collection', [GondalController::class, 'storeMilkCollection'])->name('milk-collection.store');

                Route::get('/logistics', [GondalController::class, 'logistics'])->name('logistics');
                Route::get('/logistics/export.csv', [GondalController::class, 'exportLogistics'])->name('logistics.export');
                Route::post('/logistics/import', [GondalController::class, 'importLogistics'])->name('logistics.import');
                Route::post('/logistics/trips', [GondalController::class, 'storeLogisticsTrip'])->name('logistics.trips.store');
                Route::post('/logistics/riders', [GondalController::class, 'storeLogisticsRider'])->name('logistics.riders.store');

                Route::get('/operations', [GondalController::class, 'operations'])->name('operations');
                Route::get('/operations/export.csv', [GondalController::class, 'exportOperations'])->name('operations.export');
                Route::post('/operations/import', [GondalController::class, 'importOperations'])->name('operations.import');
                Route::post('/operations', [GondalController::class, 'storeOperationCost'])->name('operations.store');
                Route::post('/operations/{id}/approve', [GondalController::class, 'approveOperationCost'])->name('operations.approve');

                Route::get('/requisitions', [GondalController::class, 'requisitions'])->name('requisitions');
                Route::get('/requisitions/export.csv', [GondalController::class, 'exportRequisitions'])->name('requisitions.export');
                Route::post('/requisitions/import', [GondalController::class, 'importRequisitions'])->name('requisitions.import');
                Route::post('/requisitions', [GondalController::class, 'storeRequisition'])->name('requisitions.store');
                Route::get('/requisitions/{id}', [GondalController::class, 'requisitionDetail'])->name('requisitions.show');
                Route::post('/requisitions/{id}/approve', [GondalController::class, 'approveRequisition'])->name('requisitions.approve');
                Route::post('/requisitions/{id}/reject', [GondalController::class, 'rejectRequisition'])->name('requisitions.reject');

                Route::get('/payments', [GondalController::class, 'payments'])->name('payments');
                Route::get('/payments/export.csv', [GondalController::class, 'exportPayments'])->name('payments.export');
                Route::post('/payments/import', [GondalController::class, 'importPayments'])->name('payments.import');
                Route::post('/payments/batches', [GondalController::class, 'storePaymentBatch'])->name('payments.batches.store');
                Route::post('/payments/batches/{id}/process', [GondalController::class, 'processPaymentBatch'])->name('payments.process');

                Route::get('/inventory', [GondalController::class, 'inventory'])->name('inventory');
                Route::get('/inventory/export.csv', [GondalController::class, 'exportInventory'])->name('inventory.export');
                Route::post('/inventory/import', [GondalController::class, 'importInventory'])->name('inventory.import');
                Route::post('/inventory/sales', [GondalController::class, 'storeInventorySale'])->name('inventory.sales.store');
                Route::post('/inventory/items', [GondalController::class, 'storeInventoryItem'])->name('inventory.items.store');
                Route::post('/inventory/credits', [GondalController::class, 'storeInventoryCredit'])->name('inventory.credits.store');
                Route::post('/inventory/agents', [GondalController::class, 'storeInventoryAgent'])->name('inventory.agents.store');
                Route::post('/inventory/issues', [GondalController::class, 'storeInventoryStockIssue'])->name('inventory.issues.store');
                Route::post('/inventory/remittances', [GondalController::class, 'storeInventoryRemittance'])->name('inventory.remittances.store');
                Route::post('/inventory/reconciliations', [GondalController::class, 'storeInventoryReconciliation'])->name('inventory.reconciliations.store');
                Route::get('/inventory/reconciliations/{id}', [GondalController::class, 'showInventoryReconciliation'])->name('inventory.reconciliations.show');
                Route::post('/inventory/reconciliations/{id}/resolve', [GondalController::class, 'resolveInventoryReconciliation'])->name('inventory.reconciliations.resolve');

                Route::get('/warehouse', [GondalController::class, 'warehouse'])->name('warehouse');
                Route::post('/warehouse', [GondalController::class, 'storeWarehouse'])->name('warehouse.store');
                Route::post('/warehouse/stocks', [GondalController::class, 'storeWarehouseStock'])->name('warehouse.stocks.store');
                Route::post('/warehouse/issues', [GondalController::class, 'storeWarehouseIssue'])->name('warehouse.issues.store');

                Route::get('/extension', [GondalController::class, 'extension'])->name('extension');
                Route::get('/extension/export.csv', [GondalController::class, 'exportExtension'])->name('extension.export');
                Route::post('/extension/import', [GondalController::class, 'importExtension'])->name('extension.import');
                Route::post('/extension/visits', [GondalController::class, 'storeExtensionVisit'])->name('extension.visits.store');
                Route::post('/extension/trainings', [GondalController::class, 'storeExtensionTraining'])->name('extension.trainings.store');

                Route::get('/reports', [GondalController::class, 'reports'])->name('reports');
                Route::get('/reports/export.csv', [GondalController::class, 'exportReports'])->name('reports.export');
                Route::post('/reports/import', [GondalController::class, 'importReports'])->name('reports.import');

                Route::get('/admin/audit-log', [GondalController::class, 'adminAuditLog'])->name('admin.audit-log');
                Route::get('/admin/approval-rules', [GondalController::class, 'adminApprovalRules'])->name('admin.approval-rules');
                Route::post('/admin/approval-rules', [GondalController::class, 'storeApprovalRule'])->name('admin.approval-rules.store');
                Route::put('/admin/approval-rules/{id}', [GondalController::class, 'updateApprovalRule'])->name('admin.approval-rules.update');
            });
    });
