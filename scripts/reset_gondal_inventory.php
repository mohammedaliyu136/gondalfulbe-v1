<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$deleteItems = in_array('--delete-items', $argv, true);

$truncateTables = [
    'gondal_inventory_reconciliations',
    'gondal_agent_remittances',
    'gondal_inventory_credits',
    'gondal_inventory_sales',
    'gondal_stock_issues',
    'gondal_warehouse_stocks',
    'gondal_agent_profiles',
];

try {
    DB::beginTransaction();
    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    foreach ($truncateTables as $table) {
        DB::table($table)->truncate();
    }

    if ($deleteItems) {
        DB::table('gondal_inventory_items')->truncate();
    } else {
        DB::table('gondal_inventory_items')->update(['stock_qty' => 0]);
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    DB::commit();
} catch (Throwable $exception) {
    try {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable) {
    }

    DB::rollBack();

    fwrite(STDERR, "Reset failed: {$exception->getMessage()}\n");
    exit(1);
}

$tablesToReport = array_merge(
    ['gondal_inventory_items'],
    $truncateTables,
);

foreach ($tablesToReport as $table) {
    $count = DB::table($table)->count();
    fwrite(STDOUT, $table.': '.$count.PHP_EOL);
}

fwrite(
    STDOUT,
    $deleteItems
        ? "Gondal inventory, warehouse, and reconciliation data cleared, including inventory items.\n"
        : "Gondal inventory, warehouse, and reconciliation data cleared. Inventory items were kept and their stock reset to zero.\n"
);
