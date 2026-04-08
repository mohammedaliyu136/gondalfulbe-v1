<?php

namespace Tests\Feature;

use App\Models\Gondal\Community;
use App\Models\Gondal\JournalEntry;
use App\Models\Gondal\MilkCollectionReconciliation;
use App\Models\Gondal\MilkQualityTest;
use App\Models\Gondal\ProgramFarmerEnrollment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Cooperatives\Models\Cooperative;
use Modules\MilkCollection\Models\MilkCollection;
use Tests\TestCase;

class GondalMilkCollectionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_milk_collection_tags_project_and_collection_center(): void
    {
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Bole',
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-BOLE-'.$suffix,
            'status' => 'active',
        ]);
        $cooperative = Cooperative::query()->create([
            'name' => 'Bole Cooperative',
            'code' => 'COOP-BOLE-'.$suffix,
            'location' => 'Bole MCC',
            'leader_name' => 'Halima Bello',
            'leader_phone' => '08099999999',
            'site_location' => 'Yola South',
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-MILK-'.$suffix,
            'name' => 'Milk Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
            'cooperative_id' => $cooperative->id,
        ]);
        $project = Project::query()->create([
            'project_name' => 'Milk Program '.$suffix,
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

        $response = $this->actingAs($actor)->post(route('gondal.milk-collection.store'), [
            'collection_date' => now()->toDateString(),
            'farmer_id' => $farmer->id,
            'liters' => 12,
            'fat_percent' => 4.4,
            'snf_percent' => 8.5,
            'temperature' => 18,
            'grade' => 'A',
            'adulteration_test' => 'passed',
        ]);

        $response->assertRedirect(route('gondal.milk-collection'));

        $collection = MilkCollection::query()->latest('id')->firstOrFail();

        $this->assertSame($project->id, $collection->project_id);
        $this->assertNotNull($collection->milk_collection_center_id);
        $this->assertDatabaseHas('gondal_milk_quality_tests', [
            'milk_collection_id' => $collection->id,
            'quality_grade' => 'A',
            'is_rejected' => false,
        ]);
        $this->assertDatabaseHas('gondal_journal_entries', [
            'entry_type' => 'milk_accrual',
            'source_key' => 'milk_collection:'.$collection->id,
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('gondal_milk_center_reconciliations', [
            'milk_collection_center_id' => $collection->milk_collection_center_id,
            'project_id' => $project->id,
            'accepted_collections' => 1,
            'rejected_collections' => 0,
            'accepted_quantity' => 12.00,
        ]);
        $this->assertDatabaseHas('milk_collection_centers', [
            'id' => $collection->milk_collection_center_id,
            'name' => $cooperative->name,
            'location' => $cooperative->location,
        ]);
    }

    public function test_rejected_collection_skips_ledger_and_updates_reconciliation_reject_counts(): void
    {
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Bajabure',
            'state' => 'Adamawa',
            'lga' => 'Yola North',
            'code' => 'COM-BAJABURE-'.$suffix,
            'status' => 'active',
        ]);
        $cooperative = Cooperative::query()->create([
            'name' => 'Bajabure Cooperative',
            'code' => 'COOP-BAJABURE-'.$suffix,
            'location' => 'Bajabure MCC',
            'leader_name' => 'Umar Abubakar',
            'leader_phone' => '08010101010',
            'site_location' => 'Yola North',
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-REJ-'.$suffix,
            'name' => 'Rejected Farmer',
            'created_by' => 1,
            'community_id' => $community->id,
            'community' => $community->name,
            'cooperative_id' => $cooperative->id,
        ]);
        $project = Project::query()->create([
            'project_name' => 'Rejected Milk Program '.$suffix,
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

        $response = $this->actingAs($actor)->post(route('gondal.milk-collection.store'), [
            'collection_date' => now()->toDateString(),
            'farmer_id' => $farmer->id,
            'liters' => 9,
            'fat_percent' => 2.1,
            'snf_percent' => 7.2,
            'temperature' => 28,
            'grade' => 'C',
            'adulteration_test' => 'failed',
            'rejection_reason' => 'Adulteration confirmed',
        ]);

        $response->assertRedirect(route('gondal.milk-collection'));

        $collection = MilkCollection::query()->latest('id')->firstOrFail();
        $qualityTest = MilkQualityTest::query()->where('milk_collection_id', $collection->id)->firstOrFail();
        $reconciliation = MilkCollectionReconciliation::query()
            ->where('milk_collection_center_id', $collection->milk_collection_center_id)
            ->whereDate('reconciliation_date', now()->toDateString())
            ->firstOrFail();

        $this->assertSame('C', $collection->quality_grade);
        $this->assertTrue($qualityTest->is_rejected);
        $this->assertSame('Adulteration confirmed', $qualityTest->rejection_reason);
        $this->assertSame(0, JournalEntry::query()->where('source_key', 'milk_collection:'.$collection->id)->count());
        $this->assertSame(0, (int) $reconciliation->accepted_collections);
        $this->assertSame(1, (int) $reconciliation->rejected_collections);
        $this->assertSame(0.0, (float) $reconciliation->accepted_value);
    }
}
