<?php

namespace Tests\Feature;

use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\Community;
use App\Models\Gondal\GondalOrder;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\OneStopShop;
use App\Models\Gondal\PaymentBatch;
use App\Models\Gondal\ProgramAgentAssignment;
use App\Models\Gondal\ProgramFarmerEnrollment;
use App\Models\Gondal\SettlementRun;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use App\Services\Gondal\ProgramScopeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\MilkCollection\Models\MilkCollection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GondalProgramScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sponsor_scope_limits_visible_farmers_and_milk_collections(): void
    {
        $scopeService = app(ProgramScopeService::class);

        $sponsor = User::factory()->create([
            'type' => 'client',
        ]);
        $outsider = User::factory()->create([
            'type' => 'client',
        ]);

        $project = Project::query()->create([
            'project_name' => 'Feed Support Program',
            'client_id' => $sponsor->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);

        $community = Community::query()->create([
            'name' => 'Sabon Gari',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-SABON-GARI',
            'status' => 'active',
        ]);
        $otherCommunity = Community::query()->create([
            'name' => 'Karewa',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'code' => 'COM-KAREWA',
            'status' => 'active',
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'Jimeta OSS',
            'code' => 'OSS-JIMETA-2',
            'community_id' => $community->id,
            'status' => 'active',
        ]);

        $agent = AgentProfile::query()->create([
            'sponsor_user_id' => $sponsor->id,
            'project_id' => $project->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-SPONSOR-1',
            'agent_type' => 'farmer',
            'first_name' => 'Amina',
            'last_name' => 'Bello',
            'gender' => 'female',
            'phone_number' => '08012345678',
            'email' => 'amina.sponsor@example.com',
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

        $visibleFarmer = Vender::query()->create([
            'vender_id' => 'F-SP-001',
            'name' => 'Scoped Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $hiddenFarmer = Vender::query()->create([
            'vender_id' => 'F-SP-002',
            'name' => 'Hidden Farmer',
            'created_by' => 1,
            'community_id' => $otherCommunity->id,
            'community' => $otherCommunity->name,
        ]);

        $scopeService->assignAgentToProject($project, $agent, $sponsor);
        $scopeService->enrollFarmerInProject($project, $visibleFarmer, $sponsor);

        $visibleCollection = MilkCollection::query()->create([
            'batch_id' => 'MC-SCOPE-1',
            'mcc_id' => 'MCC-1',
            'farmer_id' => $visibleFarmer->id,
            'project_id' => $project->id,
            'quantity' => 50,
            'fat_percentage' => 4.2,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $sponsor->id,
            'collection_date' => now(),
        ]);
        $hiddenCollection = MilkCollection::query()->create([
            'batch_id' => 'MC-SCOPE-2',
            'mcc_id' => 'MCC-2',
            'farmer_id' => $hiddenFarmer->id,
            'quantity' => 40,
            'fat_percentage' => 4.2,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $outsider->id,
            'collection_date' => now(),
        ]);

        $farmerIds = $scopeService->scopedFarmerIds($sponsor);

        $this->assertContains($visibleFarmer->id, $farmerIds);
        $this->assertNotContains($hiddenFarmer->id, $farmerIds);
        $this->assertTrue($sponsor->can('view', $project));
        $this->assertTrue($sponsor->can('view', $agent));
        $this->assertTrue($sponsor->can('view', $visibleFarmer));
        $this->assertFalse($sponsor->can('view', $hiddenFarmer));
        $this->assertTrue($sponsor->can('view', $visibleCollection));
        $this->assertFalse($sponsor->can('view', $hiddenCollection));
    }

    public function test_reassigning_an_agent_closes_previous_active_assignment_and_prefers_assignment_history(): void
    {
        $scopeService = app(ProgramScopeService::class);

        $actor = User::factory()->create([
            'type' => 'client',
        ]);

        $firstProject = Project::query()->create([
            'project_name' => 'First Program',
            'client_id' => $actor->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);
        $secondProject = Project::query()->create([
            'project_name' => 'Second Program',
            'client_id' => $actor->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);

        $community = Community::query()->create([
            'name' => 'Bole',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-BOLE',
            'status' => 'active',
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'Bole OSS',
            'code' => 'OSS-BOLE-1',
            'community_id' => $community->id,
            'status' => 'active',
        ]);

        $agent = AgentProfile::query()->create([
            'sponsor_user_id' => $actor->id,
            'project_id' => $firstProject->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-REASSIGN-1',
            'agent_type' => 'farmer',
            'first_name' => 'Musa',
            'last_name' => 'Abba',
            'gender' => 'male',
            'phone_number' => '08022222222',
            'email' => 'musa.reassign@example.com',
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

        $scopeService->assignAgentToProject($firstProject, $agent, $actor);
        $scopeService->assignAgentToProject($secondProject, $agent, $actor);

        $firstAssignment = ProgramAgentAssignment::query()
            ->where('project_id', $firstProject->id)
            ->where('agent_profile_id', $agent->id)
            ->firstOrFail();
        $secondAssignment = ProgramAgentAssignment::query()
            ->where('project_id', $secondProject->id)
            ->where('agent_profile_id', $agent->id)
            ->firstOrFail();

        $this->assertSame('inactive', $firstAssignment->status);
        $this->assertSame(now()->toDateString(), optional($firstAssignment->ends_on)->toDateString());
        $this->assertSame('active', $secondAssignment->status);
        $this->assertNull($secondAssignment->ends_on);
        $this->assertSame($secondProject->id, $scopeService->resolveAgentProjectId($agent->fresh()));
        $this->assertSame($secondProject->id, (int) $agent->fresh()->project_id);
    }

    public function test_reenrolling_a_farmer_closes_previous_active_enrollment_and_resolves_the_latest_project(): void
    {
        $scopeService = app(ProgramScopeService::class);

        $actor = User::factory()->create([
            'type' => 'client',
        ]);

        $firstProject = Project::query()->create([
            'project_name' => 'Livestock Input Support',
            'client_id' => $actor->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);
        $secondProject = Project::query()->create([
            'project_name' => 'Dairy Expansion',
            'client_id' => $actor->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);

        $farmer = Vender::query()->create([
            'vender_id' => 'F-REENROLL-1',
            'name' => 'Reassigned Farmer',
            'created_by' => 1,
        ]);

        $scopeService->enrollFarmerInProject($firstProject, $farmer, $actor);
        $scopeService->enrollFarmerInProject($secondProject, $farmer, $actor);

        $firstEnrollment = ProgramFarmerEnrollment::query()
            ->where('project_id', $firstProject->id)
            ->where('farmer_id', $farmer->id)
            ->firstOrFail();
        $secondEnrollment = ProgramFarmerEnrollment::query()
            ->where('project_id', $secondProject->id)
            ->where('farmer_id', $farmer->id)
            ->firstOrFail();

        $this->assertSame('inactive', $firstEnrollment->status);
        $this->assertSame(now()->toDateString(), optional($firstEnrollment->ends_on)->toDateString());
        $this->assertSame('active', $secondEnrollment->status);
        $this->assertNull($secondEnrollment->ends_on);
        $this->assertSame($secondProject->id, $scopeService->resolveFarmerProjectId($farmer->fresh()));
    }

    public function test_sponsor_payments_page_hides_other_program_batches_runs_credits_orders_and_farmers(): void
    {
        $scopeService = app(ProgramScopeService::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $sponsor = User::factory()->create(['type' => 'client']);
        $sponsor->forceFill(['created_by' => $sponsor->id, 'plan' => 1])->save();
        $outsider = User::factory()->create(['type' => 'client']);

        $sponsor->givePermissionTo(
            Permission::findOrCreate('manage payments overview', 'web')
        );

        $visibleProject = Project::query()->create([
            'project_name' => 'Sponsor Visible Program',
            'client_id' => $sponsor->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);
        $hiddenProject = Project::query()->create([
            'project_name' => 'Sponsor Hidden Program',
            'client_id' => $outsider->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);

        $community = Community::query()->create([
            'name' => 'Damare',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'code' => 'COM-DAMARE',
            'status' => 'active',
        ]);
        $otherCommunity = Community::query()->create([
            'name' => 'Mbamba',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-MBAMBA',
            'status' => 'active',
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'Damare OSS',
            'code' => 'OSS-DAMARE-1',
            'community_id' => $community->id,
            'status' => 'active',
        ]);

        $agent = AgentProfile::query()->create([
            'sponsor_user_id' => $sponsor->id,
            'project_id' => $visibleProject->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-SCOPE-PAY-1',
            'agent_type' => 'farmer',
            'first_name' => 'Halima',
            'last_name' => 'Umar',
            'gender' => 'female',
            'phone_number' => '08030000001',
            'email' => 'halima.scope.payments@example.com',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'community_id' => $community->id,
            'community' => $community->name,
            'residential_address' => 'Damare',
            'assigned_communities' => [$community->name],
            'reconciliation_frequency' => 'daily',
            'settlement_mode' => 'consignment',
            'credit_sales_enabled' => true,
            'status' => 'active',
        ]);

        $visibleFarmer = Vender::query()->create([
            'vender_id' => 'F-PAY-001',
            'name' => 'Visible Payments Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $hiddenFarmer = Vender::query()->create([
            'vender_id' => 'F-PAY-002',
            'name' => 'Hidden Payments Farmer',
            'created_by' => 1,
            'community_id' => $otherCommunity->id,
            'community' => $otherCommunity->name,
        ]);

        $scopeService->assignAgentToProject($visibleProject, $agent, $sponsor);
        $scopeService->enrollFarmerInProject($visibleProject, $visibleFarmer, $sponsor);

        $item = InventoryItem::query()->create([
            'name' => 'Scoped Feed',
            'category' => 'feed',
            'unit' => 'bag',
            'sku' => 'ITEM-SCOPE-1',
            'stock_qty' => 10,
            'unit_price' => 300,
            'status' => 'active',
        ]);

        $visibleBatch = PaymentBatch::query()->create([
            'name' => 'Visible Batch',
            'payee_type' => 'farmer',
            'project_id' => $visibleProject->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 1250,
        ]);
        $hiddenBatch = PaymentBatch::query()->create([
            'name' => 'Hidden Batch',
            'payee_type' => 'farmer',
            'project_id' => $hiddenProject->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 900,
        ]);

        $visibleRun = SettlementRun::query()->create([
            'reference' => 'SET-VIS-1',
            'farmer_id' => $visibleFarmer->id,
            'project_id' => $visibleProject->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_milk_value' => 1600,
            'total_deductions' => 350,
            'net_payout' => 1250,
            'status' => 'approved',
            'created_by' => $sponsor->id,
        ]);
        $hiddenRun = SettlementRun::query()->create([
            'reference' => 'SET-HID-1',
            'farmer_id' => $hiddenFarmer->id,
            'project_id' => $hiddenProject->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_milk_value' => 1200,
            'total_deductions' => 300,
            'net_payout' => 900,
            'status' => 'approved',
            'created_by' => $outsider->id,
        ]);

        $visibleCredit = InventoryCredit::query()->create([
            'inventory_item_id' => $item->id,
            'project_id' => $visibleProject->id,
            'vender_id' => $visibleFarmer->id,
            'customer_name' => $visibleFarmer->name,
            'amount' => 300,
            'outstanding_amount' => 300,
            'status' => 'open',
            'credit_date' => now()->toDateString(),
        ]);
        $hiddenCredit = InventoryCredit::query()->create([
            'inventory_item_id' => $item->id,
            'project_id' => $hiddenProject->id,
            'vender_id' => $hiddenFarmer->id,
            'customer_name' => $hiddenFarmer->name,
            'amount' => 450,
            'outstanding_amount' => 450,
            'status' => 'open',
            'credit_date' => now()->toDateString(),
        ]);

        $visibleOrder = GondalOrder::query()->create([
            'reference' => 'ORD-VIS-1',
            'farmer_id' => $visibleFarmer->id,
            'project_id' => $visibleProject->id,
            'status' => 'fulfilled',
            'payment_mode' => 'milk_deduction',
            'subtotal_amount' => 300,
            'total_amount' => 300,
            'settled_amount' => 0,
            'outstanding_amount' => 300,
            'ordered_on' => now()->toDateString(),
            'fulfilled_at' => now(),
            'created_by' => $sponsor->id,
        ]);
        $hiddenOrder = GondalOrder::query()->create([
            'reference' => 'ORD-HID-1',
            'farmer_id' => $hiddenFarmer->id,
            'project_id' => $hiddenProject->id,
            'status' => 'fulfilled',
            'payment_mode' => 'milk_deduction',
            'subtotal_amount' => 450,
            'total_amount' => 450,
            'settled_amount' => 0,
            'outstanding_amount' => 450,
            'ordered_on' => now()->toDateString(),
            'fulfilled_at' => now(),
            'created_by' => $outsider->id,
        ]);

        $response = $this->actingAs($sponsor)->get(route('gondal.payments'));

        $response->assertOk();
        $response->assertViewHas('batches', fn ($batches) => $batches->pluck('id')->all() === [$visibleBatch->id]);
        $response->assertViewHas('settlementRuns', fn ($runs) => $runs->pluck('id')->all() === [$visibleRun->id]);
        $response->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$visibleOrder->id]);
        $response->assertViewHas('farmers', fn ($farmers) => $farmers->pluck('id')->all() === [$visibleFarmer->id]);
        $response->assertViewHas('reconciliationRows', function ($rows) use ($visibleCredit, $hiddenCredit, $visibleOrder, $hiddenOrder) {
            $references = $rows->pluck('reference')->all();

            return in_array('CR-'.$visibleCredit->id, $references, true)
                && in_array($visibleOrder->reference, $references, true)
                && ! in_array('CR-'.$hiddenCredit->id, $references, true)
                && ! in_array($hiddenOrder->reference, $references, true);
        });

        $this->assertNotNull($hiddenBatch);
        $this->assertNotNull($hiddenRun);
    }

    public function test_sponsor_milk_collection_page_hides_recent_and_selectable_farmers_from_other_programs(): void
    {
        $scopeService = app(ProgramScopeService::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $sponsor = User::factory()->create(['type' => 'client']);
        $sponsor->forceFill(['created_by' => $sponsor->id, 'plan' => 1])->save();
        $outsider = User::factory()->create(['type' => 'client']);

        $sponsor->givePermissionTo(
            Permission::findOrCreate('manage milk collection records', 'web')
        );

        $project = Project::query()->create([
            'project_name' => 'Milk Scope Program',
            'client_id' => $sponsor->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => 1,
        ]);

        $community = Community::query()->create([
            'name' => 'Ngurore',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-NGURORE',
            'status' => 'active',
        ]);
        $otherCommunity = Community::query()->create([
            'name' => 'Bako',
            'state' => 'Adamawa',
            'lga' => 'Girei',
            'code' => 'COM-BAKO',
            'status' => 'active',
        ]);
        $oneStopShop = OneStopShop::query()->create([
            'name' => 'Ngurore OSS',
            'code' => 'OSS-NGURORE-1',
            'community_id' => $community->id,
            'status' => 'active',
        ]);

        $agent = AgentProfile::query()->create([
            'sponsor_user_id' => $sponsor->id,
            'project_id' => $project->id,
            'one_stop_shop_id' => $oneStopShop->id,
            'agent_code' => 'AGT-SCOPE-MILK-1',
            'agent_type' => 'farmer',
            'first_name' => 'Aisha',
            'last_name' => 'Jauro',
            'gender' => 'female',
            'phone_number' => '08030000002',
            'email' => 'aisha.scope.milk@example.com',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'community_id' => $community->id,
            'community' => $community->name,
            'residential_address' => 'Ngurore',
            'assigned_communities' => [$community->name],
            'reconciliation_frequency' => 'daily',
            'settlement_mode' => 'consignment',
            'credit_sales_enabled' => true,
            'status' => 'active',
        ]);

        $visibleFarmer = Vender::query()->create([
            'vender_id' => 'F-MILK-001',
            'name' => 'Visible Milk Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $hiddenFarmer = Vender::query()->create([
            'vender_id' => 'F-MILK-002',
            'name' => 'Hidden Milk Farmer',
            'created_by' => 1,
            'community_id' => $otherCommunity->id,
            'community' => $otherCommunity->name,
        ]);

        $scopeService->assignAgentToProject($project, $agent, $sponsor);
        $scopeService->enrollFarmerInProject($project, $visibleFarmer, $sponsor);

        MilkCollection::query()->create([
            'batch_id' => 'MC-VIS-RECENT',
            'mcc_id' => 'MCC-VIS-1',
            'farmer_id' => $visibleFarmer->id,
            'project_id' => $project->id,
            'quantity' => 55,
            'fat_percentage' => 4.4,
            'temperature' => 16,
            'quality_grade' => 'A',
            'recorded_by' => $sponsor->id,
            'collection_date' => now(),
        ]);
        MilkCollection::query()->create([
            'batch_id' => 'MC-HID-RECENT',
            'mcc_id' => 'MCC-HID-1',
            'farmer_id' => $hiddenFarmer->id,
            'quantity' => 42,
            'fat_percentage' => 4.0,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $outsider->id,
            'collection_date' => now()->subMinute(),
        ]);

        $response = $this->actingAs($sponsor)->get(route('gondal.milk-collection'));

        $response->assertOk();
        $response->assertViewHas('farmers', fn ($farmers) => $farmers->pluck('id')->all() === [$visibleFarmer->id]);
        $response->assertViewHas('recentFarmers', fn ($farmers) => $farmers->pluck('id')->all() === [$visibleFarmer->id]);
    }
}
