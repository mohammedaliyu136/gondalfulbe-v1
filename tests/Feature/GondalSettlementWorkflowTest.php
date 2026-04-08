<?php

namespace Tests\Feature;

use App\Models\Gondal\BusinessRule;
use App\Models\Gondal\Community;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\JournalEntry;
use App\Models\Gondal\SettlementRun;
use App\Models\Gondal\ProgramFarmerEnrollment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use App\Services\Gondal\LedgerService;
use App\Services\Gondal\SettlementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\MilkCollection\Models\MilkCollection;
use Tests\TestCase;

class GondalSettlementWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_settlement_run_posts_milk_value_allocates_deductions_and_schedules_payout(): void
    {
        $ledgerService = app(LedgerService::class);
        $settlementService = app(SettlementService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Mbamba',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-MBAMBA-SET-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-SET-'.$suffix,
            'name' => 'Settlement Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $project = Project::query()->create([
            'project_name' => 'Settlement Program '.$suffix,
            'client_id' => $actor->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => $actor->id,
        ]);
        ProgramFarmerEnrollment::query()->create([
            'project_id' => $project->id,
            'farmer_id' => $farmer->id,
            'enrolled_by' => $actor->id,
            'starts_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Feed Advance',
            'sku' => 'SKU-SET-'.$suffix,
            'stock_qty' => 0,
            'unit_price' => 1000,
            'status' => 'active',
        ]);

        MilkCollection::query()->create([
            'batch_id' => 'MC-SET-1',
            'mcc_id' => 'MCC-SET',
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'quantity' => 10,
            'fat_percentage' => 4.5,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now()->subDay(),
        ]);
        MilkCollection::query()->create([
            'batch_id' => 'MC-SET-2',
            'mcc_id' => 'MCC-SET',
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'quantity' => 8,
            'fat_percentage' => 3.6,
            'temperature' => 21,
            'quality_grade' => 'B',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);

        $inventoryCredit = InventoryCredit::query()->create([
            'inventory_item_id' => $item->id,
            'project_id' => $project->id,
            'customer_name' => $farmer->name,
            'amount' => 1000,
            'outstanding_amount' => 1000,
            'status' => 'open',
            'credit_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'vender_id' => $farmer->id,
        ]);

        $obligation = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'inventory_credit_id' => $inventoryCredit->id,
            'project_id' => $project->id,
            'source_type' => InventoryCredit::class,
            'source_id' => $inventoryCredit->id,
            'principal_amount' => 1000,
            'priority' => 1,
            'max_deduction_percent' => 30,
            'due_date' => now()->addDays(5)->toDateString(),
        ], $actor);

        $settlement = $settlementService->runFarmerSettlement([
            'farmer_id' => $farmer->id,
            'period_start' => now()->subDays(2)->toDateString(),
            'period_end' => now()->toDateString(),
            'max_deduction_percent' => 50,
            'payout_floor_amount' => 500,
        ], $actor);

        $settlement->refresh();
        $obligation->refresh();

        $this->assertSame('completed', $settlement->status);
        $this->assertSame(2000.0, (float) $settlement->gross_milk_value);
        $this->assertSame(600.0, (float) $settlement->total_deductions);
        $this->assertSame(1400.0, (float) $settlement->net_payout);
        $this->assertSame(400.0, (float) $obligation->outstanding_amount);
        $this->assertDatabaseHas('gondal_deduction_allocations', [
            'obligation_id' => $obligation->id,
            'amount' => 600.00,
        ]);
        $this->assertDatabaseHas('gondal_payouts', [
            'settlement_run_id' => $settlement->id,
            'amount' => 1400.00,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('gondal_payment_batches', [
            'id' => $settlement->payment_batch_id,
            'project_id' => $project->id,
            'total_amount' => 1400.00,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('gondal_payments', [
            'batch_id' => $settlement->payment_batch_id,
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'amount' => 1400.00,
        ]);
    }

    public function test_milk_value_posting_uses_database_grade_pricing(): void
    {
        $ledgerService = app(LedgerService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);

        BusinessRule::query()
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('rule_key', 'milk.grade_prices')
            ->firstOrFail()
            ->update([
                'rule_value' => [
                    'A' => 150,
                    'B' => 95,
                    'C' => 0,
                ],
            ]);

        $community = Community::query()->create([
            'name' => 'Jambutu',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'code' => 'COM-JAMBUTU-LED-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-LED-'.$suffix,
            'name' => 'Ledger Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $collection = MilkCollection::query()->create([
            'batch_id' => 'MC-LED-1',
            'mcc_id' => 'MCC-LED',
            'farmer_id' => $farmer->id,
            'quantity' => 10,
            'fat_percentage' => 4.5,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);

        $entry = $ledgerService->postMilkCollectionValue($collection, null, $actor);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertSame(LedgerService::ENTRY_TYPE_MILK_ACCRUAL, $entry->entry_type);
        $this->assertSame('milk_collection:'.$collection->id, $entry->source_key);
        $this->assertSame(1500.0, (float) $entry->lines->sum('amount') / 2);
        $this->assertSame(150.0, (float) data_get($entry->meta, 'price_per_liter'));
    }

    public function test_milk_value_posting_is_idempotent_for_the_same_collection(): void
    {
        $ledgerService = app(LedgerService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Nassarawo',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-NASSARAWO-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-IDEMP-'.$suffix,
            'name' => 'Idempotent Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $collection = MilkCollection::query()->create([
            'batch_id' => 'MC-IDEMP-1',
            'mcc_id' => 'MCC-IDEMP',
            'farmer_id' => $farmer->id,
            'quantity' => 6,
            'fat_percentage' => 4.3,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);

        $firstEntry = $ledgerService->postMilkCollectionValue($collection, null, $actor);
        $secondEntry = $ledgerService->postMilkCollectionValue($collection, null, $actor);

        $this->assertSame($firstEntry->id, $secondEntry->id);
        $this->assertSame(1, JournalEntry::query()->where('source_key', 'milk_collection:'.$collection->id)->count());
    }

    public function test_reversing_a_journal_entry_marks_original_and_posts_offsetting_lines(): void
    {
        $ledgerService = app(LedgerService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Makama',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'code' => 'COM-MAKAMA-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-REV-'.$suffix,
            'name' => 'Reversal Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $collection = MilkCollection::query()->create([
            'batch_id' => 'MC-REV-1',
            'mcc_id' => 'MCC-REV',
            'farmer_id' => $farmer->id,
            'quantity' => 5,
            'fat_percentage' => 4.4,
            'temperature' => 17,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);

        $entry = $ledgerService->postMilkCollectionValue($collection, null, $actor);
        $reversal = $ledgerService->reverseEntry($entry, 'Milk collection captured in error', $actor);

        $entry->refresh();

        $this->assertSame('reversed', $entry->status);
        $this->assertSame(LedgerService::ENTRY_TYPE_REVERSAL, $reversal->entry_type);
        $this->assertSame($entry->id, $reversal->reversal_of_entry_id);
        $this->assertCount(2, $reversal->lines);
        $this->assertSame(
            [$entry->lines[0]->direction === 'debit' ? 'credit' : 'debit', $entry->lines[1]->direction === 'debit' ? 'credit' : 'debit'],
            $reversal->lines->pluck('direction')->all()
        );
        $this->assertSame(0.0, $ledgerService->farmerAccountBalance($farmer));
    }

    public function test_settlement_uses_database_rule_defaults_when_request_values_are_omitted(): void
    {
        $ledgerService = app(LedgerService::class);
        $settlementService = app(SettlementService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);

        BusinessRule::query()
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('rule_key', 'settlement.defaults')
            ->firstOrFail()
            ->update([
                'rule_value' => [
                    'max_deduction_percent' => 25,
                    'payout_floor_amount' => 300,
                ],
            ]);

        $community = Community::query()->create([
            'name' => 'Girei',
            'state' => 'Adamawa',
            'lga' => 'Girei',
            'code' => 'COM-GIREI-SET-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-SET-DB-'.$suffix,
            'name' => 'Config Settlement Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);

        MilkCollection::query()->create([
            'batch_id' => 'MC-CONFIG-1',
            'mcc_id' => 'MCC-CONFIG',
            'farmer_id' => $farmer->id,
            'quantity' => 10,
            'fat_percentage' => 4.2,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);

        $obligation = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'principal_amount' => 1000,
            'outstanding_amount' => 1000,
            'due_date' => now()->addDays(7)->toDateString(),
        ], $actor);

        $settlement = $settlementService->runFarmerSettlement([
            'farmer_id' => $farmer->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->toDateString(),
        ], $actor);

        $obligation->refresh();
        $settlement->refresh();

        $this->assertSame(1200.0, (float) $settlement->gross_milk_value);
        $this->assertSame(300.0, (float) $settlement->total_deductions);
        $this->assertSame(900.0, (float) $settlement->net_payout);
        $this->assertSame(700.0, (float) $obligation->outstanding_amount);
    }

    public function test_deductions_follow_category_order_then_oldest_due_date_then_oldest_record(): void
    {
        $ledgerService = app(LedgerService::class);
        $settlementService = app(SettlementService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        BusinessRule::query()
            ->updateOrCreate(
                [
                    'scope_type' => 'global',
                    'scope_id' => 0,
                    'rule_key' => \App\Services\Gondal\BusinessRuleService::KEY_SETTLEMENT_DEDUCTION_PRIORITY,
                ],
                [
                    'rule_value' => [
                        'order' => ['loan', 'feed_input_credit', 'service_charge', 'marketplace_order', 'manual_adjustment', 'other'],
                        'same_type_order' => 'oldest_due_date_first',
                    ],
                ],
            );

        $community = Community::query()->create([
            'name' => 'Demsawo',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-DEMSAWO-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-DED-'.$suffix,
            'name' => 'Deduction Ordered Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $project = Project::query()->create([
            'project_name' => 'Deduction Order Program '.$suffix,
            'client_id' => $actor->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => $actor->id,
        ]);
        ProgramFarmerEnrollment::query()->create([
            'project_id' => $project->id,
            'farmer_id' => $farmer->id,
            'enrolled_by' => $actor->id,
            'starts_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
        MilkCollection::query()->create([
            'batch_id' => 'MC-DED-'.$suffix,
            'mcc_id' => 'MCC-DED',
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'quantity' => 10,
            'fat_percentage' => 4.1,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);

        $manualAdjustment = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'principal_amount' => 200,
            'outstanding_amount' => 200,
            'priority' => 1,
            'due_date' => now()->addDays(1)->toDateString(),
            'meta' => ['deduction_category' => 'manual_adjustment'],
        ], $actor);

        $newerFeedCredit = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'principal_amount' => 200,
            'outstanding_amount' => 200,
            'priority' => 99,
            'source_type' => InventoryCredit::class,
            'due_date' => now()->addDays(3)->toDateString(),
        ], $actor);

        usleep(1000);

        $olderFeedCredit = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'principal_amount' => 200,
            'outstanding_amount' => 200,
            'priority' => 5,
            'source_type' => InventoryCredit::class,
            'due_date' => now()->addDay()->toDateString(),
        ], $actor);

        $loanRecovery = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'project_id' => $project->id,
            'principal_amount' => 200,
            'outstanding_amount' => 200,
            'priority' => 50,
            'source_type' => 'loan',
            'due_date' => now()->addDays(10)->toDateString(),
        ], $actor);

        $settlement = $settlementService->runFarmerSettlement([
            'farmer_id' => $farmer->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->toDateString(),
            'max_deduction_percent' => 100,
            'payout_floor_amount' => 0,
        ], $actor);

        $allocationObligationIds = $settlement->deductionRuns()
            ->with('allocations')
            ->firstOrFail()
            ->allocations
            ->sortBy('id')
            ->pluck('obligation_id')
            ->all();

        $this->assertSame([
            $loanRecovery->id,
            $olderFeedCredit->id,
            $newerFeedCredit->id,
            $manualAdjustment->id,
        ], $allocationObligationIds);
    }

    public function test_settlement_only_uses_collections_and_obligations_from_the_resolved_project(): void
    {
        $ledgerService = app(LedgerService::class);
        $settlementService = app(SettlementService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Malkohi',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-MALKOHI-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-PROJ-'.$suffix,
            'name' => 'Project Scoped Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $firstProject = Project::query()->create([
            'project_name' => 'Old Program '.$suffix,
            'client_id' => $actor->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => $actor->id,
        ]);
        $secondProject = Project::query()->create([
            'project_name' => 'Current Program '.$suffix,
            'client_id' => $actor->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'in_progress',
            'created_by' => $actor->id,
        ]);

        ProgramFarmerEnrollment::query()->create([
            'project_id' => $firstProject->id,
            'farmer_id' => $farmer->id,
            'enrolled_by' => $actor->id,
            'starts_on' => now()->subMonths(2)->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
            'status' => 'inactive',
        ]);
        ProgramFarmerEnrollment::query()->create([
            'project_id' => $secondProject->id,
            'farmer_id' => $farmer->id,
            'enrolled_by' => $actor->id,
            'starts_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        MilkCollection::query()->create([
            'batch_id' => 'MC-OLD-'.$suffix,
            'mcc_id' => 'MCC-PROJ',
            'farmer_id' => $farmer->id,
            'project_id' => $firstProject->id,
            'quantity' => 5,
            'fat_percentage' => 4.3,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);
        MilkCollection::query()->create([
            'batch_id' => 'MC-CURRENT-'.$suffix,
            'mcc_id' => 'MCC-PROJ',
            'farmer_id' => $farmer->id,
            'project_id' => $secondProject->id,
            'quantity' => 10,
            'fat_percentage' => 4.1,
            'temperature' => 18,
            'quality_grade' => 'A',
            'recorded_by' => $actor->id,
            'collection_date' => now(),
        ]);

        $oldObligation = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'project_id' => $firstProject->id,
            'principal_amount' => 400,
            'outstanding_amount' => 400,
            'priority' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
        ], $actor);
        $currentObligation = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'project_id' => $secondProject->id,
            'principal_amount' => 900,
            'outstanding_amount' => 900,
            'priority' => 1,
            'max_deduction_percent' => 50,
            'due_date' => now()->addDays(5)->toDateString(),
        ], $actor);

        $settlement = $settlementService->runFarmerSettlement([
            'farmer_id' => $farmer->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->toDateString(),
            'max_deduction_percent' => 50,
            'payout_floor_amount' => 0,
        ], $actor);

        $settlement->refresh();
        $oldObligation->refresh();
        $currentObligation->refresh();
        $deductionRun = $settlement->deductionRuns()->firstOrFail();

        $this->assertSame($secondProject->id, $settlement->project_id);
        $this->assertSame(1200.0, (float) $settlement->gross_milk_value);
        $this->assertSame(600.0, (float) $settlement->total_deductions);
        $this->assertSame(400.0, (float) $oldObligation->outstanding_amount);
        $this->assertSame(300.0, (float) $currentObligation->outstanding_amount);
        $this->assertDatabaseHas('gondal_deduction_allocations', [
            'deduction_run_id' => $deductionRun->id,
            'obligation_id' => $currentObligation->id,
            'amount' => 600.00,
        ]);
        $this->assertDatabaseMissing('gondal_deduction_allocations', [
            'deduction_run_id' => $deductionRun->id,
            'obligation_id' => $oldObligation->id,
        ]);
    }

    public function test_post_deduction_rejects_project_mismatch_between_settlement_and_obligation(): void
    {
        $ledgerService = app(LedgerService::class);
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Namtari',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-NAMTARI-'.$suffix,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-LEDGER-'.$suffix,
            'name' => 'Ledger Guard Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        $settlementProject = Project::query()->create([
            'project_name' => 'Settlement Project '.$suffix,
            'client_id' => $actor->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => $actor->id,
        ]);
        $obligationProject = Project::query()->create([
            'project_name' => 'Obligation Project '.$suffix,
            'client_id' => $actor->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => $actor->id,
        ]);

        $settlementRun = SettlementRun::query()->create([
            'reference' => 'SET-GUARD-'.$suffix,
            'farmer_id' => $farmer->id,
            'project_id' => $settlementProject->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_milk_value' => 1200,
            'total_deductions' => 0,
            'net_payout' => 1200,
            'status' => 'processing',
            'created_by' => $actor->id,
        ]);

        $obligation = $ledgerService->createObligation([
            'farmer_id' => $farmer->id,
            'project_id' => $obligationProject->id,
            'principal_amount' => 300,
            'outstanding_amount' => 300,
            'due_date' => now()->addDays(5)->toDateString(),
        ], $actor);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Deduction journal postings require the settlement and obligation project to match.');

        $ledgerService->postDeduction($settlementRun, $obligation, 100, $actor);
    }
}
