<?php

namespace Database\Seeders;

use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\AgentRemittance;
use App\Models\Gondal\ExtensionVisit;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\InventorySale;
use App\Models\Gondal\StockIssue;
use App\Models\Gondal\WarehouseStock;
use App\Models\User;
use App\Models\Vender;
use App\Models\warehouse as Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AgentDashboardTestSeeder extends Seeder
{
    public function run(): void
    {
        if (InventorySale::query()->where('customer_name', 'like', 'Dashboard Seed%')->exists()) {
            $this->command?->info('Agent dashboard test data already exists. Skipping reseed.');

            return;
        }

        DB::transaction(function () {
            $agents = AgentProfile::query()->orderBy('id')->take(2)->get();
            $item = InventoryItem::query()->orderByDesc('stock_qty')->first();
            $warehouse = Warehouse::query()->orderBy('id')->first();
            $warehouseStock = $warehouse
                ? WarehouseStock::query()->where('warehouse_id', $warehouse->id)->where('inventory_item_id', $item?->id)->first()
                : null;
            $farmer = Vender::query()->orderBy('id')->first();
            $systemUserId = User::query()->orderBy('id')->value('id');

            if ($agents->isEmpty() || ! $item || ! $warehouse || ! $warehouseStock || ! $systemUserId) {
                throw new \RuntimeException('Missing agents, item, warehouse, warehouse stock, or user for dashboard seeding.');
            }

            $issuePlans = [
                ['agent_index' => 0, 'qty' => 140, 'days_ago' => 24],
                ['agent_index' => 0, 'qty' => 80, 'days_ago' => 10],
                ['agent_index' => 1, 'qty' => 110, 'days_ago' => 18],
                ['agent_index' => 1, 'qty' => 60, 'days_ago' => 6],
            ];

            foreach ($issuePlans as $index => $plan) {
                $agent = $agents->get($plan['agent_index']);
                if (! $agent) {
                    continue;
                }

                StockIssue::query()->create([
                    'agent_profile_id' => $agent->id,
                    'warehouse_id' => $warehouse->id,
                    'inventory_item_id' => $item->id,
                    'issued_by' => $systemUserId,
                    'issue_reference' => 'SEED-ISS-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'batch_reference' => 'DASH-SEED-'.($index + 1),
                    'quantity_issued' => $plan['qty'],
                    'unit_cost' => (float) $item->unit_price,
                    'issued_on' => Carbon::now()->subDays($plan['days_ago'])->toDateString(),
                    'notes' => 'Dashboard seed stock issue',
                ]);

                $warehouseStock->decrement('quantity', $plan['qty']);
            }

            $paymentMethods = ['Cash', 'Transfer', 'Credit', 'Cash', 'Credit', 'Transfer'];
            $salesSeed = collect(range(0, 17))->map(function (int $offset) use ($agents, $item, $paymentMethods, $farmer) {
                $agent = $agents[$offset % max($agents->count(), 1)];
                $date = Carbon::now()->subDays(20 - $offset)->toDateString();
                $quantity = [3, 5, 4, 6, 2, 4][$offset % 6];
                $paymentMethod = $paymentMethods[$offset % count($paymentMethods)];
                $unitPrice = (float) $item->unit_price;

                return [
                    'agent' => $agent,
                    'date' => $date,
                    'quantity' => $quantity,
                    'payment_method' => $paymentMethod,
                    'unit_price' => $unitPrice,
                    'total_amount' => $quantity * $unitPrice,
                    'vender_id' => $farmer && $agent->id === $agents->first()->id ? $farmer->id : null,
                    'customer_name' => $farmer && $agent->id === $agents->first()->id
                        ? $farmer->name
                        : 'Dashboard Seed Customer '.str_pad((string) ($offset + 1), 2, '0', STR_PAD_LEFT),
                ];
            });

            foreach ($salesSeed as $index => $row) {
                $sale = InventorySale::query()->create([
                    'inventory_item_id' => $item->id,
                    'agent_profile_id' => $row['agent']->id,
                    'extension_visit_id' => null,
                    'vender_id' => $row['vender_id'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'total_amount' => $row['total_amount'],
                    'payment_method' => $row['payment_method'],
                    'credit_allowed_snapshot' => true,
                    'sold_on' => $row['date'],
                    'customer_name' => $row['customer_name'],
                ]);

                if ($row['payment_method'] === 'Credit') {
                    $status = $index % 2 === 0 ? 'open' : 'partial';
                    $outstanding = $status === 'partial' ? round($row['total_amount'] * 0.45, 2) : $row['total_amount'];

                    InventoryCredit::query()->create([
                        'inventory_item_id' => $item->id,
                        'agent_profile_id' => $row['agent']->id,
                        'inventory_sale_id' => $sale->id,
                        'vender_id' => $row['vender_id'],
                        'customer_name' => $row['customer_name'],
                        'amount' => $row['total_amount'],
                        'outstanding_amount' => $outstanding,
                        'status' => $status,
                        'credit_date' => $row['date'],
                        'due_date' => Carbon::parse($row['date'])->addDays(14)->toDateString(),
                    ]);
                }
            }

            $remittancePlans = [
                ['agent_index' => 0, 'days_ago' => 14, 'amount' => 15000, 'mode' => 'weekly'],
                ['agent_index' => 0, 'days_ago' => 7, 'amount' => 22000, 'mode' => 'weekly'],
                ['agent_index' => 0, 'days_ago' => 2, 'amount' => 11000, 'mode' => 'daily'],
                ['agent_index' => 1, 'days_ago' => 12, 'amount' => 9000, 'mode' => 'weekly'],
                ['agent_index' => 1, 'days_ago' => 5, 'amount' => 12500, 'mode' => 'weekly'],
                ['agent_index' => 1, 'days_ago' => 1, 'amount' => 7000, 'mode' => 'daily'],
            ];

            foreach ($remittancePlans as $index => $plan) {
                $agent = $agents->get($plan['agent_index']);
                if (! $agent) {
                    continue;
                }

                AgentRemittance::query()->create([
                    'agent_profile_id' => $agent->id,
                    'received_by' => $systemUserId,
                    'reconciliation_mode' => $plan['mode'],
                    'reference' => 'SEED-RMT-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'amount' => $plan['amount'],
                    'payment_method' => $index % 2 === 0 ? 'transfer' : 'cash',
                    'period_start' => Carbon::now()->subDays($plan['days_ago'] + 6)->toDateString(),
                    'period_end' => Carbon::now()->subDays($plan['days_ago'])->toDateString(),
                    'remitted_at' => Carbon::now()->subDays($plan['days_ago'])->setTime(15, 0),
                    'notes' => 'Dashboard seed remittance',
                ]);
            }

            if ($farmer) {
                foreach (range(0, 5) as $index) {
                    $agent = $agents[$index % max($agents->count(), 1)];
                    $visitDate = Carbon::now()->subDays(13 - ($index * 2))->toDateString();

                    $visit = ExtensionVisit::query()->create([
                        'visit_date' => $visitDate,
                        'farmer_id' => $farmer->id,
                        'agent_profile_id' => $agent->id,
                        'officer_name' => $agent->full_name ?: ($agent->user?->name ?: 'Dashboard Agent'),
                        'topic' => ['Animal health', 'Feed practice', 'Milk hygiene', 'Heat stress', 'Vaccination', 'Housing'][($index % 6)],
                        'performance_score' => 70 + ($index * 4),
                        'notes' => 'Dashboard seed extension visit',
                    ]);

                    if ($index < 3) {
                        InventorySale::query()->create([
                            'inventory_item_id' => $item->id,
                            'agent_profile_id' => $agent->id,
                            'extension_visit_id' => $visit->id,
                            'vender_id' => $farmer->id,
                            'quantity' => 1 + $index,
                            'unit_price' => (float) $item->unit_price,
                            'total_amount' => (1 + $index) * (float) $item->unit_price,
                            'payment_method' => $index % 2 === 0 ? 'Cash' : 'Credit',
                            'credit_allowed_snapshot' => true,
                            'sold_on' => $visitDate,
                            'customer_name' => $farmer->name,
                        ]);
                    }
                }
            }
        });

        $this->command?->info('Agent dashboard test data seeded successfully.');
    }
}
