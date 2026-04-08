<?php

namespace Tests\Feature;

use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\Community;
use App\Models\Gondal\GondalOrder;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\JournalEntry;
use App\Models\Gondal\OneStopShop;
use App\Models\Gondal\ProgramFarmerFundingLimit;
use App\Models\Gondal\ProgramFarmerEnrollment;
use App\Models\Gondal\StockIssue;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use App\Services\Gondal\LedgerService;
use App\Services\Gondal\OrderWorkflowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GondalOrderWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_milk_deduction_order_fulfillment_creates_obligation_and_ledger_entry(): void
    {
        $service = app(OrderWorkflowService::class);
        $context = $this->createProgramContext(withAgent: true);

        $order = $service->createOrder([
            'farmer_id' => $context['farmer']->id,
            'agent_profile_id' => $context['agent']?->id,
            'payment_mode' => OrderWorkflowService::PAYMENT_MODE_MILK_DEDUCTION,
            'ordered_on' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $context['item']->id,
                'quantity' => 2,
                'unit_price' => 250,
            ]],
        ], $context['actor']);

        $fulfilled = $service->fulfillOrder($order, $context['actor']);

        $this->assertSame('fulfilled', $fulfilled->status);
        $this->assertSame(500.0, (float) $fulfilled->outstanding_amount);
        $this->assertNotNull($fulfilled->fulfilled_entry_id);
        $this->assertDatabaseHas('gondal_order_items', [
            'order_id' => $fulfilled->id,
            'status' => 'fulfilled',
        ]);
        $this->assertDatabaseHas('gondal_inventory_credits', [
            'order_id' => $fulfilled->id,
            'status' => 'open',
            'amount' => 500.00,
            'outstanding_amount' => 500.00,
        ]);
        $this->assertDatabaseHas('gondal_obligations', [
            'farmer_id' => $context['farmer']->id,
            'project_id' => $context['project']->id,
            'status' => 'open',
            'principal_amount' => 500.00,
        ]);
        $this->assertDatabaseHas('gondal_journal_entries', [
            'id' => $fulfilled->fulfilled_entry_id,
            'entry_type' => LedgerService::ENTRY_TYPE_ORDER_FULFILLMENT,
            'source_key' => 'order:'.$fulfilled->id.':fulfillment',
        ]);
    }

    public function test_cash_order_cancellation_reverses_ledger_and_restores_stock(): void
    {
        $service = app(OrderWorkflowService::class);
        $context = $this->createProgramContext(withAgent: false, unitPrice: 100, stockQty: 10);

        $order = $service->createOrder([
            'farmer_id' => $context['farmer']->id,
            'payment_mode' => OrderWorkflowService::PAYMENT_MODE_CASH,
            'ordered_on' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $context['item']->id,
                'quantity' => 3,
                'unit_price' => 100,
            ]],
        ], $context['actor']);

        $fulfilled = $service->fulfillOrder($order, $context['actor']);
        $this->assertSame(7.0, (float) $context['item']->fresh()->stock_qty);

        $cancelled = $service->cancelOrder($fulfilled, 'Customer cancelled order', $context['actor']);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(10.0, (float) $context['item']->fresh()->stock_qty);
        $this->assertNotNull($cancelled->cancelled_entry_id);
        $this->assertDatabaseHas('gondal_inventory_sales', [
            'order_id' => $cancelled->id,
            'cancelled_reason' => 'Customer cancelled order',
        ]);
        $this->assertDatabaseHas('gondal_journal_entries', [
            'id' => $cancelled->cancelled_entry_id,
            'entry_type' => LedgerService::ENTRY_TYPE_REVERSAL,
        ]);
        $this->assertSame('reversed', JournalEntry::query()->findOrFail($fulfilled->fulfilled_entry_id)->status);
    }

    public function test_payments_and_farmers_pages_show_order_balances(): void
    {
        $service = app(OrderWorkflowService::class);
        $context = $this->createProgramContext(withAgent: false, unitPrice: 150, stockQty: 20);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $paymentPermission = Permission::findOrCreate('manage payments', 'web');
        $farmerPermission = Permission::findOrCreate('manage farmers', 'web');
        $context['actor']->givePermissionTo($paymentPermission, $farmerPermission);

        $milkOrder = $service->fulfillOrder($service->createOrder([
            'farmer_id' => $context['farmer']->id,
            'payment_mode' => OrderWorkflowService::PAYMENT_MODE_MILK_DEDUCTION,
            'ordered_on' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $context['item']->id,
                'quantity' => 2,
                'unit_price' => 150,
            ]],
        ], $context['actor']), $context['actor']);

        $sponsorOrder = $service->fulfillOrder($service->createOrder([
            'farmer_id' => $context['farmer']->id,
            'payment_mode' => OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED,
            'sponsor_name' => 'Sponsor Alpha',
            'sponsor_reference' => 'SPN-REF-1',
            'ordered_on' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $context['item']->id,
                'quantity' => 3,
                'unit_price' => 150,
            ]],
        ], $context['actor']), $context['actor']);

        $paymentsResponse = $this->actingAs($context['actor'])
            ->get(route('gondal.payments', ['tab' => 'reconciliation']));

        $paymentsResponse->assertOk();
        $paymentsResponse->assertSee($milkOrder->reference);
        $paymentsResponse->assertSee($sponsorOrder->reference);
        $paymentsResponse->assertSee('Milk Deduction Orders');
        $paymentsResponse->assertSee('Sponsor Funded Orders');

        $farmersResponse = $this->actingAs($context['actor'])
            ->get(route('gondal.farmers'));

        $farmersResponse->assertOk();
        $farmersResponse->assertSee($context['farmer']->name);
        $farmersResponse->assertSee('₦300.00', false);
        $farmersResponse->assertSee('₦450.00', false);
    }

    public function test_sponsor_funded_order_cannot_exceed_program_budget(): void
    {
        $service = app(OrderWorkflowService::class);
        $context = $this->createProgramContext(withAgent: false, unitPrice: 250, stockQty: 20, projectBudget: 500);

        $firstOrder = $service->createOrder([
            'farmer_id' => $context['farmer']->id,
            'payment_mode' => OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED,
            'sponsor_name' => 'Sponsor Alpha',
            'ordered_on' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $context['item']->id,
                'quantity' => 2,
                'unit_price' => 250,
            ]],
        ], $context['actor']);
        $service->fulfillOrder($firstOrder, $context['actor']);

        $secondOrder = $service->createOrder([
            'farmer_id' => $context['farmer']->id,
            'payment_mode' => OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED,
            'sponsor_name' => 'Sponsor Alpha',
            'ordered_on' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $context['item']->id,
                'quantity' => 1,
                'unit_price' => 250,
            ]],
        ], $context['actor']);

        try {
            $service->fulfillOrder($secondOrder, $context['actor']);
            $this->fail('Expected sponsor-funded program budget validation to fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(
                'This sponsor-funded order exceeds the remaining program funding limit.',
                $exception->errors()['payment_mode'][0] ?? null
            );
        }
    }

    public function test_sponsor_funded_order_cannot_exceed_farmer_program_limit(): void
    {
        $service = app(OrderWorkflowService::class);
        $context = $this->createProgramContext(withAgent: false, unitPrice: 250, stockQty: 20, projectBudget: 5000);
        $this->ensureProgramFarmerFundingLimitTableExists();

        ProgramFarmerFundingLimit::query()->create([
            'project_id' => $context['project']->id,
            'farmer_id' => $context['farmer']->id,
            'limit_amount' => 400,
            'created_by' => $context['actor']->id,
        ]);

        $order = $service->createOrder([
            'farmer_id' => $context['farmer']->id,
            'payment_mode' => OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED,
            'sponsor_name' => 'Sponsor Alpha',
            'ordered_on' => now()->toDateString(),
            'items' => [[
                'inventory_item_id' => $context['item']->id,
                'quantity' => 2,
                'unit_price' => 250,
            ]],
        ], $context['actor']);

        try {
            $service->fulfillOrder($order, $context['actor']);
            $this->fail('Expected sponsor-funded farmer limit validation to fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(
                'This sponsor-funded order exceeds the farmer funding limit for the selected program.',
                $exception->errors()['farmer_id'][0] ?? null
            );
        }
    }

    protected function createProgramContext(bool $withAgent, float $unitPrice = 250, float $stockQty = 0, ?int $projectBudget = null): array
    {
        $suffix = uniqid();
        $actor = User::factory()->create([
            'type' => 'company',
        ]);
        $community = Community::query()->create([
            'name' => 'Order Community '.$suffix,
            'state' => 'Adamawa',
            'lga' => 'Yola South',
            'code' => 'COM-ORD-'.$suffix,
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'project_name' => 'Order Program '.$suffix,
            'client_id' => $actor->id,
            'budget' => $projectBudget,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'in_progress',
            'created_by' => $actor->id,
        ]);
        $item = InventoryItem::query()->create([
            'name' => 'Feed Bag '.$suffix,
            'sku' => 'SKU-ORD-'.$suffix,
            'stock_qty' => $stockQty,
            'unit_price' => $unitPrice,
            'status' => 'active',
        ]);
        $farmer = Vender::query()->create([
            'vender_id' => 'F-ORD-'.$suffix,
            'name' => 'Order Farmer '.$suffix,
            'contact' => '08012345678',
            'created_by' => $actor->id,
            'community_id' => $community->id,
            'community' => $community->name,
        ]);
        ProgramFarmerEnrollment::query()->create([
            'project_id' => $project->id,
            'farmer_id' => $farmer->id,
            'enrolled_by' => $actor->id,
            'starts_on' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        $agent = null;
        if ($withAgent) {
            $oneStopShop = OneStopShop::query()->create([
                'name' => 'Order OSS '.$suffix,
                'code' => 'OSS-ORD-'.$suffix,
                'community_id' => $community->id,
                'status' => 'active',
            ]);
            $agent = AgentProfile::query()->create([
                'project_id' => $project->id,
                'one_stop_shop_id' => $oneStopShop->id,
                'agent_code' => 'AGT-ORD-'.$suffix,
                'agent_type' => 'farmer',
                'first_name' => 'Order',
                'last_name' => 'Agent',
                'gender' => 'female',
                'phone_number' => '08087654321',
                'email' => 'order-agent-'.$suffix.'@example.com',
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
                'issue_reference' => 'OSS-ORD-'.$suffix,
                'quantity_issued' => 10,
                'unit_cost' => $unitPrice,
                'issued_on' => now()->toDateString(),
            ]);
        }

        return compact('actor', 'community', 'project', 'item', 'farmer', 'agent');
    }

    protected function ensureProgramFarmerFundingLimitTableExists(): void
    {
        if (Schema::hasTable('gondal_program_farmer_funding_limits')) {
            return;
        }

        Schema::create('gondal_program_farmer_funding_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
            $table->decimal('limit_amount', 14, 2);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'farmer_id'], 'gondal_program_farmer_funding_limit_unique');
        });
    }
}
