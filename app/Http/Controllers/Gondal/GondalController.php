<?php

namespace App\Http\Controllers\Gondal;

use App\Http\Controllers\Controller;
use App\Models\Gondal\AgentCashLiability;
use App\Models\Gondal\AgentInventoryAdjustment;
use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\AgentRemittance;
use App\Models\Gondal\ApprovalRule;
use App\Models\Gondal\AuditLog;
use App\Models\Gondal\Community;
use App\Models\Gondal\ExtensionTraining;
use App\Models\Gondal\ExtensionVisit;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\InventoryReconciliation;
use App\Models\Gondal\InventorySale;
use App\Models\Gondal\JournalLine;
use App\Models\Gondal\LogisticsRider;
use App\Models\Gondal\LogisticsTrip;
use App\Models\Gondal\MilkCollectionReconciliation;
use App\Models\Gondal\GondalOrder;
use App\Models\Gondal\OneStopShop;
use App\Models\Gondal\OneStopShopStock;
use App\Models\Gondal\OperationCost;
use App\Models\Gondal\Payment;
use App\Models\Gondal\PaymentBatch;
use App\Models\Gondal\ProgramFarmerEnrollment;
use App\Models\Gondal\Requisition;
use App\Models\Gondal\RequisitionEvent;
use App\Models\Gondal\RequisitionItem;
use App\Models\Gondal\SettlementRun;
use App\Models\Gondal\StockIssue;
use App\Models\Gondal\WarehouseStock;
use App\Models\LoginDetail;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use App\Models\warehouse as Warehouse;
use App\Services\Gondal\FinanceService;
use App\Services\Gondal\InventoryWorkflowService;
use App\Services\Gondal\MilkCollectionWorkflowService;
use App\Services\Gondal\ProgramScopeService;
use App\Services\Gondal\ReportService;
use App\Services\Gondal\RequisitionWorkflowService;
use App\Services\Gondal\SettlementService;
use App\Support\GondalPermissionRegistry;
use App\Support\GondalRoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cooperatives\Models\Cooperative;
use Modules\MilkCollection\Models\MilkCollection;
use Modules\MilkCollection\Models\MilkCollectionCenter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GondalController extends Controller
{
    public function __construct(
        protected RequisitionWorkflowService $workflowService,
        protected FinanceService $financeService,
        protected InventoryWorkflowService $inventoryWorkflowService,
        protected MilkCollectionWorkflowService $milkCollectionWorkflowService,
        protected ProgramScopeService $programScopeService,
        protected ReportService $reportService,
        protected GondalRoleResolver $roleResolver,
        protected SettlementService $settlementService,
    ) {}

    public function dashboard(Request $request): RedirectResponse
    {
        $this->requireAnyPermission(GondalPermissionRegistry::dashboardAccessAbilities());

        $dashboardKey = $this->roleResolver->dashboardKey($request->user());

        if ($dashboardKey === 'standard') {
            return redirect()->route('gondal.dashboard.standard');
        }

        return redirect()->route('gondal.dashboard.role', ['dashboard' => $dashboardKey]);
    }

    public function standardDashboard(Request $request)
    {
        $this->requireAnyPermission(GondalPermissionRegistry::dashboardAccessAbilities());

        $collections = MilkCollection::query()
            ->with('farmer.cooperative')
            ->orderByDesc('collection_date')
            ->get();

        $latestCollectionDate = optional($collections->first()?->collection_date)->copy() ?: now();
        $trendStart = $latestCollectionDate->copy()->subDays(6)->startOfDay();
        $trend = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $collections) {
            $date = $trendStart->copy()->addDays($offset)->toDateString();
            $liters = (float) $collections->filter(fn (MilkCollection $collection) => optional($collection->collection_date)->toDateString() === $date)->sum('quantity');

            return [
                'date' => $date,
                'label' => Carbon::parse($date)->format('M j'),
                'liters' => round($liters, 2),
            ];
        });

        $farmerQuery = Vender::query()->with('cooperative');
        $cooperatives = Cooperative::query()->withCount('farmers')->orderBy('name')->get();
        $requisitions = Requisition::query()->with('requester')->latest()->take(5)->get();
        $paymentBatches = PaymentBatch::query()->latest('period_end')->take(5)->get();

        $genderLookup = (clone $farmerQuery)->get()
            ->groupBy(fn (Vender $farmer) => Str::lower((string) $farmer->gender))
            ->map->count();

        $centerSummaries = $cooperatives->map(function (Cooperative $cooperative) use ($collections) {
            $coopCollections = $this->collectionsForCooperative($collections, $cooperative);

            return [
                'name' => $cooperative->name,
                'mcc' => $cooperative->location ?: 'N/A',
                'members' => (int) $cooperative->farmers_count,
                'liters' => round((float) $coopCollections->sum('quantity'), 2),
            ];
        })->sortByDesc('liters')->values();

        $paymentEnabledFarmers = Vender::query()
            ->where(function ($query) {
                $query->where('digital_payment_enabled', 1)
                    ->orWhereNotNull('bank_name')
                    ->orWhereNotNull('account_number');
            })
            ->count();

        $totalFarmers = (int) $farmerQuery->count();
        $activeFarmers = (int) (clone $farmerQuery)->get()->filter(fn (Vender $farmer) => Str::lower((string) $farmer->status) === 'active')->count();
        $totalCooperatives = (int) $cooperatives->count();
        $activeCooperatives = (int) $cooperatives->filter(fn (Cooperative $cooperative) => Str::lower((string) $cooperative->status) === 'active')->count();

        $dailyCollection = (float) $collections->filter(fn (MilkCollection $collection) => optional($collection->collection_date)->toDateString() === $latestCollectionDate->toDateString())->sum('quantity');

        $weeklyCollectionDateStart = now()->subDays(7)->startOfDay();
        $weeklyCollection = (float) $collections->filter(fn (MilkCollection $collection) => optional($collection->collection_date)->greaterThanOrEqualTo($weeklyCollectionDateStart))->sum('quantity');

        $previousWeekDateStart = now()->subDays(14)->startOfDay();
        $previousWeekDateEnd = now()->subDays(7)->startOfDay();
        $previousWeeklyCollection = (float) $collections->filter(function (MilkCollection $collection) use ($previousWeekDateStart, $previousWeekDateEnd) {
            $date = optional($collection->collection_date);

            return $date && $date->greaterThanOrEqualTo($previousWeekDateStart) && $date->lessThan($previousWeekDateEnd);
        })->sum('quantity');

        $weeklyPercentageChange = 0;
        if ($previousWeeklyCollection > 0) {
            $weeklyPercentageChange = (($weeklyCollection - $previousWeeklyCollection) / $previousWeeklyCollection) * 100;
        } elseif ($weeklyCollection > 0) {
            $weeklyPercentageChange = 100;
        }

        $farmersThisMonth = (int) (clone $farmerQuery)->get()->filter(function (Vender $farmer) {
            return $farmer->created_at && $farmer->created_at->month === now()->month && $farmer->created_at->year === now()->year;
        })->count();

        $paymentEnabledThisMonth = Vender::query()
            ->where(function ($query) {
                $query->where('digital_payment_enabled', 1)
                    ->orWhereNotNull('bank_name')
                    ->orWhereNotNull('account_number');
            })
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $coopThisMonth = Cooperative::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $reqThisWeek = Requisition::query()
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();

        $totalCoopMembers = $cooperatives->sum('farmers_count');
        $creatorId = $request->user()->creatorId();

        $teamUsers = User::query()
            ->with('roles')
            ->where(function ($query) use ($creatorId) {
                $query->where('id', $creatorId)
                    ->orWhere('created_by', $creatorId);
            })
            ->whereNotIn('type', ['client'])
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(function (User $user) {
                $roles = method_exists($user, 'getRoleNames') ? collect($user->getRoleNames()) : collect();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $roles->isNotEmpty() ? $roles->join(', ') : Str::headline((string) $user->type),
                    'last_seen' => $user->last_login_at ? Carbon::parse($user->last_login_at)->diffForHumans() : __('No recent login'),
                ];
            });

        $teamActivities = LoginDetail::query()
            ->join('users', 'users.id', '=', 'login_details.user_id')
            ->where(function ($query) use ($creatorId) {
                $query->where('users.id', $creatorId)
                    ->orWhere('users.created_by', $creatorId);
            })
            ->whereNotIn('users.type', ['client'])
            ->orderByDesc('login_details.created_at')
            ->limit(6)
            ->get([
                'login_details.id',
                'login_details.ip',
                'login_details.date',
                'login_details.Details',
                'users.name',
                'users.type',
            ])
            ->map(function ($entry) {
                $details = json_decode((string) $entry->Details, true) ?: [];
                $locationParts = array_filter([
                    $details['city'] ?? null,
                    $details['country'] ?? null,
                ]);
                $location = ! empty($locationParts) ? implode(', ', $locationParts) : 'Nigeria';
                $activity = isset($details['browser_name'], $details['os_name'])
                    ? __('signed in from :browser on :os', ['browser' => $details['browser_name'], 'os' => $details['os_name']])
                    : __('signed in to review Gondal operations');

                return [
                    'name' => $entry->name,
                    'role' => Str::headline((string) $entry->type),
                    'activity' => $activity.' '.__('from :location', ['location' => $location]),
                    'time' => Carbon::parse($entry->date ?: $entry->created_at)->diffForHumans(),
                    'status' => 'Active',
                ];
            });

        return view('gondal.dashboard', [
            'roleLabel' => $this->roleResolver->label($request->user()),
            'dashboardTitle' => __('Standard Dashboard'),
            'dashboardSubtitle' => __('Cross-functional operational overview across milk collection, payments, extension, and inventory.'),
            'standardDashboardUrl' => route('gondal.dashboard.standard'),
            'totalFarmers' => $totalFarmers,
            'activeFarmers' => $activeFarmers,
            'farmersThisMonth' => $farmersThisMonth,
            'dailyCollection' => $dailyCollection,
            'weeklyCollection' => $weeklyCollection,
            'weeklyPercentageChange' => $weeklyPercentageChange,
            'financialInclusion' => $totalFarmers > 0 ? (int) round(($paymentEnabledFarmers / $totalFarmers) * 100).'%' : '0%',
            'paymentEnabledThisMonth' => $paymentEnabledThisMonth,
            'activeCooperatives' => $activeCooperatives,
            'totalCooperatives' => $totalCooperatives,
            'coopThisMonth' => $coopThisMonth,
            'pendingRequisitions' => (int) Requisition::query()->where('status', 'pending')->count(),
            'reqThisWeek' => $reqThisWeek,
            'totalCoopMembers' => $totalCoopMembers,
            'trend' => $trend,
            'centerSummaries' => $centerSummaries->take(6),
            'genderBreakdown' => [
                ['label' => 'Male', 'count' => (int) ($genderLookup['male'] ?? 0)],
                ['label' => 'Female', 'count' => (int) ($genderLookup['female'] ?? 0)],
                ['label' => 'Other', 'count' => (int) ($genderLookup['other'] ?? 0)],
            ],
            'teamActivities' => $teamActivities,
            'teamUsers' => $teamUsers,
            'recentRequisitions' => $requisitions,
            'recentPaymentBatches' => $paymentBatches,
        ]);
    }

    public function roleDashboard(Request $request, string $dashboard)
    {
        $this->requireAnyPermission(GondalPermissionRegistry::dashboardAccessAbilities());

        abort_unless(in_array($dashboard, $this->roleResolver->roleDashboardKeys(), true), 404);

        return view('gondal.dashboard-role', $this->buildRoleDashboardPayload($request, $dashboard));
    }

    public function farmers(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $selectedMcc = trim((string) $request->query('mcc', 'all'));
        $selectedStatus = trim((string) $request->query('status', 'all'));

        $query = Vender::query()->with(['cooperative', 'communityRecord']);
        $this->programScopeService->scopedFarmersQuery($query, $request->user());

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('contact', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('vender_id', 'like', '%'.$search.'%');
            });
        }

        if ($selectedStatus !== '' && $selectedStatus !== 'all') {
            $query->whereRaw('LOWER(status) = ?', [Str::lower($selectedStatus)]);
        }

        if ($selectedMcc !== '' && $selectedMcc !== 'all') {
            $query->whereHas('cooperative', fn ($builder) => $builder->where('location', $selectedMcc));
        }

        $latestCollections = MilkCollection::query()
            ->selectRaw('farmer_id, MAX(collection_date) as latest_collection_date')
            ->groupBy('farmer_id')
            ->pluck('latest_collection_date', 'farmer_id');

        $farmerModels = $query->orderBy('vender_id')->get();
        $farmerIds = $farmerModels->pluck('id')->all();
        $ledgerBalanceByFarmer = JournalLine::query()
            ->whereIn('farmer_id', $farmerIds)
            ->selectRaw("farmer_id, ROUND(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 2) as balance")
            ->groupBy('farmer_id')
            ->pluck('balance', 'farmer_id');
        $milkDeductionOrderBalanceByFarmer = GondalOrder::query()
            ->whereIn('farmer_id', $farmerIds)
            ->where('payment_mode', 'milk_deduction')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('farmer_id, ROUND(SUM(outstanding_amount), 2) as balance')
            ->groupBy('farmer_id')
            ->pluck('balance', 'farmer_id');
        $sponsorOrderBalanceByFarmer = GondalOrder::query()
            ->whereIn('farmer_id', $farmerIds)
            ->where('payment_mode', 'sponsor_funded')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('farmer_id, ROUND(SUM(outstanding_amount), 2) as balance')
            ->groupBy('farmer_id')
            ->pluck('balance', 'farmer_id');

        $farmers = $farmerModels->map(function (Vender $farmer) use ($latestCollections, $ledgerBalanceByFarmer, $milkDeductionOrderBalanceByFarmer, $sponsorOrderBalanceByFarmer) {
            $latestCollectionDate = $latestCollections[$farmer->id] ?? null;
            $lastSupplyAt = $latestCollectionDate ? Carbon::parse($latestCollectionDate) : null;

            return [
                'id' => $farmer->id,
                'code' => $this->farmerCode($farmer),
                'name' => $farmer->name,
                'phone' => $farmer->contact ?: 'N/A',
                'email' => $farmer->email ?: 'N/A',
                'gender' => Str::title((string) $farmer->gender),
                'status' => Str::title((string) $farmer->status),
                'status_key' => Str::lower((string) $farmer->status),
                'cooperative' => $farmer->cooperative?->name ?: 'N/A',
                'cooperative_id' => $farmer->cooperative_id,
                'mcc' => $farmer->cooperative?->location ?: 'N/A',
                'state' => (string) $farmer->state,
                'lga' => (string) $farmer->lga,
                'ward' => (string) $farmer->ward,
                'community' => $farmer->communityRecord?->name ?: (string) $farmer->community,
                'community_id' => $farmer->community_id,
                'bank_name' => (string) $farmer->bank_name,
                'account_number' => (string) $farmer->account_number,
                'target_liters' => (float) ($farmer->target_liters ?? 0),
                'ledger_balance' => (float) ($ledgerBalanceByFarmer[$farmer->id] ?? 0),
                'open_order_balance' => (float) ($milkDeductionOrderBalanceByFarmer[$farmer->id] ?? 0),
                'sponsor_order_balance' => (float) ($sponsorOrderBalanceByFarmer[$farmer->id] ?? 0),
                'digital_payment' => (bool) $farmer->digital_payment_enabled || filled($farmer->account_number),
                'profile_photo_url' => $this->storageUrl($farmer->profile_photo_path),
                'last_supply_at' => $lastSupplyAt?->toDateString(),
                'stale' => $lastSupplyAt ? $lastSupplyAt->diffInDays(now()) > 60 : false,
            ];
        });

        if ($request->query('export') === 'csv') {
            return $this->exportFarmersCsv($farmers);
        }

        return view('gondal.farmers', [
            'farmers' => $farmers,
            'search' => $search,
            'selectedMcc' => $selectedMcc,
            'selectedStatus' => $selectedStatus,
            'mccOptions' => Cooperative::query()->pluck('location')->filter()->unique()->values(),
            'cooperatives' => Cooperative::query()->orderBy('name')->get(),
            'locationHierarchy' => $this->farmerLocationHierarchy(),
        ]);
    }

    public function communities(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $selectedState = trim((string) $request->query('state', 'all'));

        $query = Community::query()
            ->withCount(['farmers', 'agents']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('lga', 'like', '%'.$search.'%')
                    ->orWhere('state', 'like', '%'.$search.'%');
            });
        }

        if ($selectedState !== '' && $selectedState !== 'all') {
            $query->where('state', $selectedState);
        }

        $communities = $query->orderBy('state')->orderBy('lga')->orderBy('name')->get();

        return view('gondal.communities', [
            'communities' => $communities,
            'search' => $search,
            'selectedState' => $selectedState,
            'stateOptions' => Community::query()->pluck('state')->filter()->unique()->sort()->values(),
            'communityKpis' => [
                'communities' => $communities->count(),
                'states' => $communities->pluck('state')->filter()->unique()->count(),
                'lgas' => $communities->pluck('lga')->filter()->unique()->count(),
                'active' => $communities->where('status', 'active')->count(),
            ],
        ]);
    }

    public function partners(Request $request)
    {
        abort_unless($request->user()->can('manage client'), 403);

        return view('gondal.partners', $this->partnerPageData($request));
    }

    public function storeCommunity(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'lga' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $community = Community::query()->firstOrCreate(
            [
                'name' => trim((string) $payload['name']),
                'state' => trim((string) $payload['state']),
                'lga' => trim((string) $payload['lga']),
            ],
            [
                'code' => $this->generateCommunityCode((string) $payload['name']),
                'status' => $payload['status'],
            ],
        );

        if (! $community->wasRecentlyCreated && $community->status !== $payload['status']) {
            $community->update(['status' => $payload['status']]);
        }

        return redirect()->route('gondal.communities')->with('success', __('Community saved successfully.'));
    }

    public function storePartner(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create client'), 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $creatorId = (int) $request->user()->creatorId();
        $defaultLanguage = DB::table('settings')
            ->where('name', 'default_language')
            ->where('created_by', $creatorId)
            ->value('value') ?: 'en';

        $partner = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'type' => 'client',
            'lang' => $defaultLanguage,
            'created_by' => $creatorId,
            'email_verified_at' => now(),
            'is_enable_login' => 1,
        ]);

        $clientRole = Role::query()
            ->where('name', 'client')
            ->where(function ($query) use ($creatorId) {
                $query->where('created_by', $creatorId)
                    ->orWhereNull('created_by');
            })
            ->first();

        if ($clientRole) {
            $partner->assignRole($clientRole);
        }

        $permissions = Permission::query()
            ->whereIn('name', ['manage inventory agents'])
            ->pluck('name')
            ->all();

        if ($permissions !== []) {
            $partner->givePermissionTo($permissions);
        }

        return redirect()->route('gondal.partners')
            ->with('success', __('Partner login created successfully.'))
            ->with('partner_login_details', [
                'name' => $partner->name,
                'email' => $partner->email,
                'password' => $payload['password'],
                'login_url' => url('/login'),
                'dashboard_url' => route('gondal.agents.dashboard'),
                'analytics_url' => route('gondal.agents.analytics'),
            ]);
    }

    public function importCommunities(Request $request): RedirectResponse
    {
        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = $this->importCommunityRows($rows);

            return redirect()->route('gondal.communities')
                ->with('success', __('Imported :count community row(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.communities', [], $exception);
        }
    }

    public function downloadCommunityImportSample(Request $request): Response
    {
        $rows = [
            ['state' => 'Adamawa', 'lga' => 'Yola South', 'community' => 'Sabon Pegi', 'status' => 'active'],
            ['state' => 'Adamawa', 'lga' => 'Yola South', 'community' => 'Mbamba', 'status' => 'active'],
            ['state' => 'Adamawa', 'lga' => 'Yola North', 'community' => 'Karewa', 'status' => 'active'],
            ['state' => 'Adamawa', 'lga' => 'Numan', 'community' => 'Numan Town', 'status' => 'active'],
            ['state' => 'Adamawa', 'lga' => 'Mayo-Belwa', 'community' => 'Binyeri', 'status' => 'active'],
        ];

        return $this->streamCsvDownload(
            'gondal-communities-import-sample.csv',
            ['state', 'lga', 'community', 'status'],
            $rows,
        );
    }

    public function storeFarmer(Request $request): RedirectResponse
    {
        $validated = $this->validateFarmerPayload($request);
        $cooperative = $this->resolveFarmerCooperative((int) $validated['cooperative_id'], (string) $validated['mcc']);
        $community = $this->resolveOrCreateCommunity(
            (string) $validated['community'],
            (string) $validated['state'],
            (string) $validated['lga'],
        );
        $photoPath = $this->storeFarmerPhoto($request->file('profile_photo'));

        $farmer = new Vender;
        $farmer->vender_id = $this->nextFarmerNumber();
        $farmer->name = $validated['name'];
        $farmer->email = $validated['email'] ?: null;
        $farmer->contact = $validated['phone'];
        $farmer->created_by = (int) $request->user()->creatorId();
        $farmer->cooperative_id = $cooperative->id;
        $farmer->community_id = $community->id;
        $farmer->gender = $validated['gender'];
        $farmer->status = 'active';
        $farmer->registration_date = $validated['registration_date'] ?? now()->toDateString();
        $farmer->state = $validated['state'];
        $farmer->lga = $validated['lga'];
        $farmer->ward = $validated['ward'];
        $farmer->community = $community->name;
        $farmer->bank_name = $validated['bank_name'] ?: null;
        $farmer->account_number = $validated['account_number'] ?: null;
        $farmer->digital_payment_enabled = filled($validated['account_number']);
        $farmer->profile_photo_path = $photoPath;
        $farmer->target_liters = (float) ($validated['target_liters'] ?? 0);
        $farmer->save();

        $this->writeAuditLog($request, 'farmers', 'created', [
            'farmer_id' => $farmer->id,
            'code' => $this->farmerCode($farmer),
            'name' => $farmer->name,
        ]);

        return redirect()->route('gondal.farmers')->with('success', __('Farmer registered successfully.'));
    }

    public function updateFarmer(Request $request, Vender $farmer): RedirectResponse
    {
        $validated = $this->validateFarmerPayload($request, true);
        $cooperative = $this->resolveFarmerCooperative((int) $validated['cooperative_id'], (string) $validated['mcc']);
        $community = $this->resolveOrCreateCommunity(
            (string) ($validated['community'] ?: $farmer->community),
            (string) ($validated['state'] ?: $farmer->state),
            (string) ($validated['lga'] ?: $farmer->lga),
        );

        $farmer->name = $validated['name'];
        $farmer->email = $validated['email'] ?: null;
        $farmer->contact = $validated['phone'];
        $farmer->cooperative_id = $cooperative->id;
        $farmer->community_id = $community->id;
        $farmer->gender = $validated['gender'];
        $farmer->status = $validated['status'] ?? (string) $farmer->status;
        $farmer->registration_date = $validated['registration_date'] ?? $farmer->registration_date;
        $farmer->state = $validated['state'] ?: $farmer->state;
        $farmer->lga = $validated['lga'] ?: $farmer->lga;
        $farmer->ward = $validated['ward'] ?: $farmer->ward;
        $farmer->community = $community->name;
        $farmer->bank_name = $validated['bank_name'] ?: null;
        $farmer->account_number = $validated['account_number'] ?: null;
        $farmer->digital_payment_enabled = filled($validated['account_number']);
        $farmer->target_liters = (float) ($validated['target_liters'] ?? $farmer->target_liters ?? 0);

        if ($request->hasFile('profile_photo')) {
            $this->deleteFarmerPhoto($farmer->profile_photo_path);
            $farmer->profile_photo_path = $this->storeFarmerPhoto($request->file('profile_photo'));
        }

        $farmer->save();

        $this->writeAuditLog($request, 'farmers', 'updated', [
            'farmer_id' => $farmer->id,
            'code' => $this->farmerCode($farmer),
            'name' => $farmer->name,
        ]);

        return redirect()->route('gondal.farmers')->with('success', __('Farmer updated successfully.'));
    }

    public function destroyFarmer(Request $request, Vender $farmer): RedirectResponse
    {
        $this->deleteFarmerPhoto($farmer->profile_photo_path);
        $farmer->delete();

        $this->writeAuditLog($request, 'farmers', 'deleted', [
            'farmer_id' => $farmer->id,
            'code' => $this->farmerCode($farmer),
            'name' => $farmer->name,
        ]);

        return redirect()->route('gondal.farmers')->with('success', __('Farmer deleted successfully.'));
    }

    public function cooperatives(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $selectedMcc = trim((string) $request->query('mcc', 'all'));
        $collections = MilkCollection::query()->with('farmer')->get();

        $query = Cooperative::query()->withCount('farmers');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('leader_name', 'like', '%'.$search.'%')
                    ->orWhere('leader_phone', 'like', '%'.$search.'%');
            });
        }

        if ($selectedMcc !== '' && $selectedMcc !== 'all') {
            $query->where('location', $selectedMcc);
        }

        $cooperatives = $query->orderBy('name')->get()->map(function (Cooperative $cooperative) use ($collections) {
            $coopCollections = $this->collectionsForCooperative($collections, $cooperative);
            $collectionDays = $coopCollections->pluck('collection_date')->map(fn ($date) => optional($date)->toDateString())->filter()->unique()->count();
            $averageDailySupply = $collectionDays > 0 ? (float) $coopCollections->sum('quantity') / $collectionDays : 0;

            return [
                'id' => $cooperative->id,
                'code' => $cooperative->code ?: $this->nextCooperativeCode($cooperative->location ?: $cooperative->name),
                'name' => $cooperative->name,
                'mcc' => $cooperative->location ?: 'N/A',
                'leader_name' => $cooperative->leader_name ?: 'N/A',
                'leader_phone' => $cooperative->leader_phone ?: 'N/A',
                'site_location' => $cooperative->site_location ?: 'N/A',
                'status' => Str::title((string) $cooperative->status),
                'members_count' => (int) $cooperative->farmers_count,
                'avg_daily_supply' => round($averageDailySupply, 2),
            ];
        });

        if ($request->query('export') === 'csv') {
            return $this->exportCooperativesCsv($cooperatives);
        }

        return view('gondal.cooperatives', [
            'cooperatives' => $cooperatives,
            'search' => $search,
            'selectedMcc' => $selectedMcc,
            'mccOptions' => Cooperative::query()->pluck('location')->filter()->unique()->values(),
        ]);
    }

    public function storeCooperative(Request $request): RedirectResponse
    {
        $validated = $this->validateCooperativePayload($request);

        $cooperative = Cooperative::query()->create([
            'name' => $validated['name'],
            'code' => $this->nextCooperativeCode($validated['mcc']),
            'location' => $validated['mcc'],
            'leader_name' => $validated['leader_name'],
            'leader_phone' => $validated['leader_phone'],
            'site_location' => $validated['site_location'],
            'status' => $validated['status'],
            'formation_date' => $validated['formation_date'] ?: null,
            'average_daily_supply' => 0,
        ]);

        $this->writeAuditLog($request, 'cooperatives', 'created', [
            'cooperative_id' => $cooperative->id,
            'code' => $cooperative->code,
            'name' => $cooperative->name,
        ]);

        return redirect()->route('gondal.cooperatives')->with('success', __('Cooperative registered successfully.'));
    }

    public function cooperativeDetail(string $id)
    {
        $cooperative = $this->resolveCooperative($id);
        $collections = $this->collectionsForCooperative(MilkCollection::query()->with('farmer')->get(), $cooperative);

        return view('gondal.cooperative-detail', [
            'cooperative' => $cooperative,
            'farmers' => $cooperative->farmers()->orderBy('name')->get(),
            'totalLiters' => round((float) $collections->sum('quantity'), 2),
            'lastCollectionDate' => optional($collections->sortByDesc('collection_date')->first()?->collection_date)->toDateString(),
        ]);
    }

    public function milkCollection(Request $request)
    {
        $selectedDate = Carbon::parse((string) $request->query('date', now()->toDateString()))->toDateString();
        $view = in_array((string) $request->query('view', 'records'), ['records', 'summary'], true)
            ? (string) $request->query('view', 'records')
            : 'records';
        $tab = in_array((string) $request->query('tab', 'all'), ['all', 'pending', 'validated', 'rejected'], true)
            ? (string) $request->query('tab', 'all')
            : 'all';

        $recordsQuery = MilkCollection::query()->with(['farmer.cooperative', 'qualityTest', 'collectionCenter']);
        $this->programScopeService->scopedMilkCollectionsQuery($recordsQuery, $request->user());

        if ($from = $request->query('from')) {
            $recordsQuery->whereDate('collection_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $recordsQuery->whereDate('collection_date', '<=', $to);
        }
        if ($grade = $request->query('grade')) {
            $recordsQuery->where('quality_grade', strtoupper((string) $grade));
        }

        if ($tab !== 'all') {
            $recordsQuery->where('status', $tab);
        }

        $records = $recordsQuery->orderByDesc('collection_date')->take(50)->get();
        $dayCollectionsQuery = MilkCollection::query()->with(['farmer.cooperative', 'qualityTest', 'collectionCenter'])->whereDate('collection_date', $selectedDate);
        $this->programScopeService->scopedMilkCollectionsQuery($dayCollectionsQuery, $request->user());
        $dayCollections = $dayCollectionsQuery->get();
        $reconciliationQuery = MilkCollectionReconciliation::query()->with('center');

        if ($projectIds = $this->programScopeService->scopedProjectIds($request->user())) {
            $reconciliationQuery->whereIn('project_id', $projectIds);
        }

        $dailyReconciliations = $reconciliationQuery
            ->whereDate('reconciliation_date', $selectedDate)
            ->get()
            ->groupBy(fn (MilkCollectionReconciliation $reconciliation) => (int) $reconciliation->milk_collection_center_id)
            ->map(fn (Collection $rows) => [
                'accepted_collections' => (int) $rows->sum('accepted_collections'),
                'rejected_collections' => (int) $rows->sum('rejected_collections'),
                'accepted_value' => round((float) $rows->sum('accepted_value'), 2),
            ]);

        $summaryRows = Cooperative::query()->withCount('farmers')->orderBy('name')->get()->map(function (Cooperative $cooperative) use ($dailyReconciliations, $dayCollections) {
            $rows = $this->collectionsForCooperative($dayCollections, $cooperative);
            $count = max(1, $rows->count());
            $reconciliation = $rows->first()?->milk_collection_center_id
                ? $dailyReconciliations->get((int) $rows->first()->milk_collection_center_id)
                : null;

            return [
                'name' => $cooperative->name,
                'mcc' => $cooperative->location ?: 'N/A',
                'farmers_count' => $rows->pluck('farmer_id')->filter()->unique()->count(),
                'liters' => round((float) $rows->sum('quantity'), 2),
                'avg_fat_percent' => round((float) $rows->avg('fat_percentage'), 2),
                'grade_a_count' => $rows->filter(fn (MilkCollection $collection) => strtoupper((string) $collection->quality_grade) === 'A')->count(),
                'total_records' => $count === 1 && $rows->isEmpty() ? 0 : $count,
                'accepted_collections' => (int) ($reconciliation['accepted_collections'] ?? $rows->filter(fn (MilkCollection $collection) => strtoupper((string) $collection->quality_grade) !== 'C')->count()),
                'rejected_collections' => (int) ($reconciliation['rejected_collections'] ?? $rows->filter(fn (MilkCollection $collection) => strtoupper((string) $collection->quality_grade) === 'C')->count()),
                'accepted_value' => round((float) ($reconciliation['accepted_value'] ?? 0), 2),
            ];
        });

        $recentFarmersQuery = MilkCollection::query()->with('farmer');
        $this->programScopeService->scopedMilkCollectionsQuery($recentFarmersQuery, $request->user());
        $recentFarmers = $recentFarmersQuery
            ->orderByDesc('collection_date')
            ->take(20)
            ->get()
            ->pluck('farmer')
            ->filter()
            ->unique('id')
            ->take(8)
            ->values();

        return view('gondal.milk-collection', [
            'selectedDate' => $selectedDate,
            'selectedGrade' => strtoupper((string) $request->query('grade', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'summaryRows' => $summaryRows,
            'records' => $records,
            'farmers' => $this->programScopeService->scopedFarmersQuery(Vender::query(), $request->user())->orderBy('name')->get(),
            'recentFarmers' => $recentFarmers,
            'tab' => $tab,
            'view' => $view,
            'statusTabs' => [
                'all' => __('All'),
                'pending' => __('Pending Validation'),
                'validated' => __('Validated'),
                'rejected' => __('Rejected'),
            ],
        ]);
    }

    public function milkCollectionDetail($id)
    {
        $collection = MilkCollection::with(['farmer.cooperative', 'qualityTest', 'collectionCenter'])->findOrFail($id);
        
        $recentHistory = MilkCollection::where('farmer_id', $collection->farmer_id)
            ->where('id', '!=', $collection->id)
            ->latest('collection_date')
            ->take(5)
            ->get();

        return view('gondal.milk-collection-detail', [
            'collection' => $collection,
            'recentHistory' => $recentHistory,
        ]);
    }


    public function storeMilkCollection(Request $request): RedirectResponse
    {
        $validated = $this->validateMilkCollectionPayload($request);
        $farmer = Vender::query()->with('cooperative')->findOrFail($validated['farmer_id']);
        $collection = $this->milkCollectionWorkflowService->recordCollection([
            'batch_id' => 'GON-'.now()->format('YmdHis'),
            'quantity' => $validated['liters'],
            'fat_percentage' => null,
            'snf_percentage' => null,
            'temperature' => $validated['temperature'] ?: null,
            'quality_grade' => null,
            'adulteration_test' => 'passed',
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'recorded_by' => $request->user()->id,
            'collection_date' => $validated['collection_date'],
            'captured_via' => 'gondal_web',
        ], $farmer, $request->user());

        $this->writeAuditLog($request, 'milk_collection', 'created', [
            'collection_id' => $collection->id,
            'farmer_id' => $farmer->id,
            'liters' => $collection->quantity,
            'grade' => $collection->quality_grade,
        ]);

        return redirect()->route('gondal.milk-collection')->with('success', __('Milk collection recorded and awaiting validation.'));
    }

    public function validateMilkCollection(Request $request, string $id): RedirectResponse
    {
        $collection = MilkCollection::query()->findOrFail($id);
        
        $validated = $request->validate([
            'fat_percentage' => 'nullable|numeric|min:0',
            'snf_percentage' => 'nullable|numeric|min:0',
            'temperature' => 'nullable|numeric',
            'quality_grade' => 'required|in:A,B,C',
            'adulteration_test' => 'required|in:passed,failed',
            'rejection_reason' => 'required_if:quality_grade,C',
        ]);

        $this->milkCollectionWorkflowService->validateCollection($collection, $validated, $request->user());

        return redirect()->route('gondal.milk-collection')->with('success', __('Milk collection validated and graded successfully.'));
    }


    public function logistics(Request $request)
    {
        $tab = $this->resolveModuleTab($request, 'logistics', 'trips');

        $tripQuery = LogisticsTrip::query()->with(['rider', 'cooperative', 'paymentBatch']);

        if ($status = (string) $request->query('status', '')) {
            $tripQuery->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $tripQuery->whereDate('trip_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $tripQuery->whereDate('trip_date', '<=', $to);
        }

        $trips = $tripQuery->orderByDesc('trip_date')->take(50)->get();
        $riders = LogisticsRider::query()->with('trips.cooperative')->orderBy('name')->get();

        return view('gondal.logistics', [
            'tab' => $tab,
            'visibleTabs' => $this->visibleModuleTabs($request, 'logistics'),
            'selectedStatus' => (string) $request->query('status', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'cards' => [
                ['label' => 'Volume Moved', 'value' => number_format((float) LogisticsTrip::query()->sum('volume_liters'), 2).' L'],
                ['label' => 'Active Riders', 'value' => number_format((int) LogisticsRider::query()->where('status', 'active')->count())],
                ['label' => 'Completed Trips', 'value' => number_format((int) LogisticsTrip::query()->where('status', 'completed')->count())],
                ['label' => 'In Transit', 'value' => number_format((int) LogisticsTrip::query()->where('status', 'in_transit')->count())],
            ],
            'trips' => $trips,
            'riders' => $riders,
            'cooperatives' => Cooperative::query()->orderBy('name')->get(),
        ]);
    }

    public function storeLogisticsTrip(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'logistics', 'trips', 'create');

        $payload = $this->validateLogisticsTripPayload($request);
        $trip = LogisticsTrip::query()->create($payload);

        $this->writeAuditLog($request, 'logistics', 'trip_created', [
            'trip_id' => $trip->id,
            'rider_id' => $trip->rider_id,
            'cooperative_id' => $trip->cooperative_id,
        ]);

        return redirect()->route('gondal.logistics', ['tab' => 'trips'])->with('success', __('Trip recorded successfully.'));
    }

    public function approveLogisticsTrip(string $id, Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'logistics', 'trips');

        $trip = LogisticsTrip::query()->with(['rider', 'cooperative', 'paymentBatch'])->findOrFail($id);

        if ($trip->status !== 'completed') {
            return back()->with('error', __('Only completed trips can be approved for payment.'));
        }

        if ($trip->payment_batch_id) {
            return back()->with('error', __('This trip has already been sent to payment.'));
        }

        $batch = PaymentBatch::query()->create([
            'name' => __('Trip Payment - :rider - :date', [
                'rider' => $trip->rider?->name ?: 'Unknown Rider',
                'date' => optional($trip->trip_date)->toDateString() ?: now()->toDateString(),
            ]),
            'payee_type' => 'rider',
            'period_start' => optional($trip->trip_date)->toDateString() ?: now()->toDateString(),
            'period_end' => optional($trip->trip_date)->toDateString() ?: now()->toDateString(),
            'status' => 'approved',
            'total_amount' => (float) $trip->fuel_cost,
        ]);

        $trip->update([
            'status' => 'approved',
            'payment_batch_id' => $batch->id,
        ]);

        $this->writeAuditLog($request, 'logistics', 'trip_approved_for_payment', [
            'trip_id' => $trip->id,
            'batch_id' => $batch->id,
            'rider_id' => $trip->rider_id,
            'amount' => $trip->fuel_cost,
        ]);

        return redirect()
            ->route('gondal.logistics', ['tab' => 'trips'])
            ->with('success', __('Trip approved and sent to payment successfully.'));
    }

    public function storeLogisticsRider(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'logistics', 'riders', 'create');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'bike_make' => ['nullable', 'string', 'max:255'],
            'bike_model' => ['nullable', 'string', 'max:255'],
            'bike_plate_number' => ['nullable', 'string', 'max:100'],
            'identification_type' => ['nullable', 'string', 'max:255'],
            'identification_number' => ['nullable', 'string', 'max:100'],
            'identification_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $rider = LogisticsRider::query()->create([
            'name' => $payload['name'],
            'code' => 'RID-'.str_pad((string) ((int) LogisticsRider::query()->max('id') + 1), 3, '0', STR_PAD_LEFT),
            'phone' => $payload['phone'] ?: null,
            'photo_path' => $this->storeRiderAsset($request->file('photo'), 'photo'),
            'bank_name' => $payload['bank_name'] ?: null,
            'account_number' => $payload['account_number'] ?: null,
            'account_name' => $payload['account_name'] ?: null,
            'bike_make' => $payload['bike_make'] ?: null,
            'bike_model' => $payload['bike_model'] ?: null,
            'bike_plate_number' => $payload['bike_plate_number'] ?: null,
            'identification_type' => $payload['identification_type'] ?: null,
            'identification_number' => $payload['identification_number'] ?: null,
            'identification_document_path' => $this->storeRiderAsset($request->file('identification_document'), 'identification'),
            'status' => $payload['status'],
        ]);

        $this->writeAuditLog($request, 'logistics', 'rider_created', ['rider_id' => $rider->id, 'code' => $rider->code]);

        return redirect()->route('gondal.logistics', ['tab' => 'riders'])->with('success', __('Rider created successfully.'));
    }

    public function logisticsRiderDetail(string $id)
    {
        $rider = LogisticsRider::query()
            ->with(['trips.cooperative'])
            ->withCount('trips')
            ->findOrFail($id);

        $trips = $rider->trips->sortByDesc(fn (LogisticsTrip $trip) => optional($trip->trip_date)?->timestamp ?? 0)->values();

        return view('gondal.rider-detail', [
            'rider' => $rider,
            'trips' => $trips,
            'totalLiters' => round((float) $trips->sum('volume_liters'), 2),
            'totalFuelCost' => round((float) $trips->sum('fuel_cost'), 2),
            'lastTripDate' => optional($trips->first()?->trip_date)->toDateString(),
        ]);
    }

    public function operations(Request $request)
    {
        $tab = $this->resolveModuleTab($request, 'operations', 'costs');

        $query = OperationCost::query()->with('cooperative');

        if ($from = $request->query('from')) {
            $query->whereDate('cost_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('cost_date', '<=', $to);
        }
        if ($category = (string) $request->query('category', '')) {
            $query->where('category', $category);
        }

        $costs = $query->orderByDesc('cost_date')->take(50)->get();
        $allCosts = OperationCost::query()->with('cooperative')->get();

        $weeklySummary = $allCosts->groupBy(fn (OperationCost $cost) => Carbon::parse($cost->cost_date)->startOfWeek()->toDateString())
            ->map(function (Collection $group, string $weekStart) {
                return [
                    'week' => Carbon::parse($weekStart)->format('M j').' - '.Carbon::parse($weekStart)->endOfWeek()->format('M j'),
                    'entries' => $group->count(),
                    'total' => round((float) $group->sum('amount'), 2),
                    'average' => round((float) $group->avg('amount'), 2),
                    'top_category' => (string) $group->groupBy('category')->sortByDesc(fn (Collection $items) => $items->sum('amount'))->keys()->first(),
                ];
            })->values();

        $centerRanking = $allCosts->groupBy(fn (OperationCost $cost) => $cost->cooperative?->name ?: 'Unassigned')
            ->map(fn (Collection $group, string $name) => [
                'name' => $name,
                'entries' => $group->count(),
                'total' => round((float) $group->sum('amount'), 2),
                'average' => round((float) $group->avg('amount'), 2),
            ])->sortByDesc('total')->values();

        return view('gondal.operations', [
            'tab' => $tab,
            'visibleTabs' => $this->visibleModuleTabs($request, 'operations'),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'selectedCategory' => (string) $request->query('category', ''),
            'cards' => [
                ['label' => 'Total Spend', 'value' => '₦'.number_format((float) $allCosts->sum('amount'), 2)],
                ['label' => 'Pending Costs', 'value' => number_format((int) $allCosts->where('status', 'pending')->count())],
                ['label' => 'Approved Costs', 'value' => number_format((int) $allCosts->where('status', 'approved')->count())],
                ['label' => 'Centers Reporting', 'value' => number_format((int) $allCosts->pluck('cooperative_id')->filter()->unique()->count())],
            ],
            'costs' => $costs,
            'weeklySummary' => $weeklySummary,
            'centerRanking' => $centerRanking,
            'cooperatives' => Cooperative::query()->orderBy('name')->get(),
        ]);
    }

    public function storeOperationCost(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'operations', 'costs', true);
        $this->requireModulePermission($request, 'operations', $tab, 'create');

        $payload = $this->validateOperationCostPayload($request);
        $cost = OperationCost::query()->create($payload);

        $this->writeAuditLog($request, 'operations', 'cost_created', [
            'cost_id' => $cost->id,
            'cooperative_id' => $cost->cooperative_id,
            'amount' => $cost->amount,
        ]);

        return redirect()->route('gondal.operations', ['tab' => $tab])->with('success', __('Operational cost recorded successfully.'));
    }

    public function approveOperationCost(string $id, Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'operations', 'costs', 'edit');

        $cost = OperationCost::query()->findOrFail($id);
        $userType = strtolower($request->user()->type);

        $isED = in_array($userType, ['executive director', 'company', 'super admin']);
        $isLead = $isED || in_array($userType, ['component lead']);
        $isFinance = $isLead || in_array($userType, ['finance officer', 'finance']);

        if ($cost->approval_status === 'pending') {
            if (! $isLead) {
                return back()->with('error', __('Only Component Leads or higher can review pending costs.'));
            }
            $cost->update(['approval_status' => 'reviewed']);

            return back()->with('success', __('Operational cost reviewed by Component Lead.'));
        } elseif ($cost->approval_status === 'reviewed') {
            if (! $isFinance) {
                return back()->with('error', __('Only Finance or higher can approve reviewed costs.'));
            }
            $cost->update(['approval_status' => 'approved', 'status' => 'approved']);

            return back()->with('success', __('Operational cost fully approved by Finance.'));
        }

        return back()->with('error', __('Invalid approval state.'));
    }

    public function requisitions(Request $request)
    {
        $this->requireModuleAccess($request, 'requisitions');

        $tab = in_array((string) $request->query('tab', 'all'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->query('tab', 'all')
            : 'all';

        $query = Requisition::query()->with(['requester', 'cooperative', 'items', 'events.actor'])->latest();

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $requisitions = $query->get();

        return view('gondal.requisitions', [
            'tab' => $tab,
            'statusTabs' => GondalPermissionRegistry::pageTabs('requisitions'),
            'requisitions' => $requisitions,
            'cooperatives' => Cooperative::query()->orderBy('name')->get(),
        ]);
    }

    public function requisitionDetail(string $id)
    {
        abort_unless(GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'details', 'show'), 403);

        return view('gondal.requisition-detail', [
            'requisition' => $this->resolveRequisition($id),
        ]);
    }

    public function storeRequisition(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'requisitions', 'requests', 'create');

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'in:low,medium,high'],
            'cooperative_id' => ['nullable', 'integer', 'exists:cooperatives,id'],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'item_name' => ['array'],
            'item_name.*' => ['nullable', 'string', 'max:255'],
            'item_quantity' => ['array'],
            'item_quantity.*' => ['nullable', 'numeric', 'min:0.01'],
            'item_unit' => ['array'],
            'item_unit.*' => ['nullable', 'string', 'max:50'],
            'item_cost' => ['array'],
            'item_cost.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->workflowService->create($request->user(), $payload, $this->requisitionItemsFromPayload($payload));

        return redirect()->route('gondal.requisitions')->with('success', __('Requisition created successfully.'));
    }

    public function approveRequisition(string $id, Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'requisitions', 'approvals', 'edit');

        $requisition = $this->resolveRequisition($id);
        $userType = strtolower($request->user()->type);
        $amount = $requisition->total_amount;

        $isED = in_array($userType, ['executive director', 'company', 'super admin']);
        $isLead = $isED || in_array($userType, ['component lead']);
        $isFinance = $isLead || in_array($userType, ['finance officer', 'finance']);

        if ($amount > 200000 && ! $isED) {
            return back()->with('error', __('Only the Executive Director can approve requisitions over ₦200,000.'));
        } elseif ($amount >= 50000 && $amount <= 200000 && ! $isLead) {
            return back()->with('error', __('Only a Component Lead or Exec. Director can approve requisitions over ₦50,000.'));
        } elseif ($amount < 50000 && ! $isFinance) {
            return back()->with('error', __('You do not have the required role to approve this requisition.'));
        }

        try {
            $this->workflowService->approve($requisition, $request->user());
        } catch (HttpExceptionInterface $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('gondal.requisitions')->with('success', __('Requisition approved.'));
    }

    public function rejectRequisition(string $id, Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'requisitions', 'approvals', 'edit');

        $requisition = $this->resolveRequisition($id);
        $userType = strtolower($request->user()->type);
        $amount = $requisition->total_amount;

        $isED = in_array($userType, ['executive director', 'company', 'super admin']);
        $isLead = $isED || in_array($userType, ['component lead']);
        $isFinance = $isLead || in_array($userType, ['finance officer', 'finance']);

        if ($amount > 200000 && ! $isED) {
            return back()->with('error', __('Only the Executive Director can reject requisitions over ₦200,000.'));
        } elseif ($amount >= 50000 && $amount <= 200000 && ! $isLead) {
            return back()->with('error', __('Only a Component Lead or Exec. Director can reject requisitions over ₦50,000.'));
        } elseif ($amount < 50000 && ! $isFinance) {
            return back()->with('error', __('You do not have the required role to reject this requisition.'));
        }

        try {
            $this->workflowService->reject($requisition, $request->user(), $request->input('notes'));
        } catch (HttpExceptionInterface $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('gondal.requisitions')->with('success', __('Requisition rejected.'));
    }

    public function payments(Request $request)
    {
        $tab = $this->resolveModuleTab($request, 'payments', 'overview');
        $selectedStatus = (string) $request->query('status', '');
        $batchesQuery = PaymentBatch::query()
            ->when($selectedStatus !== '', fn ($query) => $query->where('status', $selectedStatus));
        $this->programScopeService->scopedPaymentBatchesQuery($batchesQuery, $request->user());
        $batches = $batchesQuery
            ->orderByDesc('period_end')
            ->get();

        $settlementRunsQuery = SettlementRun::query()->with('farmer');
        $this->programScopeService->scopedSettlementRunsQuery($settlementRunsQuery, $request->user());
        $settlementRuns = $settlementRunsQuery
            ->orderByDesc('period_end')
            ->take(25)
            ->get();

        $creditsQuery = InventoryCredit::query()
            ->with('item')
            ->when($selectedStatus !== '', fn ($query) => $query->where('status', $selectedStatus));
        $this->programScopeService->scopedInventoryCreditsQuery($creditsQuery, $request->user());
        $credits = $creditsQuery
            ->orderByDesc('credit_date')
            ->get();

        $ordersQuery = GondalOrder::query()
            ->with(['farmer', 'items.item'])
            ->when($selectedStatus !== '', fn ($query) => $query->where('status', $selectedStatus));
        $this->programScopeService->scopedOrdersQuery($ordersQuery, $request->user());
        $orders = $ordersQuery
            ->orderByDesc('fulfilled_at')
            ->orderByDesc('ordered_on')
            ->get();
        $reconciliationRows = $credits->map(function (InventoryCredit $credit): array {
            return [
                'date' => optional($credit->credit_date)->toDateString(),
                'reference' => 'CR-'.$credit->id,
                'source' => 'Inventory Credit',
                'customer' => $credit->customer_name,
                'item' => $credit->item?->name ?: 'N/A',
                'payment_mode' => 'milk_deduction',
                'amount' => (float) $credit->outstanding_amount > 0 ? (float) $credit->outstanding_amount : (float) $credit->amount,
                'status' => $credit->status,
            ];
        })->values()->merge(
            $orders
                ->where('status', '!=', 'cancelled')
                ->where('outstanding_amount', '>', 0)
                ->map(function (GondalOrder $order): array {
                    return [
                        'date' => optional($order->fulfilled_at)->toDateString() ?: optional($order->ordered_on)->toDateString(),
                        'reference' => $order->reference,
                        'source' => 'Order',
                        'customer' => $order->farmer?->name ?: 'N/A',
                        'item' => $order->items->pluck('item.name')->filter()->join(', ') ?: 'N/A',
                        'payment_mode' => str_replace('_', ' ', $order->payment_mode),
                        'amount' => (float) $order->outstanding_amount,
                        'status' => $order->status,
                    ];
                })
        )->sortByDesc('date')->values();

        return view('gondal.payments', [
            'tab' => $tab,
            'visibleTabs' => $this->visibleModuleTabs($request, 'payments'),
            'selectedStatus' => $selectedStatus,
            'overviewCards' => [
                ['title' => 'Farmer Payments', 'amount' => '₦'.number_format((float) $batches->where('payee_type', 'farmer')->sum('total_amount'), 2), 'meta' => $batches->where('payee_type', 'farmer')->count().' batches'],
                ['title' => 'Rider Payments', 'amount' => '₦'.number_format((float) $batches->where('payee_type', 'rider')->sum('total_amount'), 2), 'meta' => $batches->where('payee_type', 'rider')->count().' batches'],
                ['title' => 'Staff Payments', 'amount' => '₦'.number_format((float) $batches->where('payee_type', 'staff')->sum('total_amount'), 2), 'meta' => $batches->where('payee_type', 'staff')->count().' batches'],
                ['title' => 'Open Reconciliation', 'amount' => '₦'.number_format((float) $credits->where('status', 'open')->sum('amount'), 2), 'meta' => $credits->where('status', 'open')->count().' credits'],
                ['title' => 'Settlement Runs', 'amount' => '₦'.number_format((float) $settlementRuns->sum('net_payout'), 2), 'meta' => $settlementRuns->count().' runs'],
                ['title' => 'Milk Deduction Orders', 'amount' => '₦'.number_format((float) $orders->where('payment_mode', 'milk_deduction')->sum('outstanding_amount'), 2), 'meta' => $orders->where('payment_mode', 'milk_deduction')->where('status', '!=', 'cancelled')->count().' open orders'],
                ['title' => 'Sponsor Funded Orders', 'amount' => '₦'.number_format((float) $orders->where('payment_mode', 'sponsor_funded')->sum('outstanding_amount'), 2), 'meta' => $orders->where('payment_mode', 'sponsor_funded')->where('status', '!=', 'cancelled')->count().' outstanding'],
            ],
            'batches' => $batches,
            'settlementRuns' => $settlementRuns,
            'reconciliationRows' => $reconciliationRows,
            'farmers' => $this->programScopeService->scopedFarmersQuery(Vender::query(), $request->user())->orderBy('name')->get(),
            'orders' => $orders,
        ]);
    }

    public function runFarmerSettlement(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'payments', 'batches', 'create');

        $payload = $request->validate([
            'farmer_id' => ['required', 'exists:venders,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'max_deduction_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payout_floor_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $settlementRun = $this->settlementService->runFarmerSettlement($payload, $request->user());

        $this->writeAuditLog($request, 'payments', 'settlement_run_created', [
            'settlement_run_id' => $settlementRun->id,
            'farmer_id' => $settlementRun->farmer_id,
            'gross_milk_value' => $settlementRun->gross_milk_value,
            'total_deductions' => $settlementRun->total_deductions,
            'net_payout' => $settlementRun->net_payout,
        ]);

        return redirect()
            ->route('gondal.payments', ['tab' => 'batches'])
            ->with('success', __('Settlement run completed successfully.'));
    }

    public function storePaymentBatch(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'payments', 'batches', true);
        $this->requireModulePermission($request, 'payments', $tab, 'create');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payee_type' => ['required', 'in:farmer,rider,staff'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'status' => ['required', 'in:draft,processing,approved,completed'],
            'total_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $batch = PaymentBatch::query()->create($payload);
        $this->writeAuditLog($request, 'payments', 'batch_created', ['batch_id' => $batch->id, 'name' => $batch->name]);

        return redirect()->route('gondal.payments', ['tab' => $tab])->with('success', __('Payment batch created successfully.'));
    }

    public function processPaymentBatch(string $id, Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'payments', 'batches', 'edit');

        $batch = PaymentBatch::query()->findOrFail($id);

        if ($batch->status !== 'approved') {
            return back()->with('error', __('Only approved batches can be processed.'));
        }

        // Simulate payment gateway processing
        $batch->update(['status' => 'completed']);

        Payment::query()->where('batch_id', $batch->id)->update([
            'status' => 'paid',
            'gateway_reference' => 'PAY-'.strtoupper(uniqid()),
            'payment_date' => now(),
        ]);

        $this->writeAuditLog($request, 'payments', 'batch_processed', [
            'batch_id' => $batch->id,
            'amount' => $batch->total_amount,
        ]);

        return back()->with('success', __('Payment batch processed successfully.'));
    }

    public function inventory(Request $request)
    {
        $tab = $this->resolveModuleTab($request, 'inventory', 'sales');
        if ($tab === 'agents') {
            return redirect()->route('gondal.agents');
        }

        $creatorId = $request->user()->creatorId();
        $currentAgentProfile = $this->currentUserAgentProfile($request->user());
        $visibleFarmerIds = $this->visibleFarmerIdsForAgent($currentAgentProfile);

        $salesQuery = InventorySale::query()
            ->with(['item', 'vender', 'agentProfile.user', 'agentProfile.vender'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('sold_on', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('sold_on', '<=', $request->query('to')))
            ->orderByDesc('sold_on');
        if ($currentAgentProfile) {
            $salesQuery->where('agent_profile_id', $currentAgentProfile->id);
        }
        $sales = $salesQuery->get();

        $creditsQuery = InventoryCredit::query()
            ->with(['item', 'vender', 'agentProfile.user', 'agentProfile.vender'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('credit_date');
        if ($currentAgentProfile) {
            $creditsQuery->where('agent_profile_id', $currentAgentProfile->id);
        }
        $credits = $creditsQuery->get();
        $items = InventoryItem::query()->orderBy('name')->get();
        $warehouses = Warehouse::query()
            ->where('created_by', $creatorId)
            ->orderBy('name')
            ->get();
        $warehouseStocks = WarehouseStock::query()
            ->with(['warehouse', 'item'])
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->orderByDesc('quantity')
            ->get();
        $oneStopShops = OneStopShop::query()
            ->with(['warehouse', 'community'])
            ->where('created_by', $creatorId)
            ->orderBy('name')
            ->get();
        $oneStopShopStocks = OneStopShopStock::query()
            ->with(['oneStopShop', 'item'])
            ->whereIn('one_stop_shop_id', $oneStopShops->pluck('id'))
            ->orderByDesc('quantity')
            ->get();
        $agentProfilesQuery = AgentProfile::query()
            ->with(['user.roles', 'vender', 'supervisor', 'communityRecord', 'oneStopShop'])
            ->orderBy('agent_code');
        if ($currentAgentProfile) {
            $agentProfilesQuery->where('id', $currentAgentProfile->id);
        }
        $agentProfiles = $agentProfilesQuery->get();

        $stockIssuesQuery = StockIssue::query()
            ->with(['agentProfile.user', 'agentProfile.vender', 'item', 'issuer', 'warehouse', 'oneStopShop'])
            ->where('issue_stage', 'oss_to_agent')
            ->orderByDesc('issued_on')
            ->orderByDesc('id');
        if ($currentAgentProfile) {
            $stockIssuesQuery->where('agent_profile_id', $currentAgentProfile->id);
        }
        $stockIssues = $stockIssuesQuery->get();

        $remittancesQuery = AgentRemittance::query()
            ->with(['agentProfile.user', 'agentProfile.vender', 'receiver', 'oneStopShop'])
            ->orderByDesc('remitted_at');
        if ($currentAgentProfile) {
            $remittancesQuery->where('agent_profile_id', $currentAgentProfile->id);
        }
        $remittances = $remittancesQuery->get();

        $reconciliationsQuery = InventoryReconciliation::query()
            ->with(['agentProfile.user', 'agentProfile.vender', 'item', 'submitter', 'reviewer', 'oneStopShop'])
            ->orderByDesc('period_end')
            ->orderByDesc('id');
        if ($currentAgentProfile) {
            $reconciliationsQuery->where('agent_profile_id', $currentAgentProfile->id);
        }
        $reconciliations = $reconciliationsQuery->get();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $farmerCreditsQuery = Vender::query()
            ->whereHas('inventoryCredits', function ($q) {
                $q->whereIn('status', ['open', 'partial']);
            })
            ->withSum(['inventoryCredits as total_owed' => function ($q) {
                $q->whereIn('status', ['open', 'partial']);
            }], 'amount');
        if ($visibleFarmerIds !== null) {
            $farmerCreditsQuery->whereIn('id', $visibleFarmerIds);
        }
        $farmerCredits = $farmerCreditsQuery->get()->map(function ($vender) {
            $vender->last_credit_date = InventoryCredit::where('vender_id', $vender->id)->max('credit_date');

            return $vender;
        });

        $internalUsers = User::query()
            ->where(function ($query) use ($creatorId) {
                $query->where('id', $creatorId)
                    ->orWhere('created_by', $creatorId);
            })
            ->whereNotIn('type', ['client', 'company', 'super admin'])
            ->orderBy('name')
            ->get();

        $independentAgentUsers = User::query()
            ->where(function ($query) use ($creatorId) {
                $query->where('id', $creatorId)
                    ->orWhere('created_by', $creatorId);
            })
            ->where('type', 'client')
            ->orderBy('name')
            ->get();

        $independentAgentUsers = User::query()
            ->where(function ($query) use ($creatorId) {
                $query->where('id', $creatorId)
                    ->orWhere('created_by', $creatorId);
            })
            ->where('type', 'client')
            ->orderBy('name')
            ->get();
        $supervisors = $internalUsers;
        $farmersQuery = Vender::query()->orderBy('name');
        if ($visibleFarmerIds !== null) {
            $farmersQuery->whereIn('id', $visibleFarmerIds);
        }
        $farmers = $farmersQuery->get();

        $creditExposureByAgentQuery = InventoryCredit::query()
            ->selectRaw('agent_profile_id, COALESCE(SUM(CASE WHEN outstanding_amount > 0 THEN outstanding_amount ELSE amount END), 0) as balance')
            ->whereNotNull('agent_profile_id')
            ->whereIn('status', ['open', 'partial'])
            ->groupBy('agent_profile_id');
        if ($currentAgentProfile) {
            $creditExposureByAgentQuery->where('agent_profile_id', $currentAgentProfile->id);
        }
        $creditExposureByAgent = $creditExposureByAgentQuery->pluck('balance', 'agent_profile_id');
        $agentKpis = [
            'agents' => $agentProfiles->count(),
            'stock_issued' => (float) $stockIssues->sum('quantity_issued'),
            'remitted' => (float) $remittances->sum('amount'),
            'open_variances' => $reconciliations->whereIn('status', ['draft', 'submitted', 'under_review', 'approved_with_variance', 'escalated'])->count(),
        ];
        $agentSummaries = $agentProfiles->map(function (AgentProfile $agent) use ($stockIssues, $sales, $credits, $remittances, $reconciliations, $todayStart, $todayEnd) {
            $agentIssues = $stockIssues->where('agent_profile_id', $agent->id);
            $agentSales = $sales->where('agent_profile_id', $agent->id);
            $agentCredits = $credits->where('agent_profile_id', $agent->id);
            $agentRemittances = $remittances->where('agent_profile_id', $agent->id);
            $latestReconciliation = $reconciliations->where('agent_profile_id', $agent->id)->sortByDesc('period_end')->first();

            $openingStock = (float) $agentIssues
                ->filter(fn (StockIssue $issue) => optional($issue->issued_on)?->lt($todayStart))
                ->sum('quantity_issued')
                - (float) $agentSales
                    ->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->lt($todayStart))
                    ->sum('quantity');

            $issuedToday = (float) $agentIssues
                ->filter(fn (StockIssue $issue) => optional($issue->issued_on)?->betweenIncluded($todayStart, $todayEnd))
                ->sum('quantity_issued');
            $soldToday = (float) $agentSales
                ->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->betweenIncluded($todayStart, $todayEnd))
                ->sum('quantity');
            $cashSales = (float) $agentSales
                ->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->betweenIncluded($todayStart, $todayEnd) && $sale->payment_method === 'Cash')
                ->sum(fn (InventorySale $sale) => $sale->total_amount > 0 ? $sale->total_amount : ($sale->quantity * $sale->unit_price));
            $transferSales = (float) $agentSales
                ->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->betweenIncluded($todayStart, $todayEnd) && $sale->payment_method === 'Transfer')
                ->sum(fn (InventorySale $sale) => $sale->total_amount > 0 ? $sale->total_amount : ($sale->quantity * $sale->unit_price));
            $creditSales = (float) $agentSales
                ->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->betweenIncluded($todayStart, $todayEnd) && $sale->payment_method === 'Credit')
                ->sum(fn (InventorySale $sale) => $sale->total_amount > 0 ? $sale->total_amount : ($sale->quantity * $sale->unit_price));
            $expectedRemittance = $cashSales + $transferSales;
            $remittedToday = (float) $agentRemittances
                ->filter(fn (AgentRemittance $remittance) => optional($remittance->remitted_at)?->betweenIncluded($todayStart, $todayEnd))
                ->sum('amount');
            $outstandingCredit = (float) $agentCredits
                ->whereIn('status', ['open', 'partial'])
                ->sum(fn (InventoryCredit $credit) => $credit->outstanding_amount > 0 ? $credit->outstanding_amount : $credit->amount);
            $expectedClosing = $openingStock + $issuedToday - $soldToday;

            return [
                'agent' => $agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name ?: __('Unknown agent'),
                'agent_code' => $agent->agent_code,
                'opening_stock' => $openingStock,
                'issued_today' => $issuedToday,
                'sold_today' => $soldToday,
                'expected_closing' => $expectedClosing,
                'counted_stock' => $latestReconciliation?->counted_stock_qty,
                'stock_variance' => $latestReconciliation?->stock_variance_qty,
                'expected_remittance' => $expectedRemittance,
                'remitted_today' => $remittedToday,
                'cash_variance' => $remittedToday - $expectedRemittance,
                'credit_today' => $creditSales,
                'outstanding_credit' => $outstandingCredit,
                'status' => $latestReconciliation?->status ?: 'No snapshot',
            ];
        })->sortBy('agent')->values();
        $reconciliationSummaryCards = [
            ['label' => 'Expected Cash Today', 'value' => '₦'.number_format((float) $agentSummaries->sum('expected_remittance'), 2)],
            ['label' => 'Remitted Today', 'value' => '₦'.number_format((float) $agentSummaries->sum('remitted_today'), 2)],
            ['label' => 'Cash Short / Over', 'value' => '₦'.number_format((float) $agentSummaries->sum('cash_variance'), 2)],
            ['label' => 'Outstanding Credit', 'value' => '₦'.number_format((float) $agentSummaries->sum('outstanding_credit'), 2)],
        ];
        $reconciliationActionRows = $agentSummaries->map(function (array $summary) {
            $hasSnapshot = $summary['status'] !== 'No snapshot';
            $stockVariance = (float) ($summary['stock_variance'] ?? 0);
            $cashVariance = (float) $summary['cash_variance'];
            $creditOutstanding = (float) $summary['outstanding_credit'];

            if (! $hasSnapshot) {
                $action = __('Count stock and submit snapshot');
                $priority = 'warning';
            } elseif (abs($stockVariance) > 0.0001 || abs($cashVariance) > 0.0001) {
                $action = __('Review shortage / overage');
                $priority = 'danger';
            } elseif ($creditOutstanding > 0) {
                $action = __('Track outstanding credit');
                $priority = 'info';
            } else {
                $action = __('Balanced');
                $priority = 'success';
            }

            return $summary + [
                'action' => $action,
                'priority' => $priority,
            ];
        })->sortBy([
            fn (array $row) => match ($row['priority']) {
                'danger' => 0,
                'warning' => 1,
                'info' => 2,
                default => 3,
            },
            fn (array $row) => $row['agent'],
        ])->values();
        $reconciliationWorkflowCards = [
            [
                'step' => '1',
                'title' => __('Issue Stock'),
                'text' => __('Warehouse sends stock to the agent.'),
            ],
            [
                'step' => '2',
                'title' => __('Record Sales & Remittance'),
                'text' => __('Agent sales and cash return are captured.'),
            ],
            [
                'step' => '3',
                'title' => __('Count & Submit'),
                'text' => __('Physical count is entered and snapshot submitted.'),
            ],
            [
                'step' => '4',
                'title' => __('Review Variance'),
                'text' => __('Supervisor or finance resolves the gap.'),
            ],
        ];

        return view('gondal.inventory', [
            'tab' => $tab,
            'visibleTabs' => collect($this->visibleModuleTabs($request, 'inventory'))
                ->reject(fn (array $visibleTab) => ($visibleTab['key'] ?? null) === 'agents')
                ->values()
                ->all(),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'selectedStatus' => (string) $request->query('status', ''),
            'summaryCards' => [
                ['label' => 'Stock Value', 'value' => '₦'.number_format((float) $items->sum(fn (InventoryItem $item) => $item->stock_qty * $item->unit_price), 2)],
                ['label' => 'Units In Stock', 'value' => number_format((float) $items->sum('stock_qty'), 2)],
                ['label' => 'Sales Total', 'value' => '₦'.number_format((float) $sales->sum(fn (InventorySale $sale) => $sale->total_amount > 0 ? $sale->total_amount : ($sale->quantity * $sale->unit_price)), 2)],
                ['label' => 'Open Credit', 'value' => '₦'.number_format((float) $credits->whereIn('status', ['open', 'partial'])->sum(fn (InventoryCredit $credit) => $credit->outstanding_amount > 0 ? $credit->outstanding_amount : $credit->amount), 2)],
            ],
            'sales' => $sales,
            'credits' => $credits,
            'farmerCredits' => $farmerCredits,
            'items' => $items,
            'farmers' => $farmers,
            'agentProfiles' => $agentProfiles,
            'stockIssues' => $stockIssues,
            'remittances' => $remittances,
            'reconciliations' => $reconciliations,
            'internalUsers' => $internalUsers,
            'supervisors' => $supervisors,
            'creditExposureByAgent' => $creditExposureByAgent,
            'agentKpis' => $agentKpis,
            'agentSummaries' => $agentSummaries,
            'reconciliationSummaryCards' => $reconciliationSummaryCards,
            'reconciliationActionRows' => $reconciliationActionRows,
            'reconciliationWorkflowCards' => $reconciliationWorkflowCards,
            'agentStateOptions' => Community::query()->pluck('state')->filter()->unique()->sort()->values(),
            'agentLocationHierarchy' => $this->communityLocationHierarchy(),
            'communityOptions' => Community::query()->orderBy('name')->get(),
            'warehouses' => $warehouses,
            'warehouseStocks' => $warehouseStocks,
            'oneStopShops' => $oneStopShops,
            'oneStopShopStocks' => $oneStopShopStocks,
        ]);
    }

    public function agents(Request $request)
    {
        $this->requireModulePermission($request, 'inventory', 'agents', 'manage');

        return view('gondal.agents', $this->agentProfilePageData($request));
    }

    public function agentDashboard(Request $request)
    {
        $this->requireModulePermission($request, 'inventory', 'agents', 'manage');

        return view('gondal.agent-dashboard', $this->agentDashboardPayload($request));
    }

    public function agentAnalytics(Request $request)
    {
        $this->requireModulePermission($request, 'inventory', 'agents', 'manage');

        return view('gondal.agent-analytics', $this->agentAnalyticsPayload($request));
    }

    public function downloadAgentImportSample(Request $request): Response
    {
        $this->requireModulePermission($request, 'inventory', 'agents', 'manage');

        $community = Community::query()
            ->whereNotNull('state')
            ->whereNotNull('lga')
            ->orderBy('state')
            ->orderBy('lga')
            ->orderBy('name')
            ->first();

        $fallbackState = $community?->state ?: 'Adamawa';
        $fallbackLga = $community?->lga ?: 'Yola South';
        $fallbackCommunity = $community?->name ?: 'Sabon Pegi';

        $internalUsers = User::query()->where('type', '!=', 'client')->orderBy('id')->take(4)->get();
        $partnerUsers = User::query()->where('type', 'client')->orderBy('id')->take(2)->get();
        $sampleProject = Project::query()->orderBy('id')->first();
        $sampleCooperatives = Cooperative::query()->orderBy('id')->take(2)->get();
        $primaryUser = $internalUsers->get(0);
        $secondaryUser = $internalUsers->get(1) ?: $primaryUser;
        $firstSupervisor = $internalUsers->get(2) ?: $secondaryUser;
        $secondSupervisor = $internalUsers->get(3) ?: $primaryUser;
        $partnerUser = $partnerUsers->get(0);
        $sampleCooperativeList = $sampleCooperatives->pluck('code')->filter()->implode('|');
        $sampleCooperativeList = $sampleCooperativeList !== '' ? $sampleCooperativeList : $sampleCooperatives->pluck('id')->implode('|');
        $sampleCooperativeList = $sampleCooperativeList !== '' ? $sampleCooperativeList : '1';

        $rows = [
            [
                'user_id' => $primaryUser?->email ?: 'agent.one@example.com',
                'internal_user_email' => $primaryUser?->email ?: 'agent.one@example.com',
                'supervisor_user_id' => $firstSupervisor?->email ?: 'supervisor.one@example.com',
                'supervisor_email' => $firstSupervisor?->email ?: 'supervisor.one@example.com',
                'project_name' => $sampleProject?->project_name ?: '',
                'cooperative_ids' => $sampleCooperativeList,
                'agent_type' => 'employee',
                'first_name' => 'Amina',
                'middle_name' => 'Bello',
                'last_name' => 'Gongola',
                'gender' => 'female',
                'phone_number' => '08030000001',
                'email' => 'amina.gongola@example.com',
                'nin' => '12345678901',
                'state' => $fallbackState,
                'lga' => $fallbackLga,
                'community' => $fallbackCommunity,
                'residential_address' => 'House 12, Market Road, '.$fallbackCommunity,
                'permanent_address' => 'Family Compound, '.$fallbackCommunity,
                'one_stop_shop_name' => 'Yola South OSS',
                'assigned_communities' => $fallbackCommunity,
                'assigned_warehouse' => 'Central Store',
                'reconciliation_frequency' => 'weekly',
                'settlement_mode' => 'consignment',
                'account_number' => '0123456789',
                'account_name' => 'Amina Bello Gongola',
                'bank_details' => 'Zenith Bank, Yola Branch',
                'status' => 'active',
            ],
            [
                'user_id' => $secondaryUser?->email ?: 'agent.two@example.com',
                'internal_user_email' => $secondaryUser?->email ?: 'agent.two@example.com',
                'supervisor_user_id' => $secondSupervisor?->email ?: 'supervisor.two@example.com',
                'supervisor_email' => $secondSupervisor?->email ?: 'supervisor.two@example.com',
                'project_name' => $sampleProject?->project_name ?: 'Community Livestock Project',
                'cooperative_ids' => $sampleCooperativeList,
                'agent_type' => 'independent_reseller',
                'first_name' => 'Usman',
                'middle_name' => '',
                'last_name' => 'Numan',
                'gender' => 'male',
                'phone_number' => '08030000002',
                'email' => 'usman.numan@example.com',
                'nin' => '10987654321',
                'state' => $fallbackState,
                'lga' => $fallbackLga,
                'community' => $fallbackCommunity,
                'residential_address' => 'Opposite Primary Health Centre, '.$fallbackCommunity,
                'permanent_address' => 'Unguwan Gada, '.$fallbackCommunity,
                'one_stop_shop_name' => 'Numan OSS',
                'assigned_communities' => $fallbackCommunity,
                'assigned_warehouse' => 'Regional Drug Hub',
                'reconciliation_frequency' => 'weekly',
                'settlement_mode' => 'consignment',
                'account_number' => '1234509876',
                'account_name' => 'Usman Numan',
                'bank_details' => 'UBA, Jimeta Branch',
                'status' => 'active',
            ],
        ];

        return $this->streamCsvDownload('gondal-agents-import-sample.csv', [
            'user_id',
            'internal_user_email',
            'supervisor_user_id',
            'supervisor_email',
            'project_name',
            'cooperative_ids',
            'agent_type',
            'first_name',
            'middle_name',
            'last_name',
            'gender',
            'phone_number',
            'email',
            'nin',
            'state',
            'lga',
            'community',
            'residential_address',
            'permanent_address',
            'one_stop_shop_name',
            'assigned_communities',
            'assigned_warehouse',
            'reconciliation_frequency',
            'settlement_mode',
            'account_number',
            'account_name',
            'bank_details',
            'status',
        ], $rows);
    }

    public function storeInventorySale(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'sales', 'create');

        $payload = $request->validate([
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'agent_profile_id' => ['nullable', 'exists:gondal_agent_profiles,id'],
            'extension_visit_id' => ['nullable', 'exists:gondal_extension_visits,id'],
            'vender_id' => ['nullable', 'exists:venders,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:Cash,Credit,Transfer,Milk Collection Balance'],
            'sold_on' => ['required', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date', 'after_or_equal:sold_on'],
        ]);

        $sale = $this->createInventorySaleRecord($payload);

        $this->writeAuditLog($request, 'inventory', 'sale_recorded', [
            'sale_id' => $sale->id,
            'inventory_item_id' => $sale->inventory_item_id,
            'quantity' => $sale->quantity,
        ]);

        return redirect()->route('gondal.inventory', ['tab' => 'sales'])->with('success', __('Inventory sale recorded successfully.'));
    }

    public function storeInventoryItem(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'stock', 'create');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'stock_qty' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $item = InventoryItem::query()->create([
            'name' => $payload['name'],
            'category' => $payload['category'] ?? null,
            'unit' => $payload['unit'] ?? null,
            'sku' => 'SKU-'.str_pad((string) ((int) InventoryItem::query()->max('id') + 1), 4, '0', STR_PAD_LEFT),
            'stock_qty' => $payload['stock_qty'],
            'unit_price' => $payload['unit_price'],
            'status' => $payload['status'],
        ]);

        $this->writeAuditLog($request, 'inventory', 'item_created', ['inventory_item_id' => $item->id, 'sku' => $item->sku]);

        return redirect()->route('gondal.inventory', ['tab' => 'stock'])->with('success', __('Inventory item created successfully.'));
    }

    public function storeInventoryCredit(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'credit', 'create');

        $payload = $request->validate([
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'agent_profile_id' => ['nullable', 'exists:gondal_agent_profiles,id'],
            'vender_id' => ['nullable', 'exists:venders,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:open,partial,settled'],
            'credit_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:credit_date'],
        ]);

        $credit = InventoryCredit::query()->create([
            'inventory_item_id' => $payload['inventory_item_id'],
            'agent_profile_id' => $payload['agent_profile_id'] ?? null,
            'vender_id' => $payload['vender_id'] ?? null,
            'customer_name' => $payload['customer_name'],
            'amount' => $payload['amount'],
            'outstanding_amount' => $payload['status'] === 'settled' ? 0 : $payload['amount'],
            'status' => $payload['status'],
            'credit_date' => $payload['credit_date'],
            'due_date' => $payload['due_date'] ?? null,
        ]);
        $this->writeAuditLog($request, 'inventory', 'credit_created', ['credit_id' => $credit->id]);

        return redirect()->route('gondal.inventory', ['tab' => 'credit'])->with('success', __('Credit entry created successfully.'));
    }

    public function storeInventoryAgent(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'agents', 'create');

        $payload = $this->validateInventoryAgentPayload($request->all());
        $createdLogin = null;

        if (($payload['login_mode'] ?? 'new') === 'new') {
            $createdLogin = $this->createAgentLoginUser($payload, $request->user());
            $payload['user_id'] = $createdLogin['user']->id;
        }

        $agent = $this->createInventoryAgentProfile($payload);

        $this->writeAuditLog($request, 'inventory', 'agent_profile_created', ['agent_profile_id' => $agent->id]);

        $message = __('Agent profile created successfully.');
        $redirect = redirect()->back()->with('success', $message);

        if ($createdLogin) {
            $redirect->with('agent_login_details', [
                'agent_name' => $agent->full_name ?: $createdLogin['user']->name,
                'agent_code' => $agent->agent_code,
                'email' => $createdLogin['user']->email,
                'password' => $createdLogin['password'],
                'phone_number' => $agent->phone_number,
                'login_type' => $payload['agent_type'] ?? null,
            ]);
        }

        return $redirect;
    }

    public function importAgents(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'agents', 'create');

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = $this->importAgentRows($rows);
            $this->writeAuditLog($request, 'inventory', 'agent_profiles_imported', ['rows' => $count]);

            return redirect()->back()
                ->with('success', __('Imported :count agent(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure(url()->previous(), [], $exception);
        }
    }

    public function storeInventoryStockIssue(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'issues', 'create');

        $issue = $this->createOneStopShopToAgentIssueFromRequest($request);

        $this->writeAuditLog($request, 'inventory', 'stock_issued', ['stock_issue_id' => $issue->id]);

        return redirect()->route('gondal.inventory', ['tab' => 'issues'])->with('success', __('Stock issued from one-stop shop to agent successfully.'));
    }

    public function storeInventoryRemittance(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'remittances', 'create');

        $payload = $request->validate([
            'agent_profile_id' => ['required', 'exists:gondal_agent_profiles,id'],
            'reconciliation_mode' => ['required', 'in:daily,weekly,batch'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,transfer,pos,bank_deposit'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'remitted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $remittance = $this->inventoryWorkflowService->recordRemittance($payload, $request->user());

        $this->writeAuditLog($request, 'inventory', 'remittance_recorded', ['remittance_id' => $remittance->id]);

        return redirect()->route('gondal.inventory', ['tab' => 'remittances'])->with('success', __('Agent remittance recorded successfully.'));
    }

    public function storeInventoryReconciliation(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'reconciliation', 'create');

        $payload = $request->validate([
            'agent_profile_id' => ['required', 'exists:gondal_agent_profiles,id'],
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'reconciliation_mode' => ['required', 'in:daily,weekly,batch'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'counted_stock_qty' => ['required', 'numeric', 'min:0'],
            'agent_notes' => ['nullable', 'string'],
        ]);

        $reconciliation = $this->inventoryWorkflowService->createReconciliation($payload, $request->user());

        $this->writeAuditLog($request, 'inventory', 'reconciliation_created', ['reconciliation_id' => $reconciliation->id]);

        return redirect()->route('gondal.inventory', ['tab' => 'reconciliation'])->with('success', __('Reconciliation snapshot created successfully.'));
    }

    public function resolveInventoryReconciliation(string $id, Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'reconciliation', 'edit');

        $payload = $request->validate([
            'action' => ['required', 'in:approve,approve_with_variance,escalate,request_recount'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $reconciliation = InventoryReconciliation::query()->findOrFail($id);
        $reconciliation = $this->inventoryWorkflowService->resolveReconciliation($reconciliation, $payload, $request->user());
        $status = (string) $reconciliation->status;

        $this->writeAuditLog($request, 'inventory', 'reconciliation_resolved', [
            'reconciliation_id' => $reconciliation->id,
            'action' => $payload['action'],
            'status' => $status,
        ]);

        return redirect()->route('gondal.inventory', ['tab' => 'reconciliation'])->with('success', __('Reconciliation updated successfully.'));
    }

    public function showInventoryReconciliation(string $id, Request $request)
    {
        $this->requireModulePermission($request, 'inventory', 'reconciliation');

        $reconciliation = InventoryReconciliation::query()
            ->with(['agentProfile.user', 'agentProfile.vender', 'item', 'submitter', 'reviewer'])
            ->findOrFail($id);

        return view('gondal.reconciliation-show', [
            'reconciliation' => $reconciliation,
        ]);
    }

    public function warehouse(Request $request)
    {
        $tab = $this->resolveModuleTab($request, 'warehouse-ops', 'registry');
        $creatorId = $request->user()->creatorId();

        $warehouses = Warehouse::query()
            ->where('created_by', $creatorId)
            ->orderBy('name')
            ->get();
        $warehouseStocks = WarehouseStock::query()
            ->with(['warehouse', 'item'])
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->orderBy('warehouse_id')
            ->orderByDesc('quantity')
            ->get();
        $oneStopShops = OneStopShop::query()
            ->with(['warehouse', 'community'])
            ->where('created_by', $creatorId)
            ->orderBy('name')
            ->get();
        $oneStopShopStocks = OneStopShopStock::query()
            ->with(['oneStopShop', 'item'])
            ->whereIn('one_stop_shop_id', $oneStopShops->pluck('id'))
            ->orderBy('one_stop_shop_id')
            ->orderByDesc('quantity')
            ->get();
        $dispatches = StockIssue::query()
            ->with(['warehouse', 'oneStopShop', 'item', 'agentProfile.user', 'agentProfile.vender', 'issuer'])
            ->whereNotNull('warehouse_id')
            ->where('issue_stage', 'warehouse_to_oss')
            ->whereHas('warehouse', fn ($query) => $query->where('created_by', $creatorId))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();
        $outsideStockRows = $oneStopShopStocks
            ->filter(fn (OneStopShopStock $stock) => (float) $stock->quantity > 0.0001)
            ->map(function (OneStopShopStock $stock) {
                return [
                    'warehouse_name' => $stock->oneStopShop?->warehouse?->name ?: __('Unknown warehouse'),
                    'agent_name' => $stock->oneStopShop?->name ?: __('Unknown one-stop shop'),
                    'agent_code' => $stock->oneStopShop?->code,
                    'item_name' => $stock->item?->name ?: __('Unknown product'),
                    'item_unit' => $stock->item?->unit,
                    'issued_quantity' => (float) $stock->quantity,
                    'sold_quantity' => 0.0,
                    'unsold_quantity' => (float) $stock->quantity,
                    'sold_pending_reconciliation' => 0.0,
                    'latest_issue_date' => $stock->updated_at,
                    'references' => collect(),
                ];
            })
            ->sortBy([
                fn (array $row) => $row['warehouse_name'],
                fn (array $row) => $row['agent_name'],
                fn (array $row) => $row['item_name'],
            ])
            ->values();
        $outsideSummaryCards = [
            ['label' => 'Units At One-Stop Shops', 'value' => number_format((float) $outsideStockRows->sum('unsold_quantity'), 2)],
            ['label' => 'Active One-Stop Shops', 'value' => number_format($outsideStockRows->pluck('agent_name')->unique()->count())],
            ['label' => 'Outside Warehouse', 'value' => number_format((float) $outsideStockRows->sum('issued_quantity'), 2)],
            ['label' => 'Open Stock Lines', 'value' => number_format($outsideStockRows->count())],
        ];

        return view('gondal.warehouse', [
            'tab' => $tab,
            'visibleTabs' => $this->visibleModuleTabs($request, 'warehouse-ops'),
            'warehouses' => $warehouses,
            'warehouseStocks' => $warehouseStocks,
            'oneStopShops' => $oneStopShops,
            'oneStopShopStocks' => $oneStopShopStocks,
            'warehouseStateOptions' => Community::query()->pluck('state')->filter()->unique()->sort()->values(),
            'warehouseLocationHierarchy' => $this->communityLocationHierarchyWithIds(),
            'dispatches' => $dispatches,
            'outsideStockRows' => $outsideStockRows,
            'outsideSummaryCards' => $outsideSummaryCards,
            'items' => InventoryItem::query()->orderBy('name')->get(),
            'agents' => AgentProfile::query()->with(['user', 'vender'])->orderBy('agent_code')->get(),
            'summaryCards' => [
                ['label' => 'Total Warehouses', 'value' => number_format($warehouses->count())],
                ['label' => 'One-Stop Shops', 'value' => number_format($oneStopShops->count())],
                ['label' => 'Tracked SKUs', 'value' => number_format($warehouseStocks->pluck('inventory_item_id')->unique()->count())],
                ['label' => 'Warehouse Units', 'value' => number_format((float) $warehouseStocks->sum('quantity'), 2)],
                ['label' => 'Issued This Week', 'value' => number_format((float) $dispatches->filter(fn (StockIssue $issue) => optional($issue->issued_on)?->greaterThanOrEqualTo(now()->copy()->startOfWeek()))->sum('quantity_issued'), 2)],
            ],
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'warehouse-ops', 'registry', 'create');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'city_zip' => ['required', 'string', 'max:50'],
        ]);

        Warehouse::query()->create([
            'name' => $payload['name'],
            'address' => $payload['address'],
            'city' => $payload['city'],
            'city_zip' => $payload['city_zip'],
            'created_by' => $request->user()->creatorId(),
        ]);

        $this->writeAuditLog($request, 'warehouse-ops', 'warehouse_created', ['name' => $payload['name']]);

        return redirect()->route('gondal.warehouse', ['tab' => 'registry'])->with('success', __('Warehouse created successfully.'));
    }

    public function storeOneStopShop(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'warehouse-ops', 'registry', 'create');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'state' => ['nullable', 'string', 'max:255'],
            'lga' => ['nullable', 'string', 'max:255'],
            'community_id' => ['nullable', 'exists:gondal_communities,id'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        OneStopShop::query()->create([
            'name' => $payload['name'],
            'code' => $this->generateOneStopShopCode($payload['name']),
            'warehouse_id' => $payload['warehouse_id'] ?? null,
            'state' => $payload['state'] ?? null,
            'lga' => $payload['lga'] ?? null,
            'community_id' => $payload['community_id'] ?? null,
            'address' => $payload['address'] ?? null,
            'status' => $payload['status'],
            'created_by' => $request->user()->creatorId(),
        ]);

        return redirect()->route('gondal.warehouse', ['tab' => 'registry'])->with('success', __('One-stop shop created successfully.'));
    }

    public function storeWarehouseStock(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'warehouse-ops', 'stock', 'create');

        $payload = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
        ]);

        $warehouseStock = WarehouseStock::query()->firstOrNew([
            'warehouse_id' => $payload['warehouse_id'],
            'inventory_item_id' => $payload['inventory_item_id'],
        ]);
        $warehouseStock->created_by = $request->user()->creatorId();
        $warehouseStock->reorder_level = $payload['reorder_level'] ?? $warehouseStock->reorder_level ?? 0;
        $warehouseStock->quantity = (float) ($warehouseStock->quantity ?? 0) + (float) $payload['quantity'];
        $warehouseStock->save();

        $item = InventoryItem::query()->findOrFail($payload['inventory_item_id']);
        $item->update([
            'stock_qty' => (float) $item->stock_qty + (float) $payload['quantity'],
        ]);

        $this->writeAuditLog($request, 'warehouse-ops', 'warehouse_stock_added', [
            'warehouse_id' => $payload['warehouse_id'],
            'inventory_item_id' => $payload['inventory_item_id'],
            'quantity' => $payload['quantity'],
        ]);

        return redirect()->route('gondal.warehouse', ['tab' => 'stock'])->with('success', __('Warehouse stock updated successfully.'));
    }

    public function storeWarehouseIssue(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'warehouse-ops', 'dispatches', 'create');

        $request->merge(['issued_on' => $request->input('issued_on', now()->toDateString())]);

        $issue = $this->createWarehouseToOneStopShopIssueFromRequest($request);

        $this->writeAuditLog($request, 'warehouse-ops', 'warehouse_dispatch_created', ['stock_issue_id' => $issue->id]);

        return redirect()->route('gondal.warehouse', ['tab' => 'dispatches'])->with('success', __('Stock dispatched from warehouse to one-stop shop successfully.'));
    }

    public function extension(Request $request)
    {
        $tab = $this->resolveModuleTab($request, 'extension', 'agents');
        $currentAgentProfile = $this->currentUserAgentProfile($request->user());
        $visibleFarmerIds = $this->visibleFarmerIdsForAgent($currentAgentProfile);

        $visitsQuery = ExtensionVisit::query()->with(['farmer', 'agentProfile.user', 'sale.item'])->orderByDesc('visit_date');
        if ($currentAgentProfile) {
            $visitsQuery->where('agent_profile_id', $currentAgentProfile->id);
        }
        $visits = $visitsQuery->get();
        $trainings = ExtensionTraining::query()->orderByDesc('training_date')->get();
        $agents = $visits->groupBy('officer_name')->map(fn (Collection $group, string $officer) => [
            'officer' => $officer,
            'visits' => $group->count(),
            'farmers' => $group->pluck('farmer_id')->unique()->count(),
            'avg_score' => round((float) $group->avg('performance_score'), 1),
            'latest_visit' => optional($group->sortByDesc('visit_date')->first()?->visit_date)->toDateString(),
        ])->values();
        $performanceRows = $visits->groupBy('farmer_id')->map(function (Collection $group) {
            $first = $group->first();

            return [
                'farmer' => $first?->farmer?->name ?: 'N/A',
                'visits' => $group->count(),
                'avg_score' => round((float) $group->avg('performance_score'), 1),
                'last_topic' => $group->sortByDesc('visit_date')->first()?->topic ?: 'N/A',
            ];
        })->values();

        return view('gondal.extension', [
            'tab' => $tab,
            'visibleTabs' => $this->visibleModuleTabs($request, 'extension'),
            'summaryCards' => [
                ['label' => 'Field Visits', 'value' => number_format((int) $visits->count())],
                ['label' => 'Training Events', 'value' => number_format((int) $trainings->count())],
                ['label' => 'Active Agents', 'value' => number_format((int) $agents->count())],
                ['label' => 'Average Score', 'value' => number_format((float) $visits->avg('performance_score'), 1)],
            ],
            'agents' => $agents,
            'visits' => $visits,
            'trainings' => $trainings,
            'performanceRows' => $performanceRows,
            'farmers' => $this->farmersForVisibleScope($visibleFarmerIds),
            'agentProfiles' => $currentAgentProfile
                ? collect([$currentAgentProfile])
                : AgentProfile::query()->with(['user', 'vender'])->orderBy('agent_code')->get(),
            'items' => InventoryItem::query()->orderBy('name')->get(),
        ]);
    }

    public function storeExtensionVisit(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'extension', 'visits', true);
        $this->requireModulePermission($request, 'extension', $tab, 'create');

        $payload = $this->validateExtensionVisitPayload($request);
        $agentProfile = ! empty($payload['agent_profile_id'])
            ? AgentProfile::query()->findOrFail($payload['agent_profile_id'])
            : $this->currentUserAgentProfile($request->user());

        if (! $agentProfile) {
            throw ValidationException::withMessages([
                'agent_profile_id' => [__('Select the extension agent who is logging this visit.')],
            ]);
        }

        $farmer = Vender::query()->findOrFail($payload['farmer_id']);
        $this->assertFarmerWithinAgentScope($agentProfile, $farmer);

        $visit = DB::transaction(function () use ($payload, $agentProfile, $farmer) {
            $visit = ExtensionVisit::query()->create([
                'visit_date' => $payload['visit_date'],
                'farmer_id' => $farmer->id,
                'agent_profile_id' => $agentProfile->id,
                'officer_name' => $payload['officer_name'] ?: ($agentProfile->user?->name ?: $payload['officer_name']),
                'topic' => $payload['topic'],
                'performance_score' => $payload['performance_score'],
                'notes' => $payload['notes'] ?? null,
            ]);

            if ((bool) ($payload['record_sale'] ?? false)) {
                $this->createInventorySaleRecord([
                    'inventory_item_id' => $payload['inventory_item_id'],
                    'agent_profile_id' => $agentProfile->id,
                    'extension_visit_id' => $visit->id,
                    'vender_id' => $farmer->id,
                    'quantity' => $payload['quantity'],
                    'unit_price' => $payload['unit_price'],
                    'payment_method' => $payload['payment_method'],
                    'sold_on' => $payload['visit_date'],
                    'customer_name' => $farmer->name,
                    'due_date' => $payload['due_date'] ?? null,
                ]);
            }

            return $visit;
        });
        $this->writeAuditLog($request, 'extension', 'visit_logged', ['visit_id' => $visit->id, 'farmer_id' => $visit->farmer_id]);

        return redirect()->route('gondal.extension', ['tab' => $tab])->with('success', __('Field visit logged successfully.'));
    }

    public function storeExtensionTraining(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'extension', 'training', true);
        $this->requireModulePermission($request, 'extension', $tab, 'create');

        $payload = $this->validateExtensionTrainingPayload($request);
        $training = ExtensionTraining::query()->create($payload);
        $this->writeAuditLog($request, 'extension', 'training_created', ['training_id' => $training->id]);

        return redirect()->route('gondal.extension', ['tab' => $tab])->with('success', __('Training event created successfully.'));
    }

    public function reports(Request $request)
    {
        $this->requireModulePermission($request, 'reports', 'overview');

        $summary = $this->reportSummaryForRequest($request);
        $source = $request->query('source') === 'imported' && $request->session()->has('gondal_reports_imported_summary')
            ? 'imported'
            : 'live';

        return view('gondal.reports', [
            'summary' => $summary,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'selectedStatus' => (string) $request->query('status', ''),
            'source' => $source,
        ]);
    }

    public function exportReports(Request $request): Response
    {
        $this->requireModulePermission($request, 'reports', 'overview', 'export');

        $summary = $this->reportSummaryForRequest($request);
        $rows = $this->reportRowsFromSummary($summary);

        return $this->streamCsvDownload('gondal-report.csv', ['metric', 'value'], $rows);
    }

    public function exportLogistics(Request $request): Response
    {
        $tab = $this->requireActionTab($request, 'logistics', 'trips');
        $this->requireModulePermission($request, 'logistics', $tab, 'export');

        if ($tab === 'riders') {
            $rows = LogisticsRider::query()
                ->withCount('trips')
                ->orderBy('name')
                ->get()
                ->map(fn (LogisticsRider $rider) => [
                    $rider->code,
                    $rider->name,
                    $rider->phone,
                    $rider->bank_name,
                    $rider->account_number,
                    $rider->account_name,
                    $rider->bike_make,
                    $rider->bike_model,
                    $rider->bike_plate_number,
                    $rider->identification_type,
                    $rider->identification_number,
                    $rider->photo_path,
                    $rider->identification_document_path,
                    $rider->status,
                    (string) $rider->trips_count,
                ])->all();

            return $this->streamCsvDownload(
                'gondal-logistics-riders.csv',
                ['code', 'name', 'phone', 'bank_name', 'account_number', 'account_name', 'bike_make', 'bike_model', 'bike_plate_number', 'identification_type', 'identification_number', 'photo_path', 'identification_document_path', 'status', 'trips_count'],
                $rows,
            );
        }

        $tripQuery = LogisticsTrip::query()
            ->with(['rider', 'cooperative'])
            ->orderByDesc('trip_date');

        if ($request->filled('rider_id')) {
            $tripQuery->where('rider_id', $request->query('rider_id'));
        }

        $rows = $tripQuery
            ->get()
            ->map(fn (LogisticsTrip $trip) => [
                optional($trip->trip_date)->toDateString(),
                (string) $trip->cooperative_id,
                $trip->cooperative?->code,
                $trip->cooperative?->name,
                (string) $trip->rider_id,
                $trip->rider?->code,
                $trip->rider?->name,
                $trip->vehicle_name,
                $trip->departure_time,
                $trip->arrival_time,
                (string) $trip->volume_liters,
                (string) $trip->distance_km,
                (string) $trip->fuel_cost,
                $trip->status,
            ])->all();

        $filename = $request->filled('rider_id')
            ? 'gondal-rider-trip-history.csv'
            : 'gondal-logistics-trips.csv';

        return $this->streamCsvDownload(
            $filename,
            ['trip_date', 'cooperative_id', 'cooperative_code', 'cooperative_name', 'rider_id', 'rider_code', 'rider_name', 'vehicle_name', 'departure_time', 'arrival_time', 'volume_liters', 'distance_km', 'fuel_cost', 'status'],
            $rows,
        );
    }

    public function importLogistics(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'logistics', 'trips', true);
        $this->requireModulePermission($request, 'logistics', $tab, 'import');

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = $tab === 'riders'
                ? $this->importLogisticsRidersRows($rows)
                : $this->importLogisticsTripsRows($rows);

            $this->writeAuditLog($request, 'logistics', 'imported', ['tab' => $tab, 'rows' => $count]);

            return redirect()->route('gondal.logistics', ['tab' => $tab])
                ->with('success', __('Imported :count logistics row(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.logistics', ['tab' => $tab], $exception);
        }
    }

    public function exportOperations(Request $request): Response
    {
        $tab = $this->requireActionTab($request, 'operations', 'costs');
        $this->requireModulePermission($request, 'operations', $tab, 'export');

        $query = OperationCost::query()->with('cooperative');

        if ($from = $request->query('from')) {
            $query->whereDate('cost_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('cost_date', '<=', $to);
        }
        if ($category = (string) $request->query('category', '')) {
            $query->where('category', $category);
        }

        $allCosts = $query->orderByDesc('cost_date')->get();

        if ($tab === 'summary') {
            $rows = $allCosts->groupBy(fn (OperationCost $cost) => Carbon::parse($cost->cost_date)->startOfWeek()->toDateString())
                ->map(function (Collection $group, string $weekStart) {
                    return [
                        Carbon::parse($weekStart)->toDateString(),
                        $group->count(),
                        (string) round((float) $group->sum('amount'), 2),
                        (string) round((float) $group->avg('amount'), 2),
                        (string) $group->groupBy('category')->sortByDesc(fn (Collection $items) => $items->sum('amount'))->keys()->first(),
                    ];
                })->values()->all();

            return $this->streamCsvDownload(
                'gondal-operations-summary.csv',
                ['week_start', 'entries', 'total_amount', 'average_amount', 'top_category'],
                $rows,
            );
        }

        if ($tab === 'ranking') {
            $rows = $allCosts->groupBy(fn (OperationCost $cost) => $cost->cooperative?->name ?: 'Unassigned')
                ->map(fn (Collection $group, string $name) => [
                    $name,
                    $group->count(),
                    (string) round((float) $group->sum('amount'), 2),
                    (string) round((float) $group->avg('amount'), 2),
                ])->values()->all();

            return $this->streamCsvDownload(
                'gondal-operations-ranking.csv',
                ['center_name', 'entries', 'total_amount', 'average_amount'],
                $rows,
            );
        }

        $rows = $allCosts->map(fn (OperationCost $cost) => [
            optional($cost->cost_date)->toDateString(),
            (string) $cost->cooperative_id,
            $cost->cooperative?->code,
            $cost->cooperative?->name,
            $cost->category,
            (string) $cost->amount,
            $cost->description,
            $cost->status,
        ])->all();

        return $this->streamCsvDownload(
            'gondal-operations-costs.csv',
            ['cost_date', 'cooperative_id', 'cooperative_code', 'cooperative_name', 'category', 'amount', 'description', 'status'],
            $rows,
        );
    }

    public function importOperations(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'operations', 'costs', true);
        $this->requireModulePermission($request, 'operations', $tab, 'import');

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = $this->importOperationRows($rows);
            $this->writeAuditLog($request, 'operations', 'imported', ['rows' => $count]);

            return redirect()->route('gondal.operations', ['tab' => $tab])
                ->with('success', __('Imported :count operation row(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.operations', ['tab' => $tab], $exception);
        }
    }

    public function exportRequisitions(Request $request): Response
    {
        $this->requireModulePermission($request, 'requisitions', 'requests', 'export');

        $tab = in_array((string) $request->query('tab', 'all'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->query('tab', 'all')
            : 'all';

        $query = Requisition::query()->with(['requester', 'cooperative', 'items'])->latest();

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $rows = [];

        foreach ($query->get() as $requisition) {
            $items = $requisition->items->isNotEmpty()
                ? $requisition->items
                : collect([(object) ['item_name' => null, 'quantity' => null, 'unit' => null, 'unit_cost' => null]]);

            foreach ($items as $item) {
                $rows[] = [
                    $requisition->reference,
                    (string) $requisition->requester_id,
                    $requisition->requester?->email,
                    (string) $requisition->cooperative_id,
                    $requisition->cooperative?->code,
                    $requisition->cooperative?->name,
                    $requisition->title,
                    $requisition->description,
                    $requisition->priority,
                    $requisition->status,
                    (string) $requisition->total_amount,
                    $item->item_name,
                    $item->quantity,
                    $item->unit,
                    $item->unit_cost,
                    optional($requisition->submitted_at)->toDateTimeString(),
                ];
            }
        }

        return $this->streamCsvDownload(
            'gondal-requisitions.csv',
            ['reference', 'requester_id', 'requester_email', 'cooperative_id', 'cooperative_code', 'cooperative_name', 'title', 'description', 'priority', 'status', 'total_amount', 'item_name', 'item_quantity', 'item_unit', 'item_cost', 'submitted_at'],
            $rows,
        );
    }

    public function importRequisitions(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'requisitions', 'requests', 'import');

        $tab = in_array((string) $request->input('tab', 'all'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->input('tab', 'all')
            : 'all';

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = $this->importRequisitionRows($request, $rows);
            $this->writeAuditLog($request, 'requisitions', 'imported', ['rows' => $count]);

            return redirect()->route('gondal.requisitions', ['tab' => $tab])
                ->with('success', __('Imported :count requisition row(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.requisitions', ['tab' => $tab], $exception);
        }
    }

    public function accounting(Request $request): Response
    {
        $tab = $request->query('tab', 'accounts');

        $accounts = $tab === 'accounts' ? \App\Models\Gondal\FinanceAccount::orderBy('code')->get() : collect();
        
        $entriesQuery = \App\Models\Gondal\JournalEntry::with(['lines.account', 'lines.farmer']);
        if ($request->filled('from')) {
            $entriesQuery->whereDate('entry_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $entriesQuery->whereDate('entry_date', '<=', $request->to);
        }
        $entries = $tab === 'entries' ? $entriesQuery->orderByDesc('entry_date')->orderByDesc('id')->paginate() : null;

        $linesQuery = \App\Models\Gondal\JournalLine::with(['entry', 'account', 'farmer.communityRecord']);
        if ($request->filled('from')) {
            $linesQuery->whereHas('entry', fn($q) => $q->whereDate('entry_date', '>=', $request->from));
        }
        if ($request->filled('to')) {
            $linesQuery->whereHas('entry', fn($q) => $q->whereDate('entry_date', '<=', $request->to));
        }
        if ($request->filled('account_id')) {
            $linesQuery->where('finance_account_id', $request->account_id);
        }
        $lines = $tab === 'ledger' ? $linesQuery->orderByDesc('id')->paginate(50) : null;

        return response()->view('gondal.accounting.index', compact('tab', 'accounts', 'entries', 'lines'));
    }

    public function exportPayments(Request $request): Response
    {
        $tab = $this->requireActionTab($request, 'payments', 'overview');
        $this->requireModulePermission($request, 'payments', $tab, 'export');

        if ($tab === 'reconciliation') {
            $rows = InventoryCredit::query()
                ->with('item')
                ->orderByDesc('credit_date')
                ->get()
                ->map(fn (InventoryCredit $credit) => [
                    optional($credit->credit_date)->toDateString(),
                    (string) $credit->inventory_item_id,
                    $credit->item?->sku,
                    $credit->item?->name,
                    $credit->customer_name,
                    (string) $credit->amount,
                    $credit->status,
                ])->all();

            return $this->streamCsvDownload(
                'gondal-payments-reconciliation.csv',
                ['credit_date', 'inventory_item_id', 'item_sku', 'item_name', 'customer_name', 'amount', 'status'],
                $rows,
            );
        }

        $rows = PaymentBatch::query()
            ->orderByDesc('period_end')
            ->get()
            ->map(fn (PaymentBatch $batch) => [
                $batch->name,
                $batch->payee_type,
                optional($batch->period_start)->toDateString(),
                optional($batch->period_end)->toDateString(),
                (string) $batch->total_amount,
                $batch->status,
            ])->all();

        return $this->streamCsvDownload(
            'gondal-payment-batches.csv',
            ['name', 'payee_type', 'period_start', 'period_end', 'total_amount', 'status'],
            $rows,
        );
    }

    public function importPayments(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'payments', 'overview', true);
        $this->requireModulePermission($request, 'payments', $tab, 'import');

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = $tab === 'reconciliation'
                ? $this->importPaymentReconciliationRows($rows)
                : $this->importPaymentBatchRows($rows);

            $this->writeAuditLog($request, 'payments', 'imported', ['tab' => $tab, 'rows' => $count]);

            return redirect()->route('gondal.payments', ['tab' => $tab])
                ->with('success', __('Imported :count payment row(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.payments', ['tab' => $tab], $exception);
        }
    }

    public function exportInventory(Request $request): Response
    {
        $tab = $this->requireActionTab($request, 'inventory', 'sales');
        $this->requireModulePermission($request, 'inventory', $tab, 'export');

        if ($tab === 'credit') {
            $rows = InventoryCredit::query()
                ->with('item')
                ->orderByDesc('credit_date')
                ->get()
                ->map(fn (InventoryCredit $credit) => [
                    optional($credit->credit_date)->toDateString(),
                    (string) $credit->inventory_item_id,
                    $credit->item?->sku,
                    $credit->item?->name,
                    $credit->customer_name,
                    (string) $credit->amount,
                    $credit->status,
                ])->all();

            return $this->streamCsvDownload(
                'gondal-inventory-credits.csv',
                ['credit_date', 'inventory_item_id', 'item_sku', 'item_name', 'customer_name', 'amount', 'status'],
                $rows,
            );
        }

        if ($tab === 'stock') {
            $rows = InventoryItem::query()
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryItem $item) => [
                    $item->sku,
                    $item->name,
                    (string) $item->stock_qty,
                    (string) $item->unit_price,
                    $item->status,
                ])->all();

            return $this->streamCsvDownload(
                'gondal-inventory-stock.csv',
                ['sku', 'name', 'stock_qty', 'unit_price', 'status'],
                $rows,
            );
        }

        $rows = InventorySale::query()
            ->with('item')
            ->orderByDesc('sold_on')
            ->get()
            ->map(fn (InventorySale $sale) => [
                optional($sale->sold_on)->toDateString(),
                (string) $sale->inventory_item_id,
                $sale->item?->sku,
                $sale->item?->name,
                $sale->customer_name,
                (string) $sale->quantity,
                (string) $sale->unit_price,
            ])->all();

        return $this->streamCsvDownload(
            'gondal-inventory-sales.csv',
            ['sold_on', 'inventory_item_id', 'item_sku', 'item_name', 'customer_name', 'quantity', 'unit_price'],
            $rows,
        );
    }

    public function importInventory(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'inventory', 'sales', true);
        $this->requireModulePermission($request, 'inventory', $tab, 'import');

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = match ($tab) {
                'credit' => $this->importInventoryCreditRows($rows),
                'stock' => $this->importInventoryItemRows($rows),
                default => $this->importInventorySaleRows($rows),
            };

            $this->writeAuditLog($request, 'inventory', 'imported', ['tab' => $tab, 'rows' => $count]);

            return redirect()->route('gondal.inventory', ['tab' => $tab])
                ->with('success', __('Imported :count inventory row(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.inventory', ['tab' => $tab], $exception);
        }
    }

    public function exportExtension(Request $request): Response
    {
        $tab = $this->requireActionTab($request, 'extension', 'agents');
        $this->requireModulePermission($request, 'extension', $tab, 'export');

        $visits = ExtensionVisit::query()->with('farmer')->orderByDesc('visit_date')->get();
        $trainings = ExtensionTraining::query()->orderByDesc('training_date')->get();

        if ($tab === 'training') {
            $rows = $trainings->map(fn (ExtensionTraining $training) => [
                optional($training->training_date)->toDateString(),
                $training->title,
                $training->location,
                (string) $training->attendees,
            ])->all();

            return $this->streamCsvDownload(
                'gondal-extension-training.csv',
                ['training_date', 'title', 'location', 'attendees'],
                $rows,
            );
        }

        if ($tab === 'agents') {
            $rows = $visits->groupBy('officer_name')->map(fn (Collection $group, string $officer) => [
                $officer,
                $group->count(),
                $group->pluck('farmer_id')->unique()->count(),
                (string) round((float) $group->avg('performance_score'), 1),
                optional($group->sortByDesc('visit_date')->first()?->visit_date)->toDateString(),
            ])->values()->all();

            return $this->streamCsvDownload(
                'gondal-extension-agents.csv',
                ['officer_name', 'visits', 'farmers', 'average_score', 'latest_visit'],
                $rows,
            );
        }

        if ($tab === 'performance') {
            $rows = $visits->groupBy('farmer_id')->map(function (Collection $group) {
                $first = $group->first();

                return [
                    (string) $first?->farmer_id,
                    $first?->farmer?->name,
                    $group->count(),
                    (string) round((float) $group->avg('performance_score'), 1),
                    $group->sortByDesc('visit_date')->first()?->topic,
                ];
            })->values()->all();

            return $this->streamCsvDownload(
                'gondal-extension-performance.csv',
                ['farmer_id', 'farmer_name', 'visits', 'average_score', 'last_topic'],
                $rows,
            );
        }

        $rows = $visits->map(fn (ExtensionVisit $visit) => [
            optional($visit->visit_date)->toDateString(),
            (string) $visit->farmer_id,
            $visit->farmer ? $this->farmerCode($visit->farmer) : null,
            $visit->farmer?->name,
            $visit->officer_name,
            $visit->topic,
            (string) $visit->performance_score,
        ])->all();

        return $this->streamCsvDownload(
            'gondal-extension-visits.csv',
            ['visit_date', 'farmer_id', 'farmer_code', 'farmer_name', 'officer_name', 'topic', 'performance_score'],
            $rows,
        );
    }

    public function importExtension(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'extension', 'agents', true);
        $this->requireModulePermission($request, 'extension', $tab, 'import');

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $count = $tab === 'training'
                ? $this->importExtensionTrainingRows($rows)
                : $this->importExtensionVisitRows($rows);

            $this->writeAuditLog($request, 'extension', 'imported', ['tab' => $tab, 'rows' => $count]);

            return redirect()->route('gondal.extension', ['tab' => $tab])
                ->with('success', __('Imported :count extension row(s).', ['count' => $count]));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.extension', ['tab' => $tab], $exception);
        }
    }

    public function importReports(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'reports', 'overview', 'import');

        try {
            $rows = $this->parseCsvUpload($this->validateCsvUpload($request));
            $summary = $this->importReportSummaryRows($rows);

            $request->session()->put('gondal_reports_imported_summary', $summary);
            $this->writeAuditLog($request, 'reports', 'imported', ['rows' => count($rows)]);

            return redirect()->route('gondal.reports', ['source' => 'imported'])
                ->with('success', __('Report snapshot imported successfully.'));
        } catch (ValidationException $exception) {
            return $this->handleImportFailure('gondal.reports', [], $exception);
        }
    }

    public function adminAuditLog()
    {
        $this->requireAdmin();

        return view('gondal.admin-audit-log', [
            'logs' => AuditLog::query()->with('user')->latest()->take(100)->get(),
        ]);
    }

    public function adminApprovalRules()
    {
        $this->requireAdmin();

        return view('gondal.admin-approval-rules', [
            'rules' => ApprovalRule::query()->orderBy('min_amount')->get(),
        ]);
    }

    public function storeApprovalRule(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $payload = $this->validateRulePayload($request);

        if ((bool) $payload['is_active'] && $this->hasOverlappingRule((float) $payload['min_amount'], (float) $payload['max_amount'])) {
            return back()->with('error', __('Approval range overlaps with an existing active rule.'))->withInput();
        }

        $rule = ApprovalRule::query()->create($payload);
        $this->writeAuditLog($request, 'approval_rules', 'created', ['rule_id' => $rule->id]);

        return redirect()->route('gondal.admin.approval-rules')->with('success', __('Approval rule created.'));
    }

    public function updateApprovalRule(Request $request, string $id): RedirectResponse
    {
        $this->requireAdmin();

        $payload = $this->validateRulePayload($request);
        $rule = ApprovalRule::query()->findOrFail($id);

        if ((bool) $payload['is_active'] && $this->hasOverlappingRule((float) $payload['min_amount'], (float) $payload['max_amount'], $rule->id)) {
            return back()->with('error', __('Approval range overlaps with an existing active rule.'))->withInput();
        }

        $rule->update($payload);
        $this->writeAuditLog($request, 'approval_rules', 'updated', ['rule_id' => $rule->id]);

        return redirect()->route('gondal.admin.approval-rules')->with('success', __('Approval rule updated.'));
    }

    protected function validateFarmerPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:male,female,other'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'mcc' => ['required', 'string', 'max:255'],
            'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
            'registration_date' => ['nullable', 'date'],
            'state' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'lga' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'ward' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'community' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'target_liters' => ['nullable', 'numeric', 'min:0'],
            'profile_photo' => [$isUpdate ? 'nullable' : 'nullable', 'image', 'max:3072'],
        ];

        return $request->validate($rules);
    }

    protected function validateCooperativePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mcc' => ['required', 'string', 'max:255'],
            'leader_name' => ['required', 'string', 'max:255'],
            'leader_phone' => ['required', 'string', 'max:50'],
            'site_location' => ['required', 'string', 'max:255'],
            'formation_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    protected function validateMilkCollectionPayload(Request $request): array
    {
        return $request->validate([
            'collection_date' => ['required', 'date'],
            'farmer_id' => ['required', 'integer', 'exists:venders,id'],
            'liters' => ['required', 'numeric', 'min:0.1'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function validateLogisticsTripPayload(Request $request): array
    {
        return $request->validate([
            'trip_date' => ['required', 'date'],
            'cooperative_id' => ['required', 'exists:cooperatives,id'],
            'rider_id' => ['required', 'exists:gondal_logistics_riders,id'],
            'vehicle_name' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'string', 'max:20'],
            'arrival_time' => ['required', 'string', 'max:20'],
            'volume_liters' => ['required', 'numeric', 'min:0.1'],
            'distance_km' => ['required', 'numeric', 'min:0'],
            'fuel_cost' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:scheduled,in_transit,completed,approved,cancelled'],
        ]);
    }

    protected function validateOperationCostPayload(Request $request): array
    {
        return $request->validate([
            'cost_date' => ['required', 'date'],
            'cooperative_id' => ['required', 'exists:cooperatives,id'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:pending,approved,paid'],
        ]);
    }

    protected function validateInventorySalePayload(Request $request): array
    {
        return $request->validate([
            'sold_on' => ['required', 'date'],
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    protected function validateExtensionVisitPayload(Request $request): array
    {
        return $request->validate([
            'visit_date' => ['required', 'date'],
            'farmer_id' => ['required', 'exists:venders,id'],
            'agent_profile_id' => ['nullable', 'exists:gondal_agent_profiles,id'],
            'officer_name' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:255'],
            'performance_score' => ['required', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string'],
            'record_sale' => ['nullable', 'boolean'],
            'inventory_item_id' => ['required_if:record_sale,1', 'nullable', 'exists:gondal_inventory_items,id'],
            'quantity' => ['required_if:record_sale,1', 'nullable', 'numeric', 'min:0.01'],
            'unit_price' => ['required_if:record_sale,1', 'nullable', 'numeric', 'min:0'],
            'payment_method' => ['required_if:record_sale,1', 'nullable', 'in:Cash,Credit,Transfer,Milk Collection Balance'],
            'due_date' => ['nullable', 'date', 'after_or_equal:visit_date'],
        ]);
    }

    protected function validateExtensionTrainingPayload(Request $request): array
    {
        return $request->validate([
            'training_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'attendees' => ['required', 'integer', 'min:1'],
        ]);
    }

    protected function validateRulePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'approver_role' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    protected function hasOverlappingRule(float $min, float $max, ?int $ignoreId = null): bool
    {
        return ApprovalRule::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('is_active', true)
            ->where(function ($query) use ($min, $max) {
                $query->whereBetween('min_amount', [$min, $max])
                    ->orWhereBetween('max_amount', [$min, $max])
                    ->orWhere(function ($nested) use ($min, $max) {
                        $nested->where('min_amount', '<=', $min)->where('max_amount', '>=', $max);
                    });
            })
            ->exists();
    }

    protected function resolveFarmerCooperative(int $cooperativeId, string $mcc): Cooperative
    {
        $cooperative = Cooperative::query()->findOrFail($cooperativeId);

        if ($cooperative->location !== $mcc) {
            throw ValidationException::withMessages([
                'cooperative_id' => [__('Select a cooperative that matches the selected MCC.')],
            ]);
        }

        return $cooperative;
    }

    protected function resolveCooperative(string $identifier): Cooperative
    {
        return Cooperative::query()
            ->where(function ($query) use ($identifier) {
                if (ctype_digit($identifier)) {
                    $query->where('id', (int) $identifier);
                    $query->orWhere('code', strtoupper($identifier));
                } else {
                    $query->where('code', strtoupper($identifier));
                }
            })
            ->firstOrFail();
    }

    protected function resolveRequisition(string $identifier): Requisition
    {
        return Requisition::query()
            ->with(['requester', 'cooperative', 'items', 'events.actor'])
            ->where(function ($query) use ($identifier) {
                if (ctype_digit($identifier)) {
                    $query->where('id', (int) $identifier);
                    $query->orWhere('reference', strtoupper($identifier));
                } else {
                    $query->where('reference', strtoupper($identifier));
                }
            })
            ->firstOrFail();
    }

    protected function collectionsForCooperative(Collection $collections, Cooperative $cooperative): Collection
    {
        return $collections->filter(function (MilkCollection $collection) use ($cooperative) {
            if ((int) ($collection->cooperative_id ?? 0) === (int) $cooperative->id) {
                return true;
            }

            if ((int) ($collection->farmer?->cooperative_id ?? 0) === (int) $cooperative->id) {
                return true;
            }

            return $cooperative->location !== null && $collection->mcc_id === $cooperative->location;
        });
    }

    protected function farmerLocationHierarchy(): array
    {
        return [
            'Adamawa' => [
                'Yola North' => ['Ajiya', 'Jambutu', 'Doubeli'],
                'Yola South' => ['Bole', 'Mbamba', 'Namtari'],
                'Mubi North' => ['Sabon Layi', 'Yelwa', 'Lokuwa'],
                'Mayo-Belwa' => ['Bajama', 'Gangfada', 'Ribadu'],
            ],
        ];
    }

    protected function communityLocationHierarchy(): array
    {
        return Community::query()
            ->orderBy('state')
            ->orderBy('lga')
            ->orderBy('name')
            ->get(['state', 'lga', 'name'])
            ->groupBy(fn (Community $community) => (string) $community->state)
            ->map(function (Collection $stateRows) {
                return $stateRows
                    ->groupBy(fn (Community $community) => (string) $community->lga)
                    ->map(fn (Collection $lgaRows) => $lgaRows->pluck('name')->filter()->values()->all())
                    ->filter(fn (array $communities) => $communities !== [])
                    ->toArray();
            })
            ->filter(fn (array $lgas) => $lgas !== [])
            ->toArray();
    }

    protected function communityLocationHierarchyWithIds(): array
    {
        return Community::query()
            ->orderBy('state')
            ->orderBy('lga')
            ->orderBy('name')
            ->get(['id', 'state', 'lga', 'name'])
            ->groupBy(fn (Community $community) => (string) $community->state)
            ->map(function (Collection $stateRows) {
                return $stateRows
                    ->groupBy(fn (Community $community) => (string) $community->lga)
                    ->map(function (Collection $lgaRows) {
                        return $lgaRows
                            ->map(fn (Community $community) => [
                                'id' => $community->id,
                                'name' => $community->name,
                            ])
                            ->filter(fn (array $community) => ! empty($community['name']))
                            ->values()
                            ->all();
                    })
                    ->filter(fn (array $communities) => $communities !== [])
                    ->toArray();
            })
            ->filter(fn (array $lgas) => $lgas !== [])
            ->toArray();
    }

    protected function farmerCode(Vender $farmer): string
    {
        return 'FARM-'.str_pad((string) $farmer->vender_id, 3, '0', STR_PAD_LEFT);
    }

    protected function nextFarmerNumber(): int
    {
        $latest = Vender::query()->latest('vender_id')->first();

        return $latest ? ((int) $latest->vender_id + 1) : 1;
    }

    protected function nextCooperativeCode(string $mcc): string
    {
        $slug = strtoupper((string) Str::of($mcc)->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-'));
        $base = 'COOP-'.($slug !== '' ? $slug : strtoupper(Str::random(5)));
        $candidate = $base;
        $suffix = 2;

        while (Cooperative::query()->where('code', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function requisitionItemsFromPayload(array $payload): array
    {
        $names = $payload['item_name'] ?? [];
        $quantities = $payload['item_quantity'] ?? [];
        $units = $payload['item_unit'] ?? [];
        $costs = $payload['item_cost'] ?? [];
        $items = [];

        foreach ($names as $index => $name) {
            if (! filled($name)) {
                continue;
            }

            $items[] = [
                'item_name' => (string) $name,
                'quantity' => (float) ($quantities[$index] ?? 1),
                'unit' => (string) ($units[$index] ?? 'unit'),
                'unit_cost' => (float) ($costs[$index] ?? 0),
            ];
        }

        return $items === [] ? [['item_name' => 'General Item', 'quantity' => 1, 'unit' => 'unit', 'unit_cost' => (float) ($payload['total_amount'] ?? 0)]] : $items;
    }

    protected function storeFarmerPhoto(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $filename = 'farmer_'.Str::uuid().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('uploads/farmer_profiles', $filename);
    }

    protected function storeRiderAsset(?UploadedFile $file, string $prefix): ?string
    {
        if (! $file) {
            return null;
        }

        $filename = $prefix.'_'.Str::uuid().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('uploads/gondal/riders', $filename, 'public');
    }

    protected function deleteFarmerPhoto(?string $path): void
    {
        if ($path && Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    protected function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return asset(Storage::url($path));
    }

    protected function writeAuditLog(Request $request, string $module, string $action, array $context = []): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'module' => $module,
            'action' => $action,
            'context' => $context,
        ]);
    }

    protected function validateCsvUpload(Request $request): UploadedFile
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        return $validated['import_file'];
    }

    protected function parseCsvUpload(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'import_file' => [__('Unable to read the uploaded CSV file.')],
            ]);
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'import_file' => [__('The uploaded CSV file is empty.')],
            ]);
        }

        $normalizedHeaders = array_map(fn ($value) => $this->normalizeCsvHeader((string) $value), $header);
        $rows = [];
        $line = 1;

        while (($rawRow = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->csvRowIsBlank($rawRow)) {
                continue;
            }

            $row = ['__line' => $line];

            foreach ($normalizedHeaders as $index => $headerName) {
                if ($headerName === '') {
                    continue;
                }

                $row[$headerName] = isset($rawRow[$index]) ? trim((string) $rawRow[$index]) : null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'import_file' => [__('The uploaded CSV file does not contain any data rows.')],
            ]);
        }

        return $rows;
    }

    protected function normalizeCsvHeader(string $value): string
    {
        return (string) Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    protected function csvRowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function csvValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function csvRequiredValue(array $row, array $keys, string $label): string
    {
        $value = $this->csvValue($row, $keys);

        if ($value === null) {
            throw $this->csvRowException($row, __('Missing :field value.', ['field' => $label]));
        }

        return $value;
    }

    protected function csvRowException(array $row, string $message): ValidationException
    {
        $line = $row['__line'] ?? null;
        $prefix = $line ? __('Row :line: ', ['line' => $line]) : '';

        return ValidationException::withMessages([
            'import_file' => [$prefix.$message],
        ]);
    }

    protected function handleImportFailure(string $route, array $parameters, ValidationException $exception): RedirectResponse
    {
        $message = collect($exception->errors())->flatten()->first() ?: __('CSV import failed.');

        return redirect()->route($route, $parameters)
            ->with('error', $message)
            ->withErrors($exception->errors(), 'import');
    }

    protected function streamCsvDownload(string $filename, array $headers, array $rows): Response
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function resolveCooperativeIdFromCsvRow(array $row, bool $required = true): ?int
    {
        $value = $this->csvValue($row, [
            'cooperative_id',
            'cooperative_code',
            'cooperative_name',
            'cooperative',
            'milk_collection_center_id',
            'milk_collection_center_code',
            'milk_collection_center_name',
            'milk_collection_center',
            'mcc',
        ]);

        if ($value === null) {
            if ($required) {
                throw $this->csvRowException($row, __('Missing cooperative reference.'));
            }

            return null;
        }

        $cooperative = null;

        if (ctype_digit($value)) {
            $cooperative = Cooperative::query()->find((int) $value);
        }

        if (! $cooperative) {
            $cooperative = Cooperative::query()
                ->where('code', strtoupper($value))
                ->orWhere('name', $value)
                ->orWhere('location', $value)
                ->first();
        }

        if (! $cooperative) {
            throw $this->csvRowException($row, __('Unable to match cooperative ":value".', ['value' => $value]));
        }

        return (int) $cooperative->id;
    }

    protected function resolveRiderIdFromCsvRow(array $row): int
    {
        $value = $this->csvRequiredValue($row, ['rider_id', 'rider_code', 'rider_name', 'rider'], 'rider');
        $rider = null;

        if (ctype_digit($value)) {
            $rider = LogisticsRider::query()->find((int) $value);
        }

        if (! $rider) {
            $rider = LogisticsRider::query()
                ->where('code', strtoupper($value))
                ->orWhere('name', $value)
                ->first();
        }

        if (! $rider) {
            throw $this->csvRowException($row, __('Unable to match rider ":value".', ['value' => $value]));
        }

        return (int) $rider->id;
    }

    protected function resolveInventoryItemIdFromCsvRow(array $row): int
    {
        $value = $this->csvRequiredValue($row, ['inventory_item_id', 'item_id', 'item_sku', 'sku', 'item_name', 'item'], 'inventory item');
        $item = null;

        if (ctype_digit($value)) {
            $item = InventoryItem::query()->find((int) $value);
        }

        if (! $item) {
            $item = InventoryItem::query()
                ->where('sku', strtoupper($value))
                ->orWhere('name', $value)
                ->first();
        }

        if (! $item) {
            throw $this->csvRowException($row, __('Unable to match inventory item ":value".', ['value' => $value]));
        }

        return (int) $item->id;
    }

    protected function resolveFarmerIdFromCsvRow(array $row): int
    {
        $value = $this->csvRequiredValue($row, ['farmer_id', 'farmer_code', 'farmer_name', 'farmer', 'farmer_email'], 'farmer');
        $farmer = null;

        if (ctype_digit($value)) {
            $farmer = Vender::query()->find((int) $value);
        }

        if (! $farmer && str_starts_with(strtoupper($value), 'FARM-')) {
            $numericCode = preg_replace('/\D+/', '', $value);

            if ($numericCode !== '') {
                $farmer = Vender::query()->where('vender_id', (int) $numericCode)->first();
            }
        }

        if (! $farmer) {
            $farmer = Vender::query()
                ->where('email', $value)
                ->orWhere('name', $value)
                ->first();
        }

        if (! $farmer) {
            throw $this->csvRowException($row, __('Unable to match farmer ":value".', ['value' => $value]));
        }

        return (int) $farmer->id;
    }

    protected function resolveCooperativeIdsFromCsvRow(array $row, bool $required = true): array
    {
        $values = $this->csvListValue($row, ['cooperative_ids', 'cooperative_id', 'cooperatives', 'cooperative_codes', 'cooperative_names']);

        if ($values === []) {
            if ($required) {
                throw $this->csvRowException($row, __('Missing cooperative reference.'));
            }

            return [];
        }

        return collect($values)->map(function (string $value) use ($row) {
            $cooperative = null;

            if (ctype_digit($value)) {
                $cooperative = Cooperative::query()->find((int) $value);
            }

            if (! $cooperative) {
                $cooperative = Cooperative::query()
                    ->where('code', strtoupper($value))
                    ->orWhere('name', $value)
                    ->first();
            }

            if (! $cooperative) {
                throw $this->csvRowException($row, __('Unable to match cooperative ":value".', ['value' => $value]));
            }

            return (int) $cooperative->id;
        })->unique()->values()->all();
    }

    protected function resolveRequesterFromCsvRow(array $row, User $fallback): User
    {
        $value = $this->csvValue($row, ['requester_id', 'requester_email']);

        if ($value === null) {
            return $fallback;
        }

        $requester = null;

        if (ctype_digit($value)) {
            $requester = User::query()->find((int) $value);
        }

        if (! $requester) {
            $requester = User::query()->where('email', $value)->first();
        }

        if (! $requester) {
            throw $this->csvRowException($row, __('Unable to match requester ":value".', ['value' => $value]));
        }

        return $requester;
    }

    protected function resolveUserIdFromCsvRow(array $row, array $keys, string $label): int
    {
        $value = $this->csvRequiredValue($row, $keys, $label);
        $user = null;

        if (ctype_digit($value)) {
            $user = User::query()->find((int) $value);
        }

        if (! $user) {
            $user = User::query()
                ->where('email', $value)
                ->orWhere('name', $value)
                ->first();
        }

        if (! $user) {
            throw $this->csvRowException($row, __('Unable to match :label ":value".', ['label' => $label, 'value' => $value]));
        }

        return (int) $user->id;
    }

    protected function resolveProjectIdFromCsvRow(array $row, array $keys, string $label = 'project'): int
    {
        $value = $this->csvRequiredValue($row, $keys, $label);
        $project = null;

        if (ctype_digit($value)) {
            $project = Project::query()->find((int) $value);
        }

        if (! $project) {
            $project = Project::query()
                ->where('project_name', $value)
                ->orWhereRaw('LOWER(project_name) = ?', [Str::lower((string) $value)])
                ->first();
        }

        if (! $project) {
            throw $this->csvRowException($row, __('Unable to match :label ":value".', ['label' => $label, 'value' => $value]));
        }

        return (int) $project->id;
    }

    protected function resolveOneStopShopIdFromCsvRow(array $row, array $keys, string $label = 'one-stop shop'): int
    {
        $value = $this->csvRequiredValue($row, $keys, $label);
        $shop = null;

        if (ctype_digit($value)) {
            $shop = OneStopShop::query()->find((int) $value);
        }

        if (! $shop) {
            $shop = OneStopShop::query()
                ->where('name', $value)
                ->orWhere('code', $value)
                ->orWhereRaw('LOWER(name) = ?', [Str::lower((string) $value)])
                ->first();
        }

        if (! $shop) {
            throw $this->csvRowException($row, __('Unable to match :label ":value".', ['label' => $label, 'value' => $value]));
        }

        return (int) $shop->id;
    }

    protected function csvListValue(array $row, array $keys, array $fallback = []): array
    {
        $value = $this->csvValue($row, $keys);

        if ($value === null) {
            return $fallback;
        }

        $parts = preg_split('/[\|\;\,]+/', $value) ?: [];

        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->values()
            ->all();
    }

    protected function nextRiderCode(): string
    {
        return 'RID-'.str_pad((string) ((int) LogisticsRider::query()->max('id') + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function nextInventorySku(): string
    {
        return 'SKU-'.str_pad((string) ((int) InventoryItem::query()->max('id') + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function nextRequisitionReference(): string
    {
        return 'REQ-'.str_pad((string) ((int) Requisition::query()->max('id') + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function uniqueRequisitionReference(?string $reference): string
    {
        if (! filled($reference)) {
            return $this->nextRequisitionReference();
        }

        $reference = strtoupper(trim((string) $reference));

        if (! Requisition::query()->where('reference', $reference)->exists()) {
            return $reference;
        }

        return $this->nextRequisitionReference();
    }

    protected function importLogisticsTripsRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'trip_date' => $this->csvRequiredValue($row, ['trip_date', 'date'], 'trip_date'),
                'cooperative_id' => $this->resolveCooperativeIdFromCsvRow($row),
                'rider_id' => $this->resolveRiderIdFromCsvRow($row),
                'vehicle_name' => $this->csvRequiredValue($row, ['vehicle_name', 'vehicle'], 'vehicle_name'),
                'departure_time' => $this->csvRequiredValue($row, ['departure_time', 'departure'], 'departure_time'),
                'arrival_time' => $this->csvRequiredValue($row, ['arrival_time', 'arrival'], 'arrival_time'),
                'volume_liters' => $this->csvRequiredValue($row, ['volume_liters', 'liters', 'volume'], 'volume_liters'),
                'distance_km' => $this->csvRequiredValue($row, ['distance_km', 'distance'], 'distance_km'),
                'fuel_cost' => $this->csvRequiredValue($row, ['fuel_cost', 'fuel'], 'fuel_cost'),
                'status' => Str::lower($this->csvRequiredValue($row, ['status'], 'status')),
            ], [
                'trip_date' => ['required', 'date'],
                'cooperative_id' => ['required', 'exists:cooperatives,id'],
                'rider_id' => ['required', 'exists:gondal_logistics_riders,id'],
                'vehicle_name' => ['required', 'string', 'max:255'],
                'departure_time' => ['required', 'string', 'max:20'],
                'arrival_time' => ['required', 'string', 'max:20'],
                'volume_liters' => ['required', 'numeric', 'min:0.1'],
                'distance_km' => ['required', 'numeric', 'min:0'],
                'fuel_cost' => ['required', 'numeric', 'min:0'],
                'status' => ['required', 'in:scheduled,in_transit,completed,approved,cancelled'],
            ])->validate();

            LogisticsTrip::query()->create($payload);
            $count++;
        }

        return $count;
    }

    protected function importLogisticsRidersRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'name' => $this->csvRequiredValue($row, ['name'], 'name'),
                'phone' => $this->csvValue($row, ['phone']),
                'bank_name' => $this->csvValue($row, ['bank_name']),
                'account_number' => $this->csvValue($row, ['account_number', 'bank_account_number']),
                'account_name' => $this->csvValue($row, ['account_name', 'bank_account_name']),
                'bike_make' => $this->csvValue($row, ['bike_make']),
                'bike_model' => $this->csvValue($row, ['bike_model']),
                'bike_plate_number' => $this->csvValue($row, ['bike_plate_number', 'plate_number']),
                'identification_type' => $this->csvValue($row, ['identification_type', 'id_type']),
                'identification_number' => $this->csvValue($row, ['identification_number', 'id_number']),
                'photo_path' => $this->csvValue($row, ['photo_path', 'image_path']),
                'identification_document_path' => $this->csvValue($row, ['identification_document_path', 'identification_path', 'id_document_path']),
                'status' => Str::lower($this->csvValue($row, ['status']) ?: 'active'),
            ], [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'account_number' => ['nullable', 'string', 'max:50'],
                'account_name' => ['nullable', 'string', 'max:255'],
                'bike_make' => ['nullable', 'string', 'max:255'],
                'bike_model' => ['nullable', 'string', 'max:255'],
                'bike_plate_number' => ['nullable', 'string', 'max:100'],
                'identification_type' => ['nullable', 'string', 'max:255'],
                'identification_number' => ['nullable', 'string', 'max:100'],
                'photo_path' => ['nullable', 'string', 'max:2048'],
                'identification_document_path' => ['nullable', 'string', 'max:2048'],
                'status' => ['required', 'in:active,inactive'],
            ])->validate();

            $code = strtoupper($this->csvValue($row, ['code']) ?: $this->nextRiderCode());

            if (LogisticsRider::query()->where('code', $code)->exists()) {
                $code = $this->nextRiderCode();
            }

            LogisticsRider::query()->create($payload + ['code' => $code]);
            $count++;
        }

        return $count;
    }

    protected function importOperationRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'cost_date' => $this->csvRequiredValue($row, ['cost_date', 'date'], 'cost_date'),
                'cooperative_id' => $this->resolveCooperativeIdFromCsvRow($row),
                'category' => $this->csvRequiredValue($row, ['category'], 'category'),
                'amount' => $this->csvRequiredValue($row, ['amount'], 'amount'),
                'description' => $this->csvValue($row, ['description']),
                'status' => Str::lower($this->csvValue($row, ['status']) ?: 'pending'),
            ], [
                'cost_date' => ['required', 'date'],
                'cooperative_id' => ['required', 'exists:cooperatives,id'],
                'category' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'description' => ['nullable', 'string', 'max:1000'],
                'status' => ['required', 'in:pending,approved,paid'],
            ])->validate();

            OperationCost::query()->create($payload);
            $count++;
        }

        return $count;
    }

    protected function importRequisitionRows(Request $request, array $rows): int
    {
        $groups = collect($rows)->groupBy(fn (array $row) => $this->csvValue($row, ['reference']) ?: 'row_'.$row['__line']);
        $count = 0;

        foreach ($groups as $groupRows) {
            $firstRow = $groupRows->first();
            $requester = $this->resolveRequesterFromCsvRow($firstRow, $request->user());
            $status = Str::lower($this->csvValue($firstRow, ['status']) ?: 'pending');

            if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
                throw $this->csvRowException($firstRow, __('Invalid requisition status ":value".', ['value' => $status]));
            }

            $payload = Validator::make([
                'title' => $this->csvRequiredValue($firstRow, ['title'], 'title'),
                'description' => $this->csvValue($firstRow, ['description']),
                'priority' => Str::lower($this->csvValue($firstRow, ['priority']) ?: 'medium'),
                'cooperative_id' => $this->resolveCooperativeIdFromCsvRow($firstRow, false),
                'total_amount' => $this->csvRequiredValue($firstRow, ['total_amount', 'amount'], 'total_amount'),
            ], [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
                'priority' => ['required', 'in:low,medium,high'],
                'cooperative_id' => ['nullable', 'integer', 'exists:cooperatives,id'],
                'total_amount' => ['required', 'numeric', 'min:1'],
            ])->validate();

            DB::transaction(function () use ($groupRows, $requester, $request, $payload, $firstRow, $status) {
                $requisition = Requisition::query()->create([
                    'reference' => $this->uniqueRequisitionReference($this->csvValue($firstRow, ['reference'])),
                    'requester_id' => $requester->id,
                    'cooperative_id' => $payload['cooperative_id'] ?? null,
                    'title' => $payload['title'],
                    'description' => $payload['description'] ?? null,
                    'total_amount' => (float) $payload['total_amount'],
                    'priority' => $payload['priority'],
                    'status' => $status,
                    'submitted_at' => now(),
                    'approved_at' => $status === 'approved' ? now() : null,
                    'rejected_at' => $status === 'rejected' ? now() : null,
                ]);

                $itemCount = 0;

                foreach ($groupRows as $row) {
                    $itemName = $this->csvValue($row, ['item_name']);

                    if (! filled($itemName)) {
                        continue;
                    }

                    $itemPayload = Validator::make([
                        'item_name' => $itemName,
                        'quantity' => $this->csvValue($row, ['item_quantity', 'quantity']) ?: 1,
                        'unit' => $this->csvValue($row, ['item_unit', 'unit']) ?: 'unit',
                        'unit_cost' => $this->csvValue($row, ['item_cost', 'unit_cost']) ?: 0,
                    ], [
                        'item_name' => ['required', 'string', 'max:255'],
                        'quantity' => ['required', 'numeric', 'min:0.01'],
                        'unit' => ['required', 'string', 'max:50'],
                        'unit_cost' => ['required', 'numeric', 'min:0'],
                    ])->validate();

                    RequisitionItem::query()->create([
                        'requisition_id' => $requisition->id,
                        'item_name' => $itemPayload['item_name'],
                        'quantity' => $itemPayload['quantity'],
                        'unit' => $itemPayload['unit'],
                        'unit_cost' => $itemPayload['unit_cost'],
                    ]);

                    $itemCount++;
                }

                if ($itemCount === 0) {
                    RequisitionItem::query()->create([
                        'requisition_id' => $requisition->id,
                        'item_name' => 'General Item',
                        'quantity' => 1,
                        'unit' => 'unit',
                        'unit_cost' => (float) $payload['total_amount'],
                    ]);
                }

                RequisitionEvent::query()->create([
                    'requisition_id' => $requisition->id,
                    'actor_id' => $requester->id,
                    'action' => 'submitted',
                    'notes' => 'Imported from CSV',
                ]);

                if ($status === 'approved' || $status === 'rejected') {
                    RequisitionEvent::query()->create([
                        'requisition_id' => $requisition->id,
                        'actor_id' => $request->user()->id,
                        'action' => $status,
                        'notes' => $status === 'approved' ? 'Imported as approved' : ($this->csvValue($firstRow, ['notes', 'rejection_notes']) ?: 'Imported as rejected'),
                    ]);
                }
            });

            $count++;
        }

        return $count;
    }

    protected function importPaymentBatchRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'name' => $this->csvRequiredValue($row, ['name', 'batch_name'], 'name'),
                'payee_type' => Str::lower($this->csvRequiredValue($row, ['payee_type'], 'payee_type')),
                'period_start' => $this->csvRequiredValue($row, ['period_start', 'start'], 'period_start'),
                'period_end' => $this->csvRequiredValue($row, ['period_end', 'end'], 'period_end'),
                'status' => Str::lower($this->csvValue($row, ['status']) ?: 'draft'),
                'total_amount' => $this->csvRequiredValue($row, ['total_amount', 'amount'], 'total_amount'),
            ], [
                'name' => ['required', 'string', 'max:255'],
                'payee_type' => ['required', 'in:farmer,rider,staff'],
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
                'status' => ['required', 'in:draft,processing,approved,completed'],
                'total_amount' => ['required', 'numeric', 'min:0'],
            ])->validate();

            PaymentBatch::query()->create($payload);
            $count++;
        }

        return $count;
    }

    protected function importPaymentReconciliationRows(array $rows): int
    {
        return $this->importInventoryCreditRows($rows);
    }

    protected function importInventorySaleRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'sold_on' => $this->csvRequiredValue($row, ['sold_on', 'date'], 'sold_on'),
                'inventory_item_id' => $this->resolveInventoryItemIdFromCsvRow($row),
                'customer_name' => $this->csvRequiredValue($row, ['customer_name', 'customer'], 'customer_name'),
                'quantity' => $this->csvRequiredValue($row, ['quantity'], 'quantity'),
                'unit_price' => $this->csvRequiredValue($row, ['unit_price', 'price'], 'unit_price'),
            ], [
                'sold_on' => ['required', 'date'],
                'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
                'customer_name' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'numeric', 'min:0.1'],
                'unit_price' => ['required', 'numeric', 'min:0'],
            ])->validate();

            $item = InventoryItem::query()->findOrFail($payload['inventory_item_id']);

            if ((float) $payload['quantity'] > (float) $item->stock_qty) {
                throw $this->csvRowException($row, __('Quantity exceeds current stock balance for ":item".', ['item' => $item->name]));
            }

            InventorySale::query()->create($payload);
            $item->update(['stock_qty' => (float) $item->stock_qty - (float) $payload['quantity']]);
            $count++;
        }

        return $count;
    }

    protected function importAgentRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $assignedCommunities = $this->csvListValue($row, ['assigned_communities', 'communities'], [
                $this->csvRequiredValue($row, ['community', 'primary_community'], 'community'),
            ]);

            $payload = $this->validateInventoryAgentPayload([
                'user_id' => $this->resolveUserIdFromCsvRow($row, ['user_id', 'internal_user_id', 'internal_user_email', 'user_email', 'internal_user_name', 'user_name'], 'internal user'),
                'supervisor_user_id' => $this->resolveUserIdFromCsvRow($row, ['supervisor_user_id', 'supervisor_id', 'supervisor_email', 'supervisor_name'], 'supervisor'),
                'project_id' => $this->csvValue($row, ['project_id', 'project_name', 'project']) !== null
                    ? $this->resolveProjectIdFromCsvRow($row, ['project_id', 'project_name', 'project'], 'project')
                    : null,
                'cooperative_ids' => $this->resolveCooperativeIdsFromCsvRow($row),
                'agent_type' => Str::lower($this->csvRequiredValue($row, ['agent_type', 'type'], 'agent_type')),
                'first_name' => $this->csvRequiredValue($row, ['first_name'], 'first_name'),
                'middle_name' => $this->csvValue($row, ['middle_name']),
                'last_name' => $this->csvRequiredValue($row, ['last_name'], 'last_name'),
                'gender' => Str::lower($this->csvRequiredValue($row, ['gender'], 'gender')),
                'phone_number' => $this->csvRequiredValue($row, ['phone_number', 'phone'], 'phone_number'),
                'email' => $this->csvRequiredValue($row, ['email', 'email_address'], 'email'),
                'nin' => $this->csvValue($row, ['nin']),
                'state' => $this->csvRequiredValue($row, ['state'], 'state'),
                'lga' => $this->csvRequiredValue($row, ['lga'], 'lga'),
                'community' => $this->csvRequiredValue($row, ['community', 'primary_community'], 'community'),
                'residential_address' => $this->csvRequiredValue($row, ['residential_address', 'address'], 'residential_address'),
                'permanent_address' => $this->csvValue($row, ['permanent_address']),
                'one_stop_shop_id' => $this->csvValue($row, ['one_stop_shop_id', 'one_stop_shop_name', 'assigned_one_stop_shop']) !== null
                    ? $this->resolveOneStopShopIdFromCsvRow($row, ['one_stop_shop_id', 'one_stop_shop_name', 'assigned_one_stop_shop'], 'one-stop shop')
                    : null,
                'account_number' => $this->csvValue($row, ['account_number']),
                'account_name' => $this->csvValue($row, ['account_name']),
                'bank_details' => $this->csvValue($row, ['bank_details', 'bank_name']),
                'assigned_communities' => $assignedCommunities,
                'assigned_warehouse' => $this->csvValue($row, ['assigned_warehouse', 'warehouse']),
                'reconciliation_frequency' => Str::lower($this->csvValue($row, ['reconciliation_frequency']) ?: 'weekly'),
                'settlement_mode' => Str::lower($this->csvValue($row, ['settlement_mode']) ?: 'consignment'),
                'status' => Str::lower($this->csvValue($row, ['status']) ?: 'active'),
            ]);

            $this->createInventoryAgentProfile($payload);
            $count++;
        }

        return $count;
    }

    protected function importInventoryItemRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'name' => $this->csvRequiredValue($row, ['name', 'item_name'], 'name'),
                'stock_qty' => $this->csvRequiredValue($row, ['stock_qty', 'quantity'], 'stock_qty'),
                'unit_price' => $this->csvRequiredValue($row, ['unit_price', 'price'], 'unit_price'),
                'status' => Str::lower($this->csvValue($row, ['status']) ?: 'active'),
            ], [
                'name' => ['required', 'string', 'max:255'],
                'stock_qty' => ['required', 'numeric', 'min:0'],
                'unit_price' => ['required', 'numeric', 'min:0'],
                'status' => ['required', 'in:active,inactive'],
            ])->validate();

            $sku = strtoupper($this->csvValue($row, ['sku']) ?: $this->nextInventorySku());

            if (InventoryItem::query()->where('sku', $sku)->exists()) {
                $sku = $this->nextInventorySku();
            }

            InventoryItem::query()->create($payload + ['sku' => $sku]);
            $count++;
        }

        return $count;
    }

    protected function importCommunityRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'state' => $this->csvRequiredValue($row, ['state'], 'state'),
                'lga' => $this->csvRequiredValue($row, ['lga'], 'lga'),
                'community' => $this->csvRequiredValue($row, ['community', 'name'], 'community'),
                'status' => Str::lower($this->csvValue($row, ['status']) ?: 'active'),
            ], [
                'state' => ['required', 'string', 'max:255'],
                'lga' => ['required', 'string', 'max:255'],
                'community' => ['required', 'string', 'max:255'],
                'status' => ['required', 'in:active,inactive'],
            ])->validate();

            $community = Community::query()->firstOrCreate(
                [
                    'name' => trim((string) $payload['community']),
                    'state' => trim((string) $payload['state']),
                    'lga' => trim((string) $payload['lga']),
                ],
                [
                    'code' => $this->generateCommunityCode((string) $payload['community']),
                    'status' => $payload['status'],
                ],
            );

            if (! $community->wasRecentlyCreated && $community->status !== $payload['status']) {
                $community->update(['status' => $payload['status']]);
            }

            $count++;
        }

        return $count;
    }

    protected function importInventoryCreditRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'credit_date' => $this->csvRequiredValue($row, ['credit_date', 'date'], 'credit_date'),
                'inventory_item_id' => $this->resolveInventoryItemIdFromCsvRow($row),
                'customer_name' => $this->csvRequiredValue($row, ['customer_name', 'customer'], 'customer_name'),
                'amount' => $this->csvRequiredValue($row, ['amount'], 'amount'),
                'status' => Str::lower($this->csvValue($row, ['status']) ?: 'open'),
            ], [
                'credit_date' => ['required', 'date'],
                'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
                'customer_name' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'min:0'],
                'status' => ['required', 'in:open,partial,settled'],
            ])->validate();

            InventoryCredit::query()->create($payload);
            $count++;
        }

        return $count;
    }

    protected function importExtensionVisitRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'visit_date' => $this->csvRequiredValue($row, ['visit_date', 'date'], 'visit_date'),
                'farmer_id' => $this->resolveFarmerIdFromCsvRow($row),
                'officer_name' => $this->csvRequiredValue($row, ['officer_name', 'officer'], 'officer_name'),
                'topic' => $this->csvRequiredValue($row, ['topic'], 'topic'),
                'performance_score' => $this->csvRequiredValue($row, ['performance_score', 'score'], 'performance_score'),
            ], [
                'visit_date' => ['required', 'date'],
                'farmer_id' => ['required', 'exists:venders,id'],
                'officer_name' => ['required', 'string', 'max:255'],
                'topic' => ['required', 'string', 'max:255'],
                'performance_score' => ['required', 'integer', 'between:0,100'],
            ])->validate();

            ExtensionVisit::query()->create($payload);
            $count++;
        }

        return $count;
    }

    protected function importExtensionTrainingRows(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $payload = Validator::make([
                'training_date' => $this->csvRequiredValue($row, ['training_date', 'date'], 'training_date'),
                'title' => $this->csvRequiredValue($row, ['title'], 'title'),
                'location' => $this->csvRequiredValue($row, ['location'], 'location'),
                'attendees' => $this->csvRequiredValue($row, ['attendees'], 'attendees'),
            ], [
                'training_date' => ['required', 'date'],
                'title' => ['required', 'string', 'max:255'],
                'location' => ['required', 'string', 'max:255'],
                'attendees' => ['required', 'integer', 'min:1'],
            ])->validate();

            ExtensionTraining::query()->create($payload);
            $count++;
        }

        return $count;
    }

    protected function importReportSummaryRows(array $rows): array
    {
        $summary = [
            'total_collection' => null,
            'total_cost' => null,
            'net_value' => null,
            'requisition_count' => null,
        ];

        foreach ($rows as $row) {
            $metric = $this->normalizeCsvHeader($this->csvRequiredValue($row, ['metric'], 'metric'));
            $value = $this->csvRequiredValue($row, ['value'], 'value');

            if (! array_key_exists($metric, $summary)) {
                throw $this->csvRowException($row, __('Unsupported report metric ":metric".', ['metric' => $metric]));
            }

            if (! is_numeric($value)) {
                throw $this->csvRowException($row, __('Metric ":metric" must be numeric.', ['metric' => $metric]));
            }

            $summary[$metric] = (float) $value;
        }

        $summary['total_collection'] = (float) ($summary['total_collection'] ?? 0);
        $summary['total_cost'] = (float) ($summary['total_cost'] ?? 0);
        $summary['net_value'] = $summary['net_value'] !== null
            ? (float) $summary['net_value']
            : (float) ($summary['total_collection'] - $summary['total_cost']);
        $summary['requisition_count'] = (float) ($summary['requisition_count'] ?? 0);

        return $summary;
    }

    protected function reportSummaryForRequest(Request $request): array
    {
        if ($request->query('source') === 'imported' && $request->session()->has('gondal_reports_imported_summary')) {
            return (array) $request->session()->get('gondal_reports_imported_summary');
        }

        if ($request->query('source') !== 'imported') {
            $request->session()->forget('gondal_reports_imported_summary');
        }

        return $this->reportService->summary([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'status' => $request->query('status'),
            'project_id' => $request->query('project_id'),
            'mcc_id' => $request->query('mcc_id'),
        ]);
    }

    protected function reportRowsFromSummary(array $summary): array
    {
        return [
            ['total_collection', (string) ($summary['total_collection'] ?? 0)],
            ['total_cost', (string) ($summary['total_cost'] ?? 0)],
            ['net_value', (string) ($summary['net_value'] ?? 0)],
            ['requisition_count', (string) ($summary['requisition_count'] ?? 0)],
        ];
    }

    protected function buildRoleDashboardPayload(Request $request, string $dashboard): array
    {
        $today = now()->startOfDay();
        $weekStart = now()->copy()->startOfWeek();
        $monthStart = now()->copy()->startOfMonth();

        $collectionsQuery = MilkCollection::query()->with('farmer.cooperative');
        $tripsQuery = LogisticsTrip::query()->with(['rider', 'cooperative']);
        $costsQuery = OperationCost::query()->with('cooperative');
        $requisitionsQuery = Requisition::query()->with(['requester', 'cooperative'])->latest();

        if ($from = $request->query('from')) {
            $collectionsQuery->whereDate('collection_date', '>=', $from);
            $tripsQuery->whereDate('departure_time', '>=', $from);
            $costsQuery->whereDate('cost_date', '>=', $from);
            $requisitionsQuery->whereDate('submitted_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $collectionsQuery->whereDate('collection_date', '<=', $to);
            $tripsQuery->whereDate('departure_time', '<=', $to);
            $costsQuery->whereDate('cost_date', '<=', $to);
            $requisitionsQuery->whereDate('submitted_at', '<=', $to);
        }
        if ($projectId = $request->query('project_id')) {
            $collectionsQuery->where('project_id', $projectId);
            $costsQuery->where('project_id', $projectId);
            $requisitionsQuery->where('project_id', $projectId);
        }

        $collections = $collectionsQuery->get();
        $trips = $tripsQuery->get();
        $costs = $costsQuery->get();
        $requisitions = $requisitionsQuery->get();
        $paymentBatches = PaymentBatch::query()->latest('period_end')->get();
        $items = InventoryItem::query()->orderBy('name')->get();
        $credits = InventoryCredit::query()->with(['item', 'agentProfile.user', 'agentProfile.vender'])->orderByDesc('credit_date')->get();
        $stockIssues = StockIssue::query()->with(['item', 'agentProfile.user', 'agentProfile.vender', 'oneStopShop'])->where('issue_stage', 'oss_to_agent')->orderByDesc('issued_on')->get();
        $remittances = AgentRemittance::query()->with(['agentProfile.user', 'agentProfile.vender'])->orderByDesc('remitted_at')->get();
        $reconciliations = InventoryReconciliation::query()->with(['item', 'agentProfile.user', 'agentProfile.vender'])->orderByDesc('period_end')->get();
        $visits = ExtensionVisit::query()->with('farmer')->orderByDesc('visit_date')->get();
        $trainings = ExtensionTraining::query()->orderByDesc('training_date')->get();
        $approvalRules = ApprovalRule::query()->where('is_active', true)->get();
        $cooperatives = Cooperative::query()->withCount('farmers')->orderBy('name')->get();

        $openCreditAmount = (float) $credits->whereIn('status', ['open', 'partial'])->sum(fn (InventoryCredit $credit) => $credit->outstanding_amount > 0 ? $credit->outstanding_amount : $credit->amount);
        $weeklyCollection = (float) $collections->filter(fn (MilkCollection $collection) => optional($collection->collection_date)?->greaterThanOrEqualTo($weekStart))->sum('quantity');
        $pendingRequisitions = $requisitions->where('status', 'pending');
        $cards = [];
        $recentQueue = collect();
        $secondaryQueue = collect();
        $quickLinks = [];

        switch ($dashboard) {
            case 'accountant':
                $cards = [
                    ['label' => __('Outstanding Credit'), 'value' => '₦'.number_format($openCreditAmount, 2), 'meta' => __('Across farmer and agent ledgers')],
                    ['label' => __('Remitted This Week'), 'value' => '₦'.number_format((float) $remittances->filter(fn (AgentRemittance $remittance) => optional($remittance->remitted_at)?->greaterThanOrEqualTo($weekStart))->sum('amount'), 2), 'meta' => __('Cash returned by agents this week')],
                    ['label' => __('Open Variances'), 'value' => number_format($reconciliations->whereIn('status', ['draft', 'submitted', 'under_review', 'approved_with_variance', 'escalated'])->count()), 'meta' => __('Finance exceptions awaiting action')],
                    ['label' => __('Completed Batches'), 'value' => number_format($paymentBatches->where('status', 'completed')->count()), 'meta' => __('Batches already settled')],
                ];
                $recentQueue = $remittances->take(6)->map(fn (AgentRemittance $remittance) => [
                    'title' => $remittance->agentProfile?->outlet_name ?: $remittance->agentProfile?->user?->name ?: $remittance->agentProfile?->vender?->name ?: __('Unknown agent'),
                    'meta' => __(':mode remittance', ['mode' => Str::headline($remittance->reconciliation_mode)]),
                    'value' => '₦'.number_format($remittance->amount, 2),
                    'status' => optional($remittance->remitted_at)->diffForHumans(),
                ]);
                $secondaryQueue = $credits->whereIn('status', ['open', 'partial'])->sortByDesc(fn (InventoryCredit $credit) => $credit->outstanding_amount > 0 ? $credit->outstanding_amount : $credit->amount)->take(6)->map(fn (InventoryCredit $credit) => [
                    'title' => $credit->customer_name ?: __('Unnamed customer'),
                    'meta' => $credit->agentProfile?->outlet_name ?: $credit->agentProfile?->user?->name ?: __('No agent linked'),
                    'value' => '₦'.number_format($credit->outstanding_amount > 0 ? $credit->outstanding_amount : $credit->amount, 2),
                    'status' => Str::headline($credit->status),
                ])->values();
                $quickLinks = [
                    ['label' => __('Payments'), 'url' => route('gondal.payments', ['tab' => 'reconciliation'])],
                    ['label' => __('Credit Tracking'), 'url' => route('gondal.inventory', ['tab' => 'credit'])],
                    ['label' => __('Remittances'), 'url' => route('gondal.inventory', ['tab' => 'remittances'])],
                    ['label' => __('Reports'), 'url' => route('gondal.reports')],
                ];
                break;
            case 'operations_lead':
                $cards = [
                    ['label' => __('Weekly Collection'), 'value' => number_format($weeklyCollection, 2).'L', 'meta' => __('Milk collected since :date', ['date' => $weekStart->format('M j')])],
                    ['label' => __('Pending Requisitions'), 'value' => number_format($pendingRequisitions->count()), 'meta' => __('Requests waiting for approval')],
                    ['label' => __('Ops Cost This Month'), 'value' => '₦'.number_format((float) $costs->filter(fn (OperationCost $cost) => optional($cost->cost_date)?->greaterThanOrEqualTo($monthStart))->sum('amount'), 2), 'meta' => __('Recorded operating cost this month')],
                    ['label' => __('Active Cooperatives'), 'value' => number_format($cooperatives->filter(fn (Cooperative $cooperative) => Str::lower((string) $cooperative->status) === 'active')->count()), 'meta' => __('Field centers currently active')],
                ];
                $recentQueue = $requisitions->take(6)->map(fn (Requisition $requisition) => [
                    'title' => $requisition->title,
                    'meta' => $requisition->cooperative?->name ?: __('No cooperative linked'),
                    'value' => '₦'.number_format($requisition->total_amount, 2),
                    'status' => Str::headline($requisition->status),
                ])->values();
                $secondaryQueue = $cooperatives->map(function (Cooperative $cooperative) use ($collections) {
                    $liters = (float) $collections->filter(fn (MilkCollection $collection) => $collection->farmer?->cooperative_id === $cooperative->id)->sum('quantity');

                    return [
                        'title' => $cooperative->name,
                        'meta' => __(':members members', ['members' => number_format((int) $cooperative->farmers_count)]),
                        'value' => number_format($liters, 2).'L',
                        'status' => $cooperative->location ?: __('No location'),
                    ];
                })->sortByDesc(fn (array $row) => (float) str_replace([',', 'L'], '', $row['value']))->take(6)->values();
                $quickLinks = [
                    ['label' => __('Operations'), 'url' => route('gondal.operations', ['tab' => 'summary'])],
                    ['label' => __('Requisitions'), 'url' => route('gondal.requisitions')],
                    ['label' => __('Logistics'), 'url' => route('gondal.logistics', ['tab' => 'trips'])],
                    ['label' => __('Standard Dashboard'), 'url' => route('gondal.dashboard.standard')],
                ];
                break;
            case 'logistics_coordinator':
                $cards = [
                    ['label' => __('Trips This Week'), 'value' => number_format($trips->filter(fn (LogisticsTrip $trip) => optional($trip->trip_date)?->greaterThanOrEqualTo($weekStart))->count()), 'meta' => __('Logged movements this week')],
                    ['label' => __('Liters Moved'), 'value' => number_format((float) $trips->filter(fn (LogisticsTrip $trip) => optional($trip->trip_date)?->greaterThanOrEqualTo($weekStart))->sum('volume_liters'), 2).'L', 'meta' => __('Volume transported this week')],
                    ['label' => __('Fuel Spend'), 'value' => '₦'.number_format((float) $trips->filter(fn (LogisticsTrip $trip) => optional($trip->trip_date)?->greaterThanOrEqualTo($weekStart))->sum('fuel_cost'), 2), 'meta' => __('Fuel cost this week')],
                    ['label' => __('Open Trips'), 'value' => number_format($trips->whereNotIn('status', ['completed', 'delivered'])->count()), 'meta' => __('Trips not yet closed')],
                ];
                $recentQueue = $trips->take(6)->map(fn (LogisticsTrip $trip) => [
                    'title' => $trip->cooperative?->name ?: __('Unassigned route'),
                    'meta' => $trip->vehicle_name ?: __('Vehicle not set'),
                    'value' => number_format($trip->volume_liters, 2).'L',
                    'status' => Str::headline($trip->status ?: 'planned'),
                ])->values();
                $secondaryQueue = $trips->groupBy('rider_id')->map(function (Collection $group) {
                    $trip = $group->first();

                    return [
                        'title' => $trip?->rider?->name ?: __('Unassigned rider'),
                        'meta' => __(':count trips', ['count' => number_format($group->count())]),
                        'value' => number_format((float) $group->sum('volume_liters'), 2).'L',
                        'status' => __('Avg fuel ₦:value', ['value' => number_format((float) $group->avg('fuel_cost'), 0)]),
                    ];
                })->sortByDesc(fn (array $row) => (float) str_replace([',', 'L'], '', $row['value']))->take(6)->values();
                $quickLinks = [
                    ['label' => __('Trips'), 'url' => route('gondal.logistics', ['tab' => 'trips'])],
                    ['label' => __('Riders'), 'url' => route('gondal.logistics', ['tab' => 'riders'])],
                    ['label' => __('Operations Costs'), 'url' => route('gondal.operations', ['tab' => 'costs'])],
                    ['label' => __('Standard Dashboard'), 'url' => route('gondal.dashboard.standard')],
                ];
                break;
            case 'payments_officer':
                $cards = [
                    ['label' => __('Draft Batches'), 'value' => number_format($paymentBatches->where('status', 'draft')->count()), 'meta' => __('Require review before processing')],
                    ['label' => __('Processing Value'), 'value' => '₦'.number_format((float) $paymentBatches->where('status', 'processing')->sum('total_amount'), 2), 'meta' => __('Batches currently in processing')],
                    ['label' => __('Completed This Month'), 'value' => '₦'.number_format((float) $paymentBatches->filter(fn (PaymentBatch $batch) => optional($batch->period_end)?->greaterThanOrEqualTo($monthStart) && $batch->status === 'completed')->sum('total_amount'), 2), 'meta' => __('Completed during this month')],
                    ['label' => __('Open Credit Cases'), 'value' => number_format($credits->whereIn('status', ['open', 'partial'])->count()), 'meta' => __('Unsettled customer balances')],
                ];
                $recentQueue = $paymentBatches->take(6)->map(fn (PaymentBatch $batch) => [
                    'title' => $batch->name,
                    'meta' => Str::headline($batch->payee_type),
                    'value' => '₦'.number_format($batch->total_amount, 2),
                    'status' => Str::headline($batch->status),
                ])->values();
                $secondaryQueue = $reconciliations->whereIn('status', ['submitted', 'under_review', 'approved_with_variance', 'escalated'])->take(6)->map(fn (InventoryReconciliation $reconciliation) => [
                    'title' => $reconciliation->agentProfile?->outlet_name ?: $reconciliation->agentProfile?->user?->name ?: __('Unknown agent'),
                    'meta' => $reconciliation->item?->name ?: __('No product'),
                    'value' => '₦'.number_format($reconciliation->cash_variance_amount, 2),
                    'status' => Str::headline(str_replace('_', ' ', $reconciliation->status)),
                ])->values();
                $quickLinks = [
                    ['label' => __('Payment Batches'), 'url' => route('gondal.payments', ['tab' => 'batches'])],
                    ['label' => __('Payment Reconciliation'), 'url' => route('gondal.payments', ['tab' => 'reconciliation'])],
                    ['label' => __('Inventory Credit'), 'url' => route('gondal.inventory', ['tab' => 'credit'])],
                    ['label' => __('Standard Dashboard'), 'url' => route('gondal.dashboard.standard')],
                ];
                break;
            case 'field_extension_supervisor':
                $cards = [
                    ['label' => __('Visits This Week'), 'value' => number_format($visits->filter(fn (ExtensionVisit $visit) => optional($visit->visit_date)?->greaterThanOrEqualTo($weekStart))->count()), 'meta' => __('Field follow-up completed this week')],
                    ['label' => __('Training Sessions'), 'value' => number_format($trainings->filter(fn (ExtensionTraining $training) => optional($training->training_date)?->greaterThanOrEqualTo($monthStart))->count()), 'meta' => __('Training events this month')],
                    ['label' => __('Average Visit Score'), 'value' => number_format((float) $visits->filter(fn (ExtensionVisit $visit) => optional($visit->visit_date)?->greaterThanOrEqualTo($monthStart))->avg('performance_score'), 1), 'meta' => __('Average field performance score this month')],
                    ['label' => __('Farmers Reached'), 'value' => number_format($visits->filter(fn (ExtensionVisit $visit) => optional($visit->visit_date)?->greaterThanOrEqualTo($monthStart))->pluck('farmer_id')->unique()->count()), 'meta' => __('Unique farmers reached this month')],
                ];
                $recentQueue = $visits->take(6)->map(fn (ExtensionVisit $visit) => [
                    'title' => $visit->farmer?->name ?: __('Unknown farmer'),
                    'meta' => $visit->topic ?: __('No topic'),
                    'value' => number_format((float) $visit->performance_score, 0).'/100',
                    'status' => optional($visit->visit_date)->toDateString() ?: __('No date'),
                ])->values();
                $secondaryQueue = $visits->groupBy('farmer_id')->map(function (Collection $group) {
                    $visit = $group->sortByDesc('visit_date')->first();

                    return [
                        'title' => $visit?->farmer?->name ?: __('Unknown farmer'),
                        'meta' => __(':count visits', ['count' => number_format($group->count())]),
                        'value' => number_format((float) $group->avg('performance_score'), 1),
                        'status' => $visit?->topic ?: __('No recent topic'),
                    ];
                })->sortBy(fn (array $row) => (float) $row['value'])->take(6)->values();
                $quickLinks = [
                    ['label' => __('Extension Visits'), 'url' => route('gondal.extension', ['tab' => 'visits'])],
                    ['label' => __('Training'), 'url' => route('gondal.extension', ['tab' => 'training'])],
                    ['label' => __('Performance'), 'url' => route('gondal.extension', ['tab' => 'performance'])],
                    ['label' => __('Standard Dashboard'), 'url' => route('gondal.dashboard.standard')],
                ];
                break;
            case 'inventory_officer':
                $cards = [
                    ['label' => __('Stock Value'), 'value' => '₦'.number_format((float) $items->sum(fn (InventoryItem $item) => $item->stock_qty * $item->unit_price), 2), 'meta' => __('Current warehouse stock value')],
                    ['label' => __('Low Stock SKUs'), 'value' => number_format($items->filter(fn (InventoryItem $item) => $item->stock_qty > 0 && $item->stock_qty <= 10)->count()), 'meta' => __('Need replenishment soon')],
                    ['label' => __('Issued This Week'), 'value' => number_format((float) $stockIssues->filter(fn (StockIssue $issue) => optional($issue->issued_on)?->greaterThanOrEqualTo($weekStart))->sum('quantity_issued'), 2), 'meta' => __('Units issued to agents this week')],
                    ['label' => __('Open Variances'), 'value' => number_format($reconciliations->whereIn('status', ['submitted', 'under_review', 'approved_with_variance', 'escalated'])->count()), 'meta' => __('Reconciliation exceptions still open')],
                ];
                $recentQueue = $stockIssues->take(6)->map(fn (StockIssue $issue) => [
                    'title' => $issue->item?->name ?: __('Unknown product'),
                    'meta' => $issue->agentProfile?->outlet_name ?: $issue->agentProfile?->user?->name ?: __('Unknown agent'),
                    'value' => number_format($issue->quantity_issued, 2),
                    'status' => optional($issue->issued_on)->toDateString() ?: __('No date'),
                ])->values();
                $secondaryQueue = $items->sortBy('stock_qty')->take(6)->map(fn (InventoryItem $item) => [
                    'title' => $item->name,
                    'meta' => $item->sku,
                    'value' => number_format($item->stock_qty, 2).' '.$item->unit,
                    'status' => $item->stock_qty <= 0 ? __('Out of stock') : __('Low stock'),
                ])->values();
                $quickLinks = [
                    ['label' => __('Product Catalog'), 'url' => route('gondal.inventory', ['tab' => 'stock'])],
                    ['label' => __('Stock Issues'), 'url' => route('gondal.inventory', ['tab' => 'issues'])],
                    ['label' => __('Reconciliation'), 'url' => route('gondal.inventory', ['tab' => 'reconciliation'])],
                    ['label' => __('Standard Dashboard'), 'url' => route('gondal.dashboard.standard')],
                ];
                break;
            case 'procurement_analyst':
                $cards = [
                    ['label' => __('Pending Requests'), 'value' => number_format($pendingRequisitions->count()), 'meta' => __('Requisitions not yet approved')],
                    ['label' => __('High Priority Pending'), 'value' => number_format($pendingRequisitions->filter(fn (Requisition $requisition) => Str::lower((string) $requisition->priority) === 'high')->count()), 'meta' => __('Pending high-priority requests')],
                    ['label' => __('Approved This Month'), 'value' => '₦'.number_format((float) $requisitions->filter(fn (Requisition $requisition) => optional($requisition->approved_at)?->greaterThanOrEqualTo($monthStart))->sum('total_amount'), 2), 'meta' => __('Approved requisition value this month')],
                    ['label' => __('Active Approval Rules'), 'value' => number_format($approvalRules->count()), 'meta' => __('Configured threshold rules')],
                ];
                $recentQueue = $requisitions->take(6)->map(fn (Requisition $requisition) => [
                    'title' => $requisition->title,
                    'meta' => $requisition->cooperative?->name ?: __('No cooperative linked'),
                    'value' => '₦'.number_format($requisition->total_amount, 2),
                    'status' => Str::headline($requisition->status),
                ])->values();
                $secondaryQueue = $approvalRules->take(6)->map(fn (ApprovalRule $rule) => [
                    'title' => $rule->name,
                    'meta' => __('Approver: :role', ['role' => Str::headline(str_replace('_', ' ', $rule->approver_role))]),
                    'value' => '₦'.number_format($rule->min_amount, 2).' - ₦'.number_format($rule->max_amount, 2),
                    'status' => $rule->is_active ? __('Active') : __('Inactive'),
                ])->values();
                $quickLinks = [
                    ['label' => __('Requisitions'), 'url' => route('gondal.requisitions')],
                    ['label' => __('Approval Rules'), 'url' => route('gondal.admin.approval-rules')],
                    ['label' => __('Operations'), 'url' => route('gondal.operations', ['tab' => 'costs'])],
                    ['label' => __('Standard Dashboard'), 'url' => route('gondal.dashboard.standard')],
                ];
                break;
            default:
                abort(404);
        }

        return [
            'dashboardKey' => $dashboard,
            'dashboardTitle' => $this->roleResolver->dashboardLabel($dashboard),
            'dashboardSubtitle' => __('Focused operational dashboard for :role.', ['role' => Str::headline(str_replace('_', ' ', $dashboard))]),
            'roleLabel' => $this->roleResolver->label($request->user()),
            'cards' => $cards,
            'recentQueue' => $recentQueue,
            'secondaryQueue' => $secondaryQueue,
            'quickLinks' => $quickLinks,
            'standardDashboardUrl' => route('gondal.dashboard.standard'),
        ];
    }

    protected function currentUserAgentProfile(?User $user): ?AgentProfile
    {
        if (! $user) {
            return null;
        }

        return AgentProfile::query()
            ->with(['communityRecord', 'oneStopShop'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    protected function isPartnerUser(?User $user): bool
    {
        return $this->programScopeService->isSponsorUser($user);
    }

    protected function partnerPageData(Request $request): array
    {
        $creatorId = (int) $request->user()->creatorId();

        $partners = User::query()
            ->where('created_by', $creatorId)
            ->where('type', 'client')
            ->orderBy('name')
            ->get();

        $projects = Project::query()
            ->where('created_by', $creatorId)
            ->whereIn('client_id', $partners->pluck('id'))
            ->get()
            ->groupBy('client_id');

        $projectIds = $projects->flatten(1)->pluck('id')->unique()->values();
        $agentsByProject = AgentProfile::query()
            ->whereIn('project_id', $projectIds)
            ->get()
            ->groupBy('project_id');

        $partnerRows = $partners->map(function (User $partner) use ($projects, $agentsByProject) {
            $partnerProjects = $projects->get($partner->id, collect());
            $partnerProjectIds = $partnerProjects->pluck('id');
            $agentCount = $partnerProjectIds->sum(fn ($projectId) => $agentsByProject->get($projectId, collect())->count());
            $farmerCount = ProgramFarmerEnrollment::query()
                ->whereIn('project_id', $partnerProjectIds)
                ->where('status', 'active')
                ->count();

            return [
                'partner' => $partner,
                'project_count' => $partnerProjects->count(),
                'agent_count' => $agentCount,
                'farmer_count' => $farmerCount,
                'projects' => $partnerProjects,
            ];
        });

        return [
            'partnerRows' => $partnerRows,
            'partnerKpis' => [
                'partners' => $partners->count(),
                'active_logins' => $partners->where('is_enable_login', 1)->count(),
                'projects' => $partnerRows->sum('project_count'),
                'agents' => $partnerRows->sum('agent_count'),
                'farmers' => $partnerRows->sum('farmer_count'),
            ],
        ];
    }

    protected function applyAgentVisibilityScope($query, ?User $user)
    {
        $currentAgentProfile = $this->currentUserAgentProfile($user);

        if ($currentAgentProfile) {
            return $query->where('id', $currentAgentProfile->id);
        }

        if ($this->isPartnerUser($user)) {
            return $this->programScopeService->scopedAgentsQuery(
                $query->whereIn('agent_type', ['farmer', 'independent_reseller']),
                $user
            );
        }

        return $query;
    }

    protected function resolveOrCreateCommunity(string $name, ?string $state = null, ?string $lga = null): Community
    {
        $name = trim($name);
        $state = $state !== null ? trim($state) : null;
        $lga = $lga !== null ? trim($lga) : null;

        abort_unless($name !== '', 422);

        return Community::query()->firstOrCreate(
            [
                'name' => $name,
                'state' => $state !== '' ? $state : null,
                'lga' => $lga !== '' ? $lga : null,
            ],
            [
                'code' => $this->generateCommunityCode($name),
                'status' => 'active',
            ],
        );
    }

    protected function generateCommunityCode(string $name): string
    {
        $base = strtoupper(Str::slug($name, '-'));
        $base = $base !== '' ? $base : 'COMMUNITY';
        $code = 'COM-'.$base;
        $suffix = 2;

        while (Community::query()->where('code', $code)->exists()) {
            $code = 'COM-'.$base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    protected function generateOneStopShopCode(string $name): string
    {
        $base = strtoupper(Str::slug($name, '-'));
        $base = $base !== '' ? $base : 'OSS';
        $code = 'OSS-'.$base;
        $suffix = 2;

        while (OneStopShop::query()->where('code', $code)->exists()) {
            $code = 'OSS-'.$base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    protected function normalizeCommunities(array $communities): array
    {
        return collect($communities)
            ->map(fn ($community) => trim((string) $community))
            ->filter()
            ->unique(fn (string $community) => Str::lower($community))
            ->values()
            ->all();
    }

    protected function validateInventoryAgentPayload(array $input): array
    {
        $input['assigned_communities'] = is_array($input['assigned_communities'] ?? null)
            ? $input['assigned_communities']
            : $this->csvListValue(['assigned_communities' => (string) ($input['assigned_communities'] ?? '')], ['assigned_communities']);

        $payload = Validator::make($input, [
            'login_mode' => ['nullable', 'in:existing,new'],
            'password_mode' => ['nullable', 'in:auto,manual'],
            'user_id' => ['nullable', 'exists:users,id'],
            'supervisor_user_id' => ['required', 'exists:users,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'one_stop_shop_id' => ['required', 'exists:gondal_one_stop_shops,id'],
            'cooperative_ids' => ['required', 'array', 'min:1'],
            'cooperative_ids.*' => ['required', 'integer', 'exists:cooperatives,id'],
            'agent_type' => ['required', 'in:employee,farmer,independent_reseller'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            'phone_number' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'nin' => ['nullable', 'string', 'max:50'],
            'state' => ['required', 'string', 'max:255'],
            'lga' => ['required', 'string', 'max:255'],
            'community' => ['required', 'string', 'max:255'],
            'residential_address' => ['required', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            'assigned_communities' => ['required', 'array', 'min:1'],
            'assigned_communities.*' => ['required', 'string', 'max:255'],
            'assigned_warehouse' => ['nullable', 'string', 'max:255'],
            'reconciliation_frequency' => ['required', 'in:daily,weekly,batch'],
            'settlement_mode' => ['required', 'in:consignment,outright_purchase'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'password' => ['nullable', 'string', 'min:6'],
            'password_confirmation' => ['nullable', 'string', 'min:6'],
        ])->validate();

        $payload['login_mode'] = in_array(($payload['login_mode'] ?? null), ['existing', 'new'], true)
            ? $payload['login_mode']
            : (! empty($payload['user_id']) ? 'existing' : 'new');
        $payload['password_mode'] = in_array(($payload['password_mode'] ?? null), ['auto', 'manual'], true)
            ? $payload['password_mode']
            : 'auto';

        if ($payload['login_mode'] === 'existing' && empty($payload['user_id'])) {
            throw ValidationException::withMessages([
                'user_id' => __('Select an existing login account or switch to create new login.'),
            ]);
        }

        if ($payload['login_mode'] === 'new') {
            if (User::query()->where('email', $payload['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('This email already belongs to an existing login. Use existing login instead or change the email address.'),
                ]);
            }

            if ($payload['password_mode'] === 'manual') {
                if (empty($payload['password'])) {
                    throw ValidationException::withMessages([
                        'password' => __('Enter a password for the new login account.'),
                    ]);
                }

                if (($payload['password_confirmation'] ?? null) !== $payload['password']) {
                    throw ValidationException::withMessages([
                        'password_confirmation' => __('Password confirmation does not match.'),
                    ]);
                }
            }
        }

        $normalizedAssignedCommunities = $this->normalizeCommunities($payload['assigned_communities'] ?? []);
        $primaryCommunity = trim((string) $payload['community']);
        $state = trim((string) $payload['state']);
        $lga = trim((string) $payload['lga']);

        if (! in_array($primaryCommunity, $normalizedAssignedCommunities, true)) {
            $normalizedAssignedCommunities[] = $primaryCommunity;
        }

        if (! empty($payload['user_id']) && AgentProfile::query()->where('user_id', $payload['user_id'])->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('This internal user already has an agent profile.'),
            ]);
        }

        if (! empty($payload['user_id']) && (int) $payload['user_id'] === (int) $payload['supervisor_user_id']) {
            throw ValidationException::withMessages([
                'supervisor_user_id' => __('The supervisor must be different from the internal user.'),
            ]);
        }

        $oneStopShop = OneStopShop::query()->find($payload['one_stop_shop_id']);
        if (! $oneStopShop) {
            throw ValidationException::withMessages([
                'one_stop_shop_id' => __('Select a valid one-stop shop.'),
            ]);
        }

        if (in_array(($payload['agent_type'] ?? null), ['farmer', 'independent_reseller'], true) && empty($payload['project_id'])) {
            throw ValidationException::withMessages([
                'project_id' => __('Select the project for non-employee agents.'),
            ]);
        }

        if (! empty($payload['project_id'])) {
            $project = Project::query()->find($payload['project_id']);

            if (! $project) {
                throw ValidationException::withMessages([
                    'project_id' => __('The selected project is invalid.'),
                ]);
            }
        }

        if (($payload['agent_type'] ?? null) === 'employee') {
            $payload['sponsor_user_id'] = null;
        }

        $payload['assigned_warehouse'] = $oneStopShop->name;

        $availableCommunities = Community::query()
            ->where('state', $state)
            ->where('lga', $lga)
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        if ($availableCommunities === []) {
            throw ValidationException::withMessages([
                'community' => __('No communities are configured for the selected state and LGA.'),
            ]);
        }

        if (! in_array($primaryCommunity, $availableCommunities, true)) {
            throw ValidationException::withMessages([
                'community' => __('The selected community does not belong to the chosen state and LGA.'),
            ]);
        }

        $invalidAssignedCommunities = collect($normalizedAssignedCommunities)
            ->reject(fn (string $community) => in_array($community, $availableCommunities, true))
            ->values()
            ->all();

        if ($invalidAssignedCommunities !== []) {
            throw ValidationException::withMessages([
                'assigned_communities' => __('Assigned communities must match the selected state and LGA.'),
            ]);
        }

        $payload['assigned_communities'] = $normalizedAssignedCommunities;

        return $payload;
    }

    protected function createAgentLoginUser(array $payload, User $actor): array
    {
        $creatorId = (int) $actor->creatorId();
        $defaultLanguage = DB::table('settings')
            ->where('name', 'default_language')
            ->where('created_by', $creatorId)
            ->value('value') ?: 'en';

        $password = ($payload['password_mode'] ?? 'auto') === 'manual'
            ? (string) $payload['password']
            : 'AGT'.Str::upper(Str::random(8));
        $roleCandidates = ($payload['agent_type'] ?? null) === 'employee'
            ? ['field_officer', 'staff', 'employee', 'manager']
            : ['client'];

        $role = Role::query()
            ->whereIn('name', $roleCandidates)
            ->where(function ($query) use ($creatorId) {
                $query->where('created_by', $creatorId)
                    ->orWhereNull('created_by');
            })
            ->orderByRaw("FIELD(name, '".implode("','", $roleCandidates)."')")
            ->first();

        $user = User::query()->create([
            'name' => trim(collect([
                $payload['first_name'] ?? null,
                $payload['middle_name'] ?? null,
                $payload['last_name'] ?? null,
            ])->filter()->implode(' ')),
            'email' => $payload['email'],
            'password' => Hash::make($password),
            'type' => $role?->name ?: (($payload['agent_type'] ?? null) === 'employee' ? 'staff' : 'client'),
            'lang' => $defaultLanguage,
            'created_by' => $creatorId,
            'email_verified_at' => now(),
            'is_enable_login' => 1,
        ]);

        if ($role) {
            $user->assignRole($role);
        }

        return [
            'user' => $user,
            'password' => $password,
        ];
    }

    protected function createInventoryAgentProfile(array $payload): AgentProfile
    {
        return DB::transaction(function () use ($payload) {
            $agent = AgentProfile::query()->create([
                'user_id' => $payload['user_id'] ?? null,
                'vender_id' => null,
                'supervisor_user_id' => $payload['supervisor_user_id'] ?? null,
                'sponsor_user_id' => $payload['sponsor_user_id'] ?? null,
                'project_id' => $payload['project_id'] ?? null,
                'one_stop_shop_id' => $payload['one_stop_shop_id'] ?? null,
                'agent_code' => $this->generateInventoryAgentCode(),
                'agent_type' => $payload['agent_type'],
                'first_name' => $payload['first_name'],
                'middle_name' => $payload['middle_name'] ?? null,
                'last_name' => $payload['last_name'],
                'gender' => $payload['gender'],
                'phone_number' => $payload['phone_number'],
                'email' => $payload['email'],
                'nin' => $payload['nin'] ?? null,
                'state' => $payload['state'],
                'lga' => $payload['lga'],
                'community_id' => $this->resolveOrCreateCommunity(
                    (string) $payload['community'],
                    (string) $payload['state'],
                    (string) $payload['lga'],
                )->id,
                'community' => $payload['community'],
                'residential_address' => $payload['residential_address'],
                'permanent_address' => $payload['permanent_address'] ?? null,
                'account_number' => $payload['account_number'] ?? null,
                'account_name' => $payload['account_name'] ?? null,
                'bank_details' => $payload['bank_details'] ?? null,
                'assigned_communities' => $payload['assigned_communities'],
                'assigned_warehouse' => $payload['assigned_warehouse'] ?? null,
                'reconciliation_frequency' => $payload['reconciliation_frequency'],
                'settlement_mode' => $payload['settlement_mode'],
                'credit_sales_enabled' => true,
                'credit_limit' => 0,
                'stock_variance_tolerance' => 0,
                'cash_variance_tolerance' => 0,
                'status' => $payload['status'],
                'notes' => null,
            ]);

            $agent->cooperatives()->sync($payload['cooperative_ids'] ?? []);

            if (! empty($payload['project_id'])) {
                $project = Project::query()->find($payload['project_id']);

                if ($project) {
                    $this->programScopeService->assignAgentToProject($project, $agent);
                }
            }

            return $agent;
        });
    }

    protected function visibleFarmerIdsForAgent(?AgentProfile $agentProfile): ?array
    {
        if (! $agentProfile) {
            return null;
        }

        $communities = $this->normalizeCommunities($agentProfile->assigned_communities ?? []);

        if ($communities === []) {
            return [];
        }

        $normalized = collect($communities)->map(fn (string $community) => Str::lower($community))->all();

        return Vender::query()
            ->where(function ($query) use ($normalized) {
                foreach ($normalized as $community) {
                    $query->orWhereRaw('LOWER(COALESCE(community, "")) = ?', [$community]);
                }
            })
            ->pluck('id')
            ->all();
    }

    protected function farmersForVisibleScope(?array $farmerIds): Collection
    {
        $query = Vender::query()->orderBy('name');

        if ($farmerIds !== null) {
            $query->whereIn('id', $farmerIds);
        }

        return $query->get();
    }

    protected function assertFarmerWithinAgentScope(?AgentProfile $agentProfile, ?Vender $farmer): void
    {
        if (! $agentProfile || ! $farmer) {
            return;
        }

        $communities = collect($this->normalizeCommunities($agentProfile->assigned_communities ?? []))
            ->map(fn (string $community) => Str::lower($community));

        if ($communities->isEmpty()) {
            throw ValidationException::withMessages([
                'agent_profile_id' => [__('Assign at least one community to the selected agent before transacting.')],
            ]);
        }

        $farmerCommunity = $farmer->communityRecord?->name ?: (string) $farmer->community;

        if (! $communities->contains(Str::lower($farmerCommunity))) {
            throw ValidationException::withMessages([
                'farmer_id' => [__('This farmer is outside the selected agent community assignment.')],
            ]);
        }
    }

    protected function availableAgentItemStock(int $agentProfileId, int $inventoryItemId, ?Carbon $asOf = null): float
    {
        return $this->inventoryWorkflowService->availableAgentItemStock($agentProfileId, $inventoryItemId, $asOf);
    }

    protected function createInventorySaleRecord(array $payload): InventorySale
    {
        return $this->inventoryWorkflowService->createInventorySale($payload);
    }

    protected function createWarehouseToOneStopShopIssueFromRequest(Request $request): StockIssue
    {
        $payload = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'one_stop_shop_id' => ['required', 'exists:gondal_one_stop_shops,id'],
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'batch_reference' => ['nullable', 'string', 'max:255'],
            'quantity_issued' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'issued_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->inventoryWorkflowService->createWarehouseToOneStopShopIssue($payload, $request->user());
    }

    protected function createOneStopShopToAgentIssueFromRequest(Request $request): StockIssue
    {
        $payload = $request->validate([
            'agent_profile_id' => ['required', 'exists:gondal_agent_profiles,id'],
            'one_stop_shop_id' => ['required', 'exists:gondal_one_stop_shops,id'],
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'batch_reference' => ['nullable', 'string', 'max:255'],
            'quantity_issued' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'issued_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->inventoryWorkflowService->createOneStopShopToAgentIssue($payload, $request->user());
    }

    protected function requireAdmin(): void
    {
        abort_unless($this->roleResolver->isAdmin(auth()->user()), 403);
    }

    protected function requireModuleAccess(Request $request, string $module): void
    {
        abort_unless(GondalPermissionRegistry::canAccessModule($request->user(), $module), 403);
    }

    protected function requireModulePermission(Request $request, string $module, string $section, string $ability = 'manage'): void
    {
        abort_unless(GondalPermissionRegistry::can($request->user(), $module, $section, $ability), 403);
    }

    protected function visibleModuleTabs(Request $request, string $module): array
    {
        return GondalPermissionRegistry::visiblePageTabs($request->user(), $module);
    }

    protected function resolveModuleTab(Request $request, string $module, string $defaultTab): string
    {
        $tab = GondalPermissionRegistry::resolvePageTab(
            $request->user(),
            $module,
            (string) $request->query('tab', $defaultTab),
            $defaultTab,
        );

        abort_unless($tab !== null, 403);

        return $tab;
    }

    protected function requireActionTab(Request $request, string $module, string $defaultTab, bool $fromInput = false): string
    {
        $requestedTab = (string) ($fromInput ? $request->input('tab', $defaultTab) : $request->query('tab', $defaultTab));
        $allowedTabs = collect(GondalPermissionRegistry::visiblePageTabs($request->user(), $module))
            ->pluck('key')
            ->all();

        abort_unless(in_array($requestedTab, $allowedTabs, true), 403);

        return $requestedTab;
    }

    protected function generateInventoryAgentCode(): string
    {
        do {
            $code = 'AGT-'.now()->format('ymd').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        } while (AgentProfile::query()->where('agent_code', $code)->exists());

        return $code;
    }

    protected function generateInventoryReference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
    }

    protected function requireAnyPermission(array $abilities): void
    {
        $user = auth()->user();

        abort_unless(
            $user && collect($abilities)->contains(fn (string $ability) => $user->can($ability)),
            403
        );
    }

    protected function exportFarmersCsv(Collection $farmers): Response
    {
        return response()->streamDownload(function () use ($farmers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Phone', 'Gender', 'Cooperative', 'MCC', 'Status', 'Digital Payment']);

            foreach ($farmers as $farmer) {
                fputcsv($handle, [
                    $farmer['code'],
                    $farmer['name'],
                    $farmer['phone'],
                    $farmer['gender'],
                    $farmer['cooperative'],
                    $farmer['mcc'],
                    $farmer['status'],
                    $farmer['digital_payment'] ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, 'gondal-farmers.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function agentProfilePageData(Request $request): array
    {
        $creatorId = $request->user()->creatorId();
        $agentProfilesQuery = AgentProfile::query()
            ->with(['user.roles', 'vender', 'supervisor', 'sponsor', 'project.client', 'communityRecord', 'cooperatives', 'oneStopShop'])
            ->orderBy('agent_code');
        $this->applyAgentVisibilityScope($agentProfilesQuery, $request->user());
        $agentProfiles = $agentProfilesQuery->get();

        $stockIssuesQuery = StockIssue::query()
            ->where('issue_stage', 'oss_to_agent')
            ->whereIn('agent_profile_id', $agentProfiles->pluck('id'))
            ->get();

        $remittancesQuery = AgentRemittance::query()
            ->whereIn('agent_profile_id', $agentProfiles->pluck('id'))
            ->get();

        $reconciliationsQuery = InventoryReconciliation::query()
            ->whereIn('agent_profile_id', $agentProfiles->pluck('id'))
            ->get();

        $internalUsers = User::query()
            ->where(function ($query) use ($creatorId) {
                $query->where('id', $creatorId)
                    ->orWhere('created_by', $creatorId);
            })
            ->whereNotIn('type', ['client', 'company', 'super admin'])
            ->orderBy('name')
            ->get();

        $independentAgentUsers = User::query()
            ->where(function ($query) use ($creatorId) {
                $query->where('id', $creatorId)
                    ->orWhere('created_by', $creatorId);
            })
            ->where('type', 'client')
            ->orderBy('name')
            ->get();

        $creditExposureByAgentQuery = InventoryCredit::query()
            ->selectRaw('agent_profile_id, COALESCE(SUM(CASE WHEN outstanding_amount > 0 THEN outstanding_amount ELSE amount END), 0) as balance')
            ->whereNotNull('agent_profile_id')
            ->whereIn('status', ['open', 'partial'])
            ->groupBy('agent_profile_id');
        $creditExposureByAgentQuery->whereIn('agent_profile_id', $agentProfiles->pluck('id'));
        $creditExposureByAgent = $creditExposureByAgentQuery->pluck('balance', 'agent_profile_id');

        $projects = Project::query()
            ->where('created_by', $creatorId)
            ->with('client')
            ->orderBy('project_name')
            ->get();

        return [
            'agentProfiles' => $agentProfiles,
            'internalUsers' => $internalUsers,
            'independentAgentUsers' => $independentAgentUsers,
            'supervisors' => $internalUsers,
            'projects' => $projects,
            'oneStopShops' => OneStopShop::query()->where('created_by', $creatorId)->orderBy('name')->get(),
            'cooperatives' => Cooperative::query()->orderBy('name')->get(),
            'creditExposureByAgent' => $creditExposureByAgent,
            'agentKpis' => [
                'agents' => $agentProfiles->count(),
                'stock_issued' => (float) $stockIssuesQuery->sum('quantity_issued'),
                'remitted' => (float) $remittancesQuery->sum('amount'),
                'open_variances' => $reconciliationsQuery->whereIn('status', ['draft', 'submitted', 'under_review', 'approved_with_variance', 'escalated'])->count(),
            ],
            'agentStateOptions' => Community::query()->pluck('state')->filter()->unique()->sort()->values(),
            'agentLocationHierarchy' => $this->communityLocationHierarchy(),
        ];
    }

    protected function agentDashboardPayload(Request $request): array
    {
        $isPartnerUser = $this->isPartnerUser($request->user());
        $agentProfilesQuery = AgentProfile::query()
            ->with(['user', 'supervisor', 'sponsor', 'communityRecord'])
            ->orderBy('agent_code');
        $this->applyAgentVisibilityScope($agentProfilesQuery, $request->user());

        $agentProfiles = $agentProfilesQuery->get();
        $agentIds = $agentProfiles->pluck('id');
        $currentAgentProfile = $this->currentUserAgentProfile($request->user());
        $today = now()->startOfDay();
        $weekStart = now()->copy()->startOfWeek();

        $sales = InventorySale::query()
            ->with(['item', 'vender', 'agentProfile.user'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderByDesc('sold_on')
            ->get();

        $credits = InventoryCredit::query()
            ->with(['item', 'agentProfile.user'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderByDesc('credit_date')
            ->get();

        $issues = StockIssue::query()
            ->with(['item', 'agentProfile.user', 'warehouse', 'oneStopShop'])
            ->where('issue_stage', 'oss_to_agent')
            ->whereIn('agent_profile_id', $agentIds)
            ->orderByDesc('issued_on')
            ->get();

        $remittances = AgentRemittance::query()
            ->with(['agentProfile.user', 'receiver'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderByDesc('remitted_at')
            ->get();

        $reconciliations = InventoryReconciliation::query()
            ->with(['item', 'agentProfile.user', 'reviewer'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->get();

        $visits = ExtensionVisit::query()
            ->with(['farmer', 'sale.item'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderByDesc('visit_date')
            ->get();

        $liabilities = AgentCashLiability::query()
            ->with('agentProfile.user')
            ->whereIn('agent_profile_id', $agentIds)
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->get();

        $adjustments = AgentInventoryAdjustment::query()
            ->with(['agentProfile.user', 'item'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderByDesc('effective_on')
            ->get();

        $items = InventoryItem::query()->whereIn('id', $issues->pluck('inventory_item_id')->merge($sales->pluck('inventory_item_id'))->unique())->get()->keyBy('id');
        $stockRows = $items->map(function (InventoryItem $item) use ($agentIds) {
            $available = $agentIds->sum(fn ($agentId) => $this->availableAgentItemStock((int) $agentId, (int) $item->id));

            return [
                'item' => $item->name,
                'unit' => $item->unit ?: __('units'),
                'available' => round((float) $available, 2),
            ];
        })->filter(fn (array $row) => $row['available'] > 0)->sortByDesc('available')->take(8)->values();

        $salesTrendStart = now()->copy()->subDays(6)->startOfDay();
        $salesTrend = collect(range(0, 6))->map(function (int $offset) use ($salesTrendStart, $sales) {
            $date = $salesTrendStart->copy()->addDays($offset);
            $daySales = $sales->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->toDateString() === $date->toDateString());

            return [
                'label' => $date->format('M j'),
                'amount' => round((float) $daySales->sum(fn (InventorySale $sale) => $sale->total_amount ?: ($sale->quantity * $sale->unit_price)), 2),
            ];
        });

        $paymentMix = collect(['Cash', 'Transfer', 'Credit'])->map(function (string $method) use ($sales) {
            return [
                'label' => $method,
                'amount' => round((float) $sales->where('payment_method', $method)->sum(fn (InventorySale $sale) => $sale->total_amount ?: ($sale->quantity * $sale->unit_price)), 2),
            ];
        })->values();

        $dashboardAgent = $currentAgentProfile ?: $agentProfiles->first();

        return [
            'dashboardTitle' => $currentAgentProfile ? __('Agent Dashboard') : ($isPartnerUser ? __('Sponsored Agents Dashboard') : __('Agents Dashboard')),
            'dashboardSubtitle' => $currentAgentProfile
                ? __('Track stock, sales, remittances, and reconciliation for your field operations.')
                : ($isPartnerUser
                    ? __('Monitor only the farmer and independent reseller agents linked to your organization projects.')
                    : __('Monitor agent stock, field sales, cash returns, and unresolved exceptions.')),
            'dashboardAgent' => $dashboardAgent,
            'cards' => [
                ['label' => __('Agents In Scope'), 'value' => number_format($agentProfiles->count()), 'meta' => $currentAgentProfile ? __('Your active profile') : __('Active agents visible in this workspace')],
                ['label' => __('Available Stock'), 'value' => number_format((float) $stockRows->sum('available'), 2), 'meta' => __('Units still held by agents')],
                ['label' => __('Sales This Week'), 'value' => '₦'.number_format((float) $sales->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->greaterThanOrEqualTo($weekStart))->sum('total_amount'), 2), 'meta' => __('Agent sales recorded since :date', ['date' => $weekStart->format('M j')])],
                ['label' => __('Open Credit'), 'value' => '₦'.number_format((float) $credits->whereIn('status', ['open', 'partial'])->sum(fn (InventoryCredit $credit) => $credit->outstanding_amount > 0 ? $credit->outstanding_amount : $credit->amount), 2), 'meta' => __('Outstanding farmer credit still attached to agents')],
                ['label' => __('Remitted This Week'), 'value' => '₦'.number_format((float) $remittances->filter(fn (AgentRemittance $remittance) => optional($remittance->remitted_at)?->greaterThanOrEqualTo($weekStart))->sum('amount'), 2), 'meta' => __('Cash returned since :date', ['date' => $weekStart->format('M j')])],
                ['label' => __('Open Exceptions'), 'value' => number_format($reconciliations->whereIn('status', ['draft', 'submitted', 'under_review', 'approved_with_variance', 'escalated'])->count() + $liabilities->count()), 'meta' => __('Reconciliation or cash issues still unresolved')],
            ],
            'stockRows' => $stockRows,
            'recentSales' => $sales->take(6)->map(fn (InventorySale $sale) => [
                'date' => optional($sale->sold_on)->toDateString(),
                'agent' => $sale->agentProfile?->full_name ?: ($sale->agentProfile?->user?->name ?: __('Unknown agent')),
                'buyer' => $sale->vender?->name ?: ($sale->customer_name ?: __('Unknown buyer')),
                'item' => $sale->item?->name ?: __('Unknown item'),
                'amount' => $sale->total_amount ?: ($sale->quantity * $sale->unit_price),
                'payment_method' => $sale->payment_method,
            ]),
            'recentActivity' => $issues->take(4)->map(fn (StockIssue $issue) => [
                'title' => __('Stock issued: :item', ['item' => $issue->item?->name ?: __('Unknown item')]),
                'meta' => $issue->warehouse?->name ?: __('Warehouse'),
                'value' => number_format($issue->quantity_issued, 2).' '.($issue->item?->unit ?: __('units')),
                'status' => optional($issue->issued_on)->diffForHumans(),
            ])->concat($remittances->take(4)->map(fn (AgentRemittance $remittance) => [
                'title' => __('Remittance received'),
                'meta' => $remittance->agentProfile?->full_name ?: ($remittance->agentProfile?->user?->name ?: __('Unknown agent')),
                'value' => '₦'.number_format($remittance->amount, 2),
                'status' => optional($remittance->remitted_at)->diffForHumans(),
            ]))->sortByDesc('status')->take(8)->values(),
            'watchList' => $liabilities->take(4)->map(fn (AgentCashLiability $liability) => [
                'title' => $liability->agentProfile?->full_name ?: ($liability->agentProfile?->user?->name ?: __('Unknown agent')),
                'meta' => __('Cash shortage liability'),
                'value' => '₦'.number_format($liability->amount, 2),
                'status' => Str::headline($liability->status),
            ])->concat($reconciliations->whereIn('status', ['under_review', 'approved_with_variance', 'escalated'])->take(4)->map(fn (InventoryReconciliation $reconciliation) => [
                'title' => $reconciliation->agentProfile?->full_name ?: ($reconciliation->agentProfile?->user?->name ?: __('Unknown agent')),
                'meta' => $reconciliation->item?->name ?: __('No item'),
                'value' => __('Cash :cash / Stock :stock', [
                    'cash' => '₦'.number_format((float) $reconciliation->cash_variance_amount, 2),
                    'stock' => number_format((float) $reconciliation->stock_variance_qty, 2),
                ]),
                'status' => Str::headline(str_replace('_', ' ', $reconciliation->status)),
            ]))->take(8)->values(),
            'fieldSummary' => [
                'visits_today' => (int) $visits->filter(fn (ExtensionVisit $visit) => optional($visit->visit_date)?->greaterThanOrEqualTo($today))->count(),
                'visits_total' => (int) $visits->count(),
                'visit_sales' => '₦'.number_format((float) $visits->filter(fn (ExtensionVisit $visit) => $visit->sale)->sum(fn (ExtensionVisit $visit) => $visit->sale?->total_amount ?: 0), 2),
                'adjustments' => $adjustments->count(),
            ],
            'quickLinks' => [
                ['label' => __('Agents'), 'url' => route('gondal.agents')],
                ['label' => __('Inventory Sales'), 'url' => route('gondal.inventory', ['tab' => 'sales'])],
                ['label' => __('Stock Issues'), 'url' => route('gondal.inventory', ['tab' => 'issues'])],
                ['label' => __('Remittances'), 'url' => route('gondal.inventory', ['tab' => 'remittances'])],
                ['label' => __('Reconciliation'), 'url' => route('gondal.inventory', ['tab' => 'reconciliation'])],
                ['label' => __('Extension'), 'url' => route('gondal.extension', ['tab' => 'visits'])],
            ],
            'salesTrend' => $salesTrend,
            'paymentMix' => $paymentMix,
        ];
    }

    protected function agentAnalyticsPayload(Request $request): array
    {
        $isPartnerUser = $this->isPartnerUser($request->user());
        $currentAgentProfile = $this->currentUserAgentProfile($request->user());
        $agentProfilesQuery = AgentProfile::query()->with(['user', 'communityRecord'])->orderBy('agent_code');
        $this->applyAgentVisibilityScope($agentProfilesQuery, $request->user());

        $agentProfiles = $agentProfilesQuery->get();
        $agentIds = $agentProfiles->pluck('id');
        $today = now()->startOfDay();

        $sales = InventorySale::query()
            ->with(['item', 'agentProfile.user', 'vender.communityRecord'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderBy('sold_on')
            ->get();

        $credits = InventoryCredit::query()
            ->with(['item', 'agentProfile.user'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderBy('credit_date')
            ->get();

        $remittances = AgentRemittance::query()
            ->with('agentProfile.user')
            ->whereIn('agent_profile_id', $agentIds)
            ->orderBy('remitted_at')
            ->get();

        $visits = ExtensionVisit::query()
            ->with(['farmer', 'agentProfile.user'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderBy('visit_date')
            ->get();

        $reconciliations = InventoryReconciliation::query()
            ->with(['item', 'agentProfile.user'])
            ->whereIn('agent_profile_id', $agentIds)
            ->orderBy('period_end')
            ->get();

        $salesTrend30 = collect(range(0, 29))->map(function (int $offset) use ($today, $sales) {
            $date = $today->copy()->subDays(29 - $offset);
            $daySales = $sales->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->toDateString() === $date->toDateString());

            return [
                'label' => $date->format('M j'),
                'amount' => round((float) $daySales->sum(fn (InventorySale $sale) => $sale->total_amount ?: ($sale->quantity * $sale->unit_price)), 2),
                'volume' => round((float) $daySales->sum('quantity'), 2),
            ];
        });

        $weeklyCashVsSales = collect(range(0, 7))->map(function (int $offset) use ($today, $sales, $remittances) {
            $weekStart = $today->copy()->startOfWeek()->subWeeks(7 - $offset);
            $weekEnd = $weekStart->copy()->endOfWeek();
            $periodSales = $sales->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->betweenIncluded($weekStart, $weekEnd));
            $expectedCash = (float) $periodSales
                ->filter(fn (InventorySale $sale) => in_array($sale->payment_method, ['Cash', 'Transfer'], true))
                ->sum(fn (InventorySale $sale) => $sale->total_amount ?: ($sale->quantity * $sale->unit_price));
            $remitted = (float) $remittances
                ->filter(fn (AgentRemittance $remittance) => optional($remittance->remitted_at)?->betweenIncluded($weekStart, $weekEnd))
                ->sum('amount');

            return [
                'label' => $weekStart->format('M j'),
                'expected' => round($expectedCash, 2),
                'remitted' => round($remitted, 2),
            ];
        });

        $creditAging = [
            ['label' => __('Current'), 'amount' => 0.0],
            ['label' => __('1-7 Days'), 'amount' => 0.0],
            ['label' => __('8-30 Days'), 'amount' => 0.0],
            ['label' => __('31+ Days'), 'amount' => 0.0],
        ];

        foreach ($credits->whereIn('status', ['open', 'partial']) as $credit) {
            $amount = (float) ($credit->outstanding_amount > 0 ? $credit->outstanding_amount : $credit->amount);
            $daysOld = max((int) optional($credit->credit_date)?->diffInDays($today), 0);

            if ($daysOld === 0) {
                $creditAging[0]['amount'] += $amount;
            } elseif ($daysOld <= 7) {
                $creditAging[1]['amount'] += $amount;
            } elseif ($daysOld <= 30) {
                $creditAging[2]['amount'] += $amount;
            } else {
                $creditAging[3]['amount'] += $amount;
            }
        }

        $visitTrend14 = collect(range(0, 13))->map(function (int $offset) use ($today, $visits) {
            $date = $today->copy()->subDays(13 - $offset);
            $dayVisits = $visits->filter(fn (ExtensionVisit $visit) => optional($visit->visit_date)?->toDateString() === $date->toDateString());

            return [
                'label' => $date->format('M j'),
                'visits' => $dayVisits->count(),
            ];
        });

        $impactWindowStart = $today->copy()->subDays(29);
        $sales30d = $sales->filter(fn (InventorySale $sale) => optional($sale->sold_on)?->betweenIncluded($impactWindowStart, $today) && $sale->vender_id);
        $visits30d = $visits->filter(fn (ExtensionVisit $visit) => optional($visit->visit_date)?->betweenIncluded($impactWindowStart, $today) && $visit->farmer_id);

        $farmerInteractions30 = $sales30d->map(function (InventorySale $sale) {
            $farmer = $sale->vender;
            $community = $farmer?->communityRecord?->name ?: (string) ($farmer->community ?? '');

            return [
                'farmer_id' => (int) $sale->vender_id,
                'date' => optional($sale->sold_on)?->toDateString(),
                'source' => 'sale',
                'community' => trim($community) !== '' ? $community : __('Unknown community'),
                'farmer_name' => $farmer?->name ?: ($sale->customer_name ?: __('Unknown farmer')),
            ];
        })->concat($visits30d->map(function (ExtensionVisit $visit) {
            $farmer = $visit->farmer;
            $community = $farmer?->communityRecord?->name ?: (string) ($farmer->community ?? '');

            return [
                'farmer_id' => (int) $visit->farmer_id,
                'date' => optional($visit->visit_date)?->toDateString(),
                'source' => 'visit',
                'community' => trim($community) !== '' ? $community : __('Unknown community'),
                'farmer_name' => $farmer?->name ?: __('Unknown farmer'),
            ];
        }))->filter(fn (array $interaction) => ! empty($interaction['farmer_id']) && ! empty($interaction['date']))->values();

        $farmerProfiles30 = $farmerInteractions30
            ->groupBy('farmer_id')
            ->map(function (Collection $group, $farmerId) {
                $sources = $group->pluck('source')->unique()->values();

                return [
                    'farmer_id' => (int) $farmerId,
                    'farmer_name' => (string) $group->pluck('farmer_name')->filter()->first(),
                    'community' => (string) $group->pluck('community')->filter()->first(),
                    'interactions' => $group->count(),
                    'last_served' => (string) $group->pluck('date')->filter()->sortDesc()->first(),
                    'has_sale' => $sources->contains('sale'),
                    'has_visit' => $sources->contains('visit'),
                ];
            })
            ->values();

        $farmerImpactTrend30 = collect(range(0, 29))->map(function (int $offset) use ($today, $farmerInteractions30) {
            $date = $today->copy()->subDays(29 - $offset)->toDateString();
            $dayInteractions = $farmerInteractions30->where('date', $date);

            return [
                'label' => Carbon::parse($date)->format('M j'),
                'farmers' => $dayInteractions->pluck('farmer_id')->unique()->count(),
                'interactions' => $dayInteractions->count(),
            ];
        });

        $communityImpact = $farmerInteractions30
            ->groupBy('community')
            ->map(function (Collection $group, string $community) {
                return [
                    'community' => $community,
                    'farmers' => $group->pluck('farmer_id')->unique()->count(),
                    'interactions' => $group->count(),
                ];
            })
            ->sortByDesc('farmers')
            ->take(8)
            ->values();

        $serviceMix = collect([
            [
                'label' => __('Visit + Sale'),
                'count' => $farmerProfiles30->filter(fn (array $profile) => $profile['has_visit'] && $profile['has_sale'])->count(),
            ],
            [
                'label' => __('Visit Only'),
                'count' => $farmerProfiles30->filter(fn (array $profile) => $profile['has_visit'] && ! $profile['has_sale'])->count(),
            ],
            [
                'label' => __('Sale Only'),
                'count' => $farmerProfiles30->filter(fn (array $profile) => ! $profile['has_visit'] && $profile['has_sale'])->count(),
            ],
        ]);

        $topFarmers = $farmerProfiles30
            ->sortByDesc('interactions')
            ->take(8)
            ->values();

        $topItems = $sales
            ->groupBy('inventory_item_id')
            ->map(function ($group) {
                $first = $group->first();
                $amount = (float) $group->sum(fn (InventorySale $sale) => $sale->total_amount ?: ($sale->quantity * $sale->unit_price));

                return [
                    'item' => $first?->item?->name ?: __('Unknown item'),
                    'amount' => round($amount, 2),
                    'quantity' => round((float) $group->sum('quantity'), 2),
                ];
            })
            ->sortByDesc('amount')
            ->take(8)
            ->values();

        $statusMix = $reconciliations
            ->groupBy('status')
            ->map(fn ($group, $status) => [
                'label' => Str::headline(str_replace('_', ' ', (string) $status)),
                'count' => $group->count(),
            ])
            ->values();

        return [
            'dashboardTitle' => $currentAgentProfile ? __('Agent Analytics') : ($isPartnerUser ? __('Sponsored Agents Analytics') : __('Agents Analytics')),
            'dashboardSubtitle' => $currentAgentProfile
                ? __('Trend and performance analytics for your stock, sales, field activity, and cash settlement.')
                : ($isPartnerUser
                    ? __('Analytics for the farmer and independent reseller agents linked to your organization projects, excluding employee performance.')
                    : __('Trend and performance analytics across agent sales, stock movement, field visits, farmer reach, credit, and remittance.')),
            'dashboardAgent' => $currentAgentProfile ?: $agentProfiles->first(),
            'summary' => [
                'sales_30d' => (float) $salesTrend30->sum('amount'),
                'units_30d' => (float) $salesTrend30->sum('volume'),
                'open_credit' => (float) collect($creditAging)->sum('amount'),
                'visit_count' => (int) $visitTrend14->sum('visits'),
                'farmers_served_30d' => (int) $farmerProfiles30->count(),
                'communities_reached_30d' => (int) $farmerProfiles30->pluck('community')->filter()->unique()->count(),
                'repeat_farmers_30d' => (int) $farmerProfiles30->filter(fn (array $profile) => $profile['interactions'] > 1)->count(),
                'full_service_farmers_30d' => (int) $farmerProfiles30->filter(fn (array $profile) => $profile['has_visit'] && $profile['has_sale'])->count(),
            ],
            'salesTrend30' => $salesTrend30,
            'weeklyCashVsSales' => $weeklyCashVsSales,
            'creditAging' => collect($creditAging)->map(fn (array $bucket) => ['label' => $bucket['label'], 'amount' => round((float) $bucket['amount'], 2)])->values(),
            'visitTrend14' => $visitTrend14,
            'farmerImpactTrend30' => $farmerImpactTrend30,
            'communityImpact' => $communityImpact,
            'serviceMix' => $serviceMix,
            'topFarmers' => $topFarmers,
            'topItems' => $topItems,
            'statusMix' => $statusMix,
        ];
    }

    protected function exportCooperativesCsv(Collection $cooperatives): Response
    {
        return response()->streamDownload(function () use ($cooperatives) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'MCC', 'Leader', 'Phone', 'Site', 'Members', 'Avg Daily Supply', 'Status']);

            foreach ($cooperatives as $cooperative) {
                fputcsv($handle, [
                    $cooperative['code'],
                    $cooperative['name'],
                    $cooperative['mcc'],
                    $cooperative['leader_name'],
                    $cooperative['leader_phone'],
                    $cooperative['site_location'],
                    $cooperative['members_count'],
                    $cooperative['avg_daily_supply'],
                    $cooperative['status'],
                ]);
            }

            fclose($handle);
        }, 'gondal-cooperatives.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
