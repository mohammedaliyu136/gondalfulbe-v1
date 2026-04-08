<?php

namespace Tests\Feature;

use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\BusinessRule;
use App\Models\Gondal\AgentRemittance;
use App\Models\Gondal\Community;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\Obligation;
use App\Models\Gondal\OneStopShop;
use App\Models\Gondal\ProgramFarmerEnrollment;
use App\Models\Gondal\StockIssue;
use App\Models\Gondal\WarehouseStock;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use App\Models\warehouse as Warehouse;
use App\Services\Gondal\InventoryWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GondalInventoryWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_credit_sale_creates_credit_entry_and_reduces_agent_stock(): void
    {
        $service = app(InventoryWorkflowService::class);
        $suffix = uniqid();
        $community = Community::query()->create([
            'name' => 'Sabon Pegi',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-SABON-PEGI-'.$suffix,
            'status' => 'active',
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'Jimeta OSS',
            'code' => 'OSS-JIMETA-'.$suffix,
            'community_id' => $community->id,
            'status' => 'active',
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Cattle Feed',
            'sku' => 'SKU-0001-'.$suffix,
            'stock_qty' => 0,
            'unit_price' => 250,
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'project_name' => 'Inventory Program '.$suffix,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);
        $agent = AgentProfile::query()->create([
            'project_id' => $project->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-001-'.$suffix,
            'agent_type' => 'farmer',
            'first_name' => 'Aisha',
            'last_name' => 'Musa',
            'gender' => 'female',
            'phone_number' => '08011111111',
            'email' => 'aisha-'.$suffix.'@example.com',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'community_id' => $community->id,
            'community' => $community->name,
            'residential_address' => 'Yola',
            'assigned_communities' => [$community->name],
            'reconciliation_frequency' => 'daily',
            'settlement_mode' => 'consignment',
            'credit_sales_enabled' => true,
            'status' => 'active',
        ]);
        StockIssue::query()->create([
            'agent_profile_id' => $agent->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'issue_stage' => 'oss_to_agent',
            'inventory_item_id' => $item->id,
            'issue_reference' => 'OSS-TEST-1',
            'quantity_issued' => 10,
            'unit_cost' => 150,
            'issued_on' => now()->toDateString(),
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-001-'.$suffix,
            'name' => 'Farmer One',
            'email' => 'farmer-'.$suffix.'@example.com',
            'contact' => '08022222222',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        ProgramFarmerEnrollment::query()->create([
            'project_id' => $project->id,
            'farmer_id' => $farmer->id,
            'enrolled_by' => 1,
            'starts_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        $sale = $service->createInventorySale([
            'inventory_item_id' => $item->id,
            'agent_profile_id' => $agent->id,
            'vender_id' => $farmer->id,
            'quantity' => 2,
            'unit_price' => 250,
            'payment_method' => 'Credit',
            'sold_on' => now()->toDateString(),
        ]);

        $this->assertSame(500.0, (float) $sale->total_amount);
        $this->assertSame($project->id, $sale->project_id);
        $this->assertDatabaseHas('gondal_inventory_credits', [
            'inventory_sale_id' => $sale->id,
            'agent_profile_id' => $agent->id,
            'project_id' => $project->id,
            'status' => 'open',
            'amount' => 500.00,
            'outstanding_amount' => 500.00,
        ]);
        $this->assertDatabaseHas('gondal_obligations', [
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'principal_amount' => 500.00,
        ]);
        $this->assertSame(8.0, $service->availableAgentItemStock($agent->id, $item->id));
    }

    public function test_credit_sale_uses_database_obligation_defaults(): void
    {
        $service = app(InventoryWorkflowService::class);
        $suffix = uniqid();

        BusinessRule::query()
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('rule_key', 'inventory.credit_obligation_defaults')
            ->firstOrFail()
            ->update([
                'rule_value' => [
                    'priority' => 7,
                    'max_deduction_percent' => 22,
                    'payout_floor_amount' => 150,
                    'due_days' => 30,
                ],
            ]);

        $community = Community::query()->create([
            'name' => 'Namtari',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-NAMTARI-'.$suffix,
            'status' => 'active',
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'Namtari OSS',
            'code' => 'OSS-NAMTARI-'.$suffix,
            'community_id' => $community->id,
            'status' => 'active',
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Hay Bale',
            'sku' => 'SKU-OBL-'.$suffix,
            'stock_qty' => 0,
            'unit_price' => 300,
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'project_name' => 'Credit Rule Program '.$suffix,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);
        $agent = AgentProfile::query()->create([
            'project_id' => $project->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-OBL-'.$suffix,
            'agent_type' => 'farmer',
            'first_name' => 'Maryam',
            'last_name' => 'Usman',
            'gender' => 'female',
            'phone_number' => '08077777777',
            'email' => 'maryam-'.$suffix.'@example.com',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'community_id' => $community->id,
            'community' => $community->name,
            'residential_address' => 'Yola',
            'assigned_communities' => [$community->name],
            'reconciliation_frequency' => 'daily',
            'settlement_mode' => 'consignment',
            'credit_sales_enabled' => true,
            'status' => 'active',
        ]);
        StockIssue::query()->create([
            'agent_profile_id' => $agent->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'issue_stage' => 'oss_to_agent',
            'inventory_item_id' => $item->id,
            'issue_reference' => 'OSS-OBL-1',
            'quantity_issued' => 5,
            'unit_cost' => 180,
            'issued_on' => now()->toDateString(),
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-OBL-'.$suffix,
            'name' => 'Rule Farmer',
            'contact' => '08088888888',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        ProgramFarmerEnrollment::query()->create([
            'project_id' => $project->id,
            'farmer_id' => $farmer->id,
            'enrolled_by' => 1,
            'starts_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        $soldOn = now()->toDateString();
        $sale = $service->createInventorySale([
            'inventory_item_id' => $item->id,
            'agent_profile_id' => $agent->id,
            'vender_id' => $farmer->id,
            'quantity' => 1,
            'unit_price' => 300,
            'payment_method' => 'Credit',
            'sold_on' => $soldOn,
        ]);

        $obligation = Obligation::query()
            ->where('farmer_id', $farmer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(7, $obligation->priority);
        $this->assertSame($project->id, $sale->project_id);
        $this->assertSame($project->id, $obligation->project_id);
        $this->assertSame(22.0, (float) $obligation->max_deduction_percent);
        $this->assertSame(150.0, (float) $obligation->payout_floor_amount);
        $this->assertSame(Carbon::parse($soldOn)->addDays(30)->toDateString(), optional($obligation->due_date)->toDateString());
    }

    public function test_reconciliation_resolution_creates_adjustment_and_cash_liability(): void
    {
        $service = app(InventoryWorkflowService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Mbamba',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-MBAMBA-'.$suffix,
            'status' => 'active',
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'OSS Mbamba',
            'code' => 'OSS-MBAMBA-'.$suffix,
            'community_id' => $community->id,
            'status' => 'active',
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Mineral Mix',
            'sku' => 'SKU-0002-'.$suffix,
            'stock_qty' => 0,
            'unit_price' => 100,
            'status' => 'active',
        ]);
        $agent = AgentProfile::query()->create([
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-002-'.$suffix,
            'agent_type' => 'farmer',
            'first_name' => 'Bello',
            'last_name' => 'Adam',
            'gender' => 'male',
            'phone_number' => '08033333333',
            'email' => 'bello-'.$suffix.'@example.com',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'community_id' => $community->id,
            'community' => $community->name,
            'residential_address' => 'Yola',
            'assigned_communities' => [$community->name],
            'reconciliation_frequency' => 'daily',
            'settlement_mode' => 'consignment',
            'credit_sales_enabled' => true,
            'status' => 'active',
        ]);
        StockIssue::query()->create([
            'agent_profile_id' => $agent->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'issue_stage' => 'oss_to_agent',
            'inventory_item_id' => $item->id,
            'issue_reference' => 'OSS-TEST-2',
            'quantity_issued' => 10,
            'unit_cost' => 60,
            'issued_on' => now()->subDay()->toDateString(),
        ]);

        $service->createInventorySale([
            'inventory_item_id' => $item->id,
            'agent_profile_id' => $agent->id,
            'quantity' => 4,
            'unit_price' => 100,
            'payment_method' => 'Cash',
            'sold_on' => now()->toDateString(),
            'customer_name' => 'Walk-in Buyer',
        ]);

        AgentRemittance::query()->create([
            'agent_profile_id' => $agent->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'received_by' => $actor->id,
            'reconciliation_mode' => 'daily',
            'reference' => 'RMT-TEST-1',
            'amount' => 350,
            'payment_method' => 'cash',
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'remitted_at' => now(),
        ]);

        $reconciliation = $service->createReconciliation([
            'agent_profile_id' => $agent->id,
            'inventory_item_id' => $item->id,
            'reconciliation_mode' => 'daily',
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'counted_stock_qty' => 5,
            'agent_notes' => 'Short by one unit and cash under-remitted',
        ], $actor);

        $this->assertSame('under_review', $reconciliation->status);

        $resolved = $service->resolveReconciliation($reconciliation, [
            'action' => 'approve_with_variance',
            'review_notes' => 'Variance accepted after review.',
        ], $actor);

        $this->assertSame('approved_with_variance', $resolved->status);
        $this->assertDatabaseHas('gondal_agent_inventory_adjustments', [
            'reconciliation_id' => $reconciliation->id,
            'quantity_delta' => -1.00,
        ]);
        $this->assertDatabaseHas('gondal_agent_cash_liabilities', [
            'reconciliation_id' => $reconciliation->id,
            'amount' => 50.00,
            'status' => 'open',
        ]);
    }

    public function test_stock_can_move_from_warehouse_to_one_stop_shop_and_to_agent(): void
    {
        $service = app(InventoryWorkflowService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Karewa',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'code' => 'COM-KAREWA-'.$suffix,
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'name' => 'Central Warehouse',
            'address' => 'Yola',
            'city' => 'Yola',
            'city_zip' => '640001',
            'created_by' => $actor->id,
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'Karewa OSS',
            'code' => 'OSS-KAREWA-'.$suffix,
            'warehouse_id' => $warehouse->id,
            'community_id' => $community->id,
            'status' => 'active',
            'created_by' => $actor->id,
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Dewormer',
            'sku' => 'SKU-0003-'.$suffix,
            'stock_qty' => 20,
            'unit_price' => 500,
            'status' => 'active',
        ]);
        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 20,
            'reorder_level' => 2,
            'created_by' => $actor->id,
        ]);
        $agent = AgentProfile::query()->create([
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-003-'.$suffix,
            'agent_type' => 'employee',
            'first_name' => 'Musa',
            'last_name' => 'Haruna',
            'gender' => 'male',
            'phone_number' => '08044444444',
            'email' => 'musa@example.com',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'community_id' => $community->id,
            'community' => $community->name,
            'residential_address' => 'Karewa',
            'assigned_communities' => [$community->name],
            'reconciliation_frequency' => 'daily',
            'settlement_mode' => 'consignment',
            'status' => 'active',
        ]);

        $service->createWarehouseToOneStopShopIssue([
            'warehouse_id' => $warehouse->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'inventory_item_id' => $item->id,
            'quantity_issued' => 8,
            'unit_cost' => 300,
            'issued_on' => now()->toDateString(),
        ], $actor);

        $service->createOneStopShopToAgentIssue([
            'agent_profile_id' => $agent->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'inventory_item_id' => $item->id,
            'quantity_issued' => 5,
            'unit_cost' => 300,
            'issued_on' => now()->toDateString(),
        ], $actor);

        $this->assertDatabaseHas('gondal_warehouse_stocks', [
            'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 12.00,
        ]);
        $this->assertDatabaseHas('gondal_one_stop_shop_stocks', [
            'one_stop_shop_id' => $oneStopShop->id,
            'inventory_item_id' => $item->id,
            'quantity' => 3.00,
        ]);
        $this->assertSame(5.0, $service->availableAgentItemStock($agent->id, $item->id));
    }
}
