<?php

namespace App\Http\Controllers\Gondal;

use App\Http\Controllers\Controller;
use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\AgentRemittance;
use App\Models\Gondal\ApprovalRule;
use App\Models\Gondal\AuditLog;
use App\Models\Gondal\ExtensionTraining;
use App\Models\Gondal\ExtensionVisit;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\InventoryReconciliation;
use App\Models\Gondal\InventorySale;
use App\Models\Gondal\LogisticsRider;
use App\Models\Gondal\LogisticsTrip;
use App\Models\Gondal\OperationCost;
use App\Models\Gondal\PaymentBatch;
use App\Models\Gondal\Requisition;
use App\Models\Gondal\RequisitionEvent;
use App\Models\Gondal\RequisitionItem;
use App\Models\Gondal\StockIssue;
use App\Models\Gondal\WarehouseStock;
use App\Models\LoginDetail;
use App\Models\User;
use App\Models\Vender;
use App\Models\warehouse as Warehouse;
use App\Services\Gondal\FinanceService;
use App\Services\Gondal\ReportService;
use App\Services\Gondal\RequisitionWorkflowService;
use App\Support\GondalPermissionRegistry;
use App\Support\GondalRoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cooperatives\Models\Cooperative;
use Modules\MilkCollection\Models\MilkCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GondalController extends Controller
{
    public function __construct(
        protected RequisitionWorkflowService $workflowService,
        protected FinanceService $financeService,
        protected ReportService $reportService,
        protected GondalRoleResolver $roleResolver,
    ) {
    }

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

        $query = Vender::query()->with('cooperative');

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

        $farmers = $query->orderBy('vender_id')->get()->map(function (Vender $farmer) use ($latestCollections) {
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
                'community' => (string) $farmer->community,
                'bank_name' => (string) $farmer->bank_name,
                'account_number' => (string) $farmer->account_number,
                'target_liters' => (float) ($farmer->target_liters ?? 0),
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

    public function storeFarmer(Request $request): RedirectResponse
    {
        $validated = $this->validateFarmerPayload($request);
        $cooperative = $this->resolveFarmerCooperative((int) $validated['cooperative_id'], (string) $validated['mcc']);
        $photoPath = $this->storeFarmerPhoto($request->file('profile_photo'));

        $farmer = new Vender();
        $farmer->vender_id = $this->nextFarmerNumber();
        $farmer->name = $validated['name'];
        $farmer->email = $validated['email'] ?: null;
        $farmer->contact = $validated['phone'];
        $farmer->created_by = (int) $request->user()->creatorId();
        $farmer->cooperative_id = $cooperative->id;
        $farmer->gender = $validated['gender'];
        $farmer->status = 'active';
        $farmer->registration_date = $validated['registration_date'] ?? now()->toDateString();
        $farmer->state = $validated['state'];
        $farmer->lga = $validated['lga'];
        $farmer->ward = $validated['ward'];
        $farmer->community = $validated['community'];
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

        $farmer->name = $validated['name'];
        $farmer->email = $validated['email'] ?: null;
        $farmer->contact = $validated['phone'];
        $farmer->cooperative_id = $cooperative->id;
        $farmer->gender = $validated['gender'];
        $farmer->status = $validated['status'] ?? (string) $farmer->status;
        $farmer->registration_date = $validated['registration_date'] ?? $farmer->registration_date;
        $farmer->state = $validated['state'] ?: $farmer->state;
        $farmer->lga = $validated['lga'] ?: $farmer->lga;
        $farmer->ward = $validated['ward'] ?: $farmer->ward;
        $farmer->community = $validated['community'] ?: $farmer->community;
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
        $recordsQuery = MilkCollection::query()->with('farmer.cooperative');

        if ($from = $request->query('from')) {
            $recordsQuery->whereDate('collection_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $recordsQuery->whereDate('collection_date', '<=', $to);
        }
        if ($grade = $request->query('grade')) {
            $recordsQuery->where('quality_grade', strtoupper((string) $grade));
        }

        $records = $recordsQuery->orderByDesc('collection_date')->take(50)->get();
        $dayCollections = MilkCollection::query()->with('farmer.cooperative')->whereDate('collection_date', $selectedDate)->get();

        $summaryRows = Cooperative::query()->withCount('farmers')->orderBy('name')->get()->map(function (Cooperative $cooperative) use ($dayCollections) {
            $rows = $this->collectionsForCooperative($dayCollections, $cooperative);
            $count = max(1, $rows->count());

            return [
                'name' => $cooperative->name,
                'mcc' => $cooperative->location ?: 'N/A',
                'farmers_count' => $rows->pluck('farmer_id')->filter()->unique()->count(),
                'liters' => round((float) $rows->sum('quantity'), 2),
                'avg_fat_percent' => round((float) $rows->avg('fat_percentage'), 2),
                'grade_a_count' => $rows->filter(fn (MilkCollection $collection) => strtoupper((string) $collection->quality_grade) === 'A')->count(),
                'total_records' => $count === 1 && $rows->isEmpty() ? 0 : $count,
            ];
        });

        $recentFarmers = MilkCollection::query()
            ->with('farmer')
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
            'farmers' => Vender::query()->orderBy('name')->get(),
            'recentFarmers' => $recentFarmers,
        ]);
    }

    public function storeMilkCollection(Request $request): RedirectResponse
    {
        $validated = $this->validateMilkCollectionPayload($request);
        $farmer = Vender::query()->with('cooperative')->findOrFail($validated['farmer_id']);

        $collection = MilkCollection::withoutEvents(function () use ($validated, $farmer, $request) {
            return MilkCollection::query()->create([
                'batch_id' => 'GON-'.now()->format('YmdHis'),
                'mcc_id' => $farmer->cooperative?->location ?: 'N/A',
                'farmer_id' => $farmer->id,
                'cooperative_id' => $farmer->cooperative_id,
                'quantity' => $validated['liters'],
                'fat_percentage' => $validated['fat_percent'],
                'snf_percentage' => $validated['snf_percent'],
                'temperature' => $validated['temperature'] ?: null,
                'quality_grade' => $validated['grade'],
                'adulteration_test' => $validated['adulteration_test'],
                'rejection_reason' => $validated['grade'] === 'C' ? ($validated['rejection_reason'] ?: 'Rejected during quality review') : null,
                'recorded_by' => $request->user()->id,
                'collection_date' => $validated['collection_date'],
            ]);
        });

        $this->writeAuditLog($request, 'milk_collection', 'created', [
            'collection_id' => $collection->id,
            'farmer_id' => $farmer->id,
            'liters' => $collection->quantity,
            'grade' => $collection->quality_grade,
        ]);

        return redirect()->route('gondal.milk-collection')->with('success', __('Milk collection recorded successfully.'));
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
            if (!$isLead) {
                return back()->with('error', __('Only Component Leads or higher can review pending costs.'));
            }
            $cost->update(['approval_status' => 'reviewed']);
            return back()->with('success', __('Operational cost reviewed by Component Lead.'));
        } elseif ($cost->approval_status === 'reviewed') {
            if (!$isFinance) {
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

        if ($amount > 200000 && !$isED) {
            return back()->with('error', __('Only the Executive Director can approve requisitions over ₦200,000.'));
        } elseif ($amount >= 50000 && $amount <= 200000 && !$isLead) {
            return back()->with('error', __('Only a Component Lead or Exec. Director can approve requisitions over ₦50,000.'));
        } elseif ($amount < 50000 && !$isFinance) {
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

        if ($amount > 200000 && !$isED) {
            return back()->with('error', __('Only the Executive Director can reject requisitions over ₦200,000.'));
        } elseif ($amount >= 50000 && $amount <= 200000 && !$isLead) {
            return back()->with('error', __('Only a Component Lead or Exec. Director can reject requisitions over ₦50,000.'));
        } elseif ($amount < 50000 && !$isFinance) {
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
        $batches = PaymentBatch::query()
            ->when($selectedStatus !== '', fn ($query) => $query->where('status', $selectedStatus))
            ->orderByDesc('period_end')
            ->get();
        $credits = InventoryCredit::query()
            ->with('item')
            ->when($selectedStatus !== '', fn ($query) => $query->where('status', $selectedStatus))
            ->orderByDesc('credit_date')
            ->get();

        return view('gondal.payments', [
            'tab' => $tab,
            'visibleTabs' => $this->visibleModuleTabs($request, 'payments'),
            'selectedStatus' => $selectedStatus,
            'overviewCards' => [
                ['title' => 'Farmer Payments', 'amount' => '₦'.number_format((float) $batches->where('payee_type', 'farmer')->sum('total_amount'), 2), 'meta' => $batches->where('payee_type', 'farmer')->count().' batches'],
                ['title' => 'Rider Payments', 'amount' => '₦'.number_format((float) $batches->where('payee_type', 'rider')->sum('total_amount'), 2), 'meta' => $batches->where('payee_type', 'rider')->count().' batches'],
                ['title' => 'Staff Payments', 'amount' => '₦'.number_format((float) $batches->where('payee_type', 'staff')->sum('total_amount'), 2), 'meta' => $batches->where('payee_type', 'staff')->count().' batches'],
                ['title' => 'Open Reconciliation', 'amount' => '₦'.number_format((float) InventoryCredit::query()->where('status', 'open')->sum('amount'), 2), 'meta' => InventoryCredit::query()->where('status', 'open')->count().' credits'],
            ],
            'batches' => $batches,
            'reconciliationRows' => $credits,
        ]);
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
            'gateway_reference' => 'PAY-' . strtoupper(uniqid()),
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
        $creatorId = $request->user()->creatorId();

        $sales = InventorySale::query()
            ->with(['item', 'vender', 'agentProfile.user', 'agentProfile.vender'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('sold_on', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('sold_on', '<=', $request->query('to')))
            ->orderByDesc('sold_on')
            ->get();
        $credits = InventoryCredit::query()
            ->with(['item', 'vender', 'agentProfile.user', 'agentProfile.vender'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('credit_date')
            ->get();
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
        $agentProfiles = AgentProfile::query()
            ->with(['user.roles', 'vender', 'supervisor'])
            ->orderBy('agent_code')
            ->get();
        $stockIssues = StockIssue::query()
            ->with(['agentProfile.user', 'agentProfile.vender', 'item', 'issuer', 'warehouse'])
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();
        $remittances = AgentRemittance::query()
            ->with(['agentProfile.user', 'agentProfile.vender', 'receiver'])
            ->orderByDesc('remitted_at')
            ->get();
        $reconciliations = InventoryReconciliation::query()
            ->with(['agentProfile.user', 'agentProfile.vender', 'item', 'submitter', 'reviewer'])
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->get();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $farmerCredits = Vender::query()
            ->whereHas('inventoryCredits', function($q) {
                $q->whereIn('status', ['open', 'partial']);
            })
            ->withSum(['inventoryCredits as total_owed' => function($q) {
                $q->whereIn('status', ['open', 'partial']);
            }], 'amount')
            ->get()->map(function($vender) {
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
        $supervisors = $internalUsers;
        $farmers = Vender::query()->orderBy('name')->get();
        $creditExposureByAgent = InventoryCredit::query()
            ->selectRaw('agent_profile_id, COALESCE(SUM(CASE WHEN outstanding_amount > 0 THEN outstanding_amount ELSE amount END), 0) as balance')
            ->whereNotNull('agent_profile_id')
            ->whereIn('status', ['open', 'partial'])
            ->groupBy('agent_profile_id')
            ->pluck('balance', 'agent_profile_id');
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
            'visibleTabs' => $this->visibleModuleTabs($request, 'inventory'),
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
            'warehouses' => $warehouses,
            'warehouseStocks' => $warehouseStocks,
        ]);
    }

    public function storeInventorySale(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'sales', 'create');

        $payload = $request->validate([
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'agent_profile_id' => ['nullable', 'exists:gondal_agent_profiles,id'],
            'vender_id' => ['nullable', 'exists:venders,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:Cash,Credit,Transfer'],
            'sold_on' => ['required', 'date'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date', 'after_or_equal:sold_on'],
        ]);

        if ($payload['vender_id'] ?? null) {
            $vender = Vender::query()->find($payload['vender_id']);
            $payload['customer_name'] = $vender ? $vender->name : $payload['customer_name'];
        }

        $item = InventoryItem::query()->findOrFail($payload['inventory_item_id']);
        $agentProfile = ! empty($payload['agent_profile_id'])
            ? AgentProfile::query()->findOrFail($payload['agent_profile_id'])
            : null;

        if ((float) $payload['quantity'] > (float) $item->stock_qty) {
            throw ValidationException::withMessages([
                'quantity' => [__('Quantity exceeds current stock balance.')],
            ]);
        }

        if ($payload['payment_method'] === 'Credit' && $agentProfile && ! $agentProfile->credit_sales_enabled) {
            throw ValidationException::withMessages([
                'payment_method' => [__('Credit sales are disabled for the selected agent.')],
            ]);
        }

        $totalAmount = round((float) $payload['quantity'] * (float) $payload['unit_price'], 2);
        if ($payload['payment_method'] === 'Credit' && $agentProfile) {
            $currentExposure = (float) InventoryCredit::query()
                ->where('agent_profile_id', $agentProfile->id)
                ->whereIn('status', ['open', 'partial'])
                ->sum('outstanding_amount');

            if ((float) $agentProfile->credit_limit > 0 && ($currentExposure + $totalAmount) > (float) $agentProfile->credit_limit) {
                throw ValidationException::withMessages([
                    'agent_profile_id' => [__('This sale exceeds the selected agent credit limit.')],
                ]);
            }
        }

        $sale = InventorySale::query()->create([
            'inventory_item_id' => $payload['inventory_item_id'],
            'agent_profile_id' => $payload['agent_profile_id'] ?? null,
            'vender_id' => $payload['vender_id'] ?? null,
            'quantity' => $payload['quantity'],
            'unit_price' => $payload['unit_price'],
            'total_amount' => $totalAmount,
            'payment_method' => $payload['payment_method'],
            'credit_allowed_snapshot' => $agentProfile?->credit_sales_enabled ?? false,
            'sold_on' => $payload['sold_on'],
            'customer_name' => $payload['customer_name'] ?? null,
        ]);
        $item->update(['stock_qty' => (float) $item->stock_qty - (float) $payload['quantity']]);

        if ($payload['payment_method'] === 'Credit') {
            InventoryCredit::query()->create([
                'inventory_item_id' => $item->id,
                'agent_profile_id' => $payload['agent_profile_id'] ?? null,
                'inventory_sale_id' => $sale->id,
                'vender_id' => $payload['vender_id'] ?? null,
                'customer_name' => $payload['customer_name'] ?? 'Unknown Customer',
                'amount' => $totalAmount,
                'outstanding_amount' => $totalAmount,
                'status' => 'open',
                'credit_date' => $payload['sold_on'],
                'due_date' => $payload['due_date'] ?? Carbon::parse($payload['sold_on'])->addDays(14)->toDateString(),
            ]);
        }

        $this->writeAuditLog($request, 'inventory', 'sale_recorded', [
            'sale_id' => $sale->id,
            'inventory_item_id' => $item->id,
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

        $payload = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'vender_id' => ['nullable', 'exists:venders,id'],
            'supervisor_user_id' => ['nullable', 'exists:users,id'],
            'agent_type' => ['required', 'in:employee,vendor,independent_reseller'],
            'outlet_name' => ['nullable', 'string', 'max:255'],
            'assigned_warehouse' => ['nullable', 'string', 'max:255'],
            'reconciliation_frequency' => ['required', 'in:daily,weekly,batch'],
            'settlement_mode' => ['required', 'in:consignment,outright_purchase'],
            'credit_sales_enabled' => ['nullable', 'boolean'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'stock_variance_tolerance' => ['nullable', 'numeric', 'min:0'],
            'cash_variance_tolerance' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($payload['user_id']) && empty($payload['vender_id'])) {
            throw ValidationException::withMessages([
                'user_id' => [__('Select an internal user or linked vendor for this agent profile.')],
            ]);
        }

        $agent = AgentProfile::query()->create([
            'user_id' => $payload['user_id'] ?? null,
            'vender_id' => $payload['vender_id'] ?? null,
            'supervisor_user_id' => $payload['supervisor_user_id'] ?? null,
            'agent_code' => $this->generateInventoryAgentCode(),
            'agent_type' => $payload['agent_type'],
            'outlet_name' => $payload['outlet_name'] ?? null,
            'assigned_warehouse' => $payload['assigned_warehouse'] ?? null,
            'reconciliation_frequency' => $payload['reconciliation_frequency'],
            'settlement_mode' => $payload['settlement_mode'],
            'credit_sales_enabled' => (bool) ($payload['credit_sales_enabled'] ?? false),
            'credit_limit' => $payload['credit_limit'] ?? 0,
            'stock_variance_tolerance' => $payload['stock_variance_tolerance'] ?? 0,
            'cash_variance_tolerance' => $payload['cash_variance_tolerance'] ?? 0,
            'status' => $payload['status'],
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->writeAuditLog($request, 'inventory', 'agent_profile_created', ['agent_profile_id' => $agent->id]);

        return redirect()->route('gondal.inventory', ['tab' => 'agents'])->with('success', __('Agent profile created successfully.'));
    }

    public function storeInventoryStockIssue(Request $request): RedirectResponse
    {
        $this->requireModulePermission($request, 'inventory', 'issues', 'create');

        $issue = $this->createStockIssueFromRequest($request);

        $this->writeAuditLog($request, 'inventory', 'stock_issued', ['stock_issue_id' => $issue->id]);

        return redirect()->route('gondal.inventory', ['tab' => 'issues'])->with('success', __('Stock issued to agent successfully.'));
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

        $remittance = AgentRemittance::query()->create([
            'agent_profile_id' => $payload['agent_profile_id'],
            'received_by' => $request->user()->id,
            'reconciliation_mode' => $payload['reconciliation_mode'],
            'reference' => $this->generateInventoryReference('RMT'),
            'amount' => $payload['amount'],
            'payment_method' => $payload['payment_method'],
            'period_start' => $payload['period_start'] ?? null,
            'period_end' => $payload['period_end'] ?? null,
            'remitted_at' => Carbon::parse($payload['remitted_at'])->endOfDay(),
            'notes' => $payload['notes'] ?? null,
        ]);

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

        $periodStart = Carbon::parse($payload['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($payload['period_end'])->endOfDay();

        $issueQuery = StockIssue::query()
            ->where('agent_profile_id', $payload['agent_profile_id'])
            ->where('inventory_item_id', $payload['inventory_item_id']);
        $saleQuery = InventorySale::query()
            ->where('agent_profile_id', $payload['agent_profile_id'])
            ->where('inventory_item_id', $payload['inventory_item_id']);
        $creditQuery = InventoryCredit::query()
            ->where('agent_profile_id', $payload['agent_profile_id'])
            ->where('inventory_item_id', $payload['inventory_item_id']);
        $remittanceQuery = AgentRemittance::query()
            ->where('agent_profile_id', $payload['agent_profile_id']);

        $openingIssues = (float) (clone $issueQuery)->whereDate('issued_on', '<', $periodStart->toDateString())->sum('quantity_issued');
        $openingSales = (float) (clone $saleQuery)->whereDate('sold_on', '<', $periodStart->toDateString())->sum('quantity');
        $openingStockQty = $openingIssues - $openingSales;

        $issuedStockQty = (float) (clone $issueQuery)
            ->whereBetween('issued_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('quantity_issued');
        $periodSales = (clone $saleQuery)
            ->whereBetween('sold_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $soldStockQty = (float) $periodSales->sum('quantity');
        $cashSalesAmount = (float) $periodSales->where('payment_method', 'Cash')->sum('total_amount');
        $transferSalesAmount = (float) $periodSales->where('payment_method', 'Transfer')->sum('total_amount');
        $creditSalesAmount = (float) $periodSales->where('payment_method', 'Credit')->sum('total_amount');
        $expectedStockQty = $openingStockQty + $issuedStockQty - $soldStockQty;
        $countedStockQty = (float) $payload['counted_stock_qty'];
        $stockVarianceQty = $countedStockQty - $expectedStockQty;
        $creditCollectionsAmount = 0.0;
        $expectedCashAmount = $cashSalesAmount + $transferSalesAmount + $creditCollectionsAmount;
        $remittedCashAmount = (float) (clone $remittanceQuery)
            ->whereBetween('remitted_at', [$periodStart, $periodEnd])
            ->sum('amount');
        $cashVarianceAmount = $remittedCashAmount - $expectedCashAmount;
        $outstandingCreditAmount = (float) (clone $creditQuery)
            ->whereIn('status', ['open', 'partial'])
            ->selectRaw('COALESCE(SUM(CASE WHEN outstanding_amount > 0 THEN outstanding_amount ELSE amount END), 0) as balance')
            ->value('balance');

        $status = 'submitted';
        if (abs($stockVarianceQty) > 0.0001 || abs($cashVarianceAmount) > 0.0001) {
            $status = 'under_review';
        }

        $reconciliation = InventoryReconciliation::query()->create([
            'agent_profile_id' => $payload['agent_profile_id'],
            'inventory_item_id' => $payload['inventory_item_id'],
            'submitted_by' => $request->user()->id,
            'reconciliation_mode' => $payload['reconciliation_mode'],
            'reference' => $this->generateInventoryReference('REC'),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'opening_stock_qty' => $openingStockQty,
            'issued_stock_qty' => $issuedStockQty,
            'sold_stock_qty' => $soldStockQty,
            'returned_stock_qty' => 0,
            'damaged_stock_qty' => 0,
            'expected_stock_qty' => $expectedStockQty,
            'counted_stock_qty' => $countedStockQty,
            'stock_variance_qty' => $stockVarianceQty,
            'cash_sales_amount' => $cashSalesAmount,
            'transfer_sales_amount' => $transferSalesAmount,
            'credit_sales_amount' => $creditSalesAmount,
            'credit_collections_amount' => $creditCollectionsAmount,
            'expected_cash_amount' => $expectedCashAmount,
            'remitted_cash_amount' => $remittedCashAmount,
            'cash_variance_amount' => $cashVarianceAmount,
            'outstanding_credit_amount' => $outstandingCreditAmount,
            'status' => $status,
            'agent_notes' => $payload['agent_notes'] ?? null,
        ]);

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

        $status = match ($payload['action']) {
            'approve' => 'approved',
            'approve_with_variance' => 'approved_with_variance',
            'escalate' => 'escalated',
            'request_recount' => 'recount_requested',
        };

        $existingNotes = trim((string) $reconciliation->review_notes);
        $newNotes = trim((string) ($payload['review_notes'] ?? ''));
        $actionLabel = Str::headline(str_replace('_', ' ', $payload['action']));

        $reconciliation->update([
            'reviewed_by' => $request->user()->id,
            'status' => $status,
            'review_notes' => trim($existingNotes.($existingNotes !== '' && $newNotes !== '' ? "\n\n" : '').($newNotes !== '' ? '['.$actionLabel.'] '.$newNotes : '['.$actionLabel.']')),
        ]);

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
        $dispatches = StockIssue::query()
            ->with(['warehouse', 'item', 'agentProfile.user', 'agentProfile.vender', 'issuer'])
            ->whereNotNull('warehouse_id')
            ->whereHas('warehouse', fn ($query) => $query->where('created_by', $creatorId))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();
        $dispatchesForAllocation = $dispatches
            ->sortBy(fn (StockIssue $issue) => sprintf(
                '%s-%010d',
                optional($issue->issued_on)->toDateString() ?: '9999-12-31',
                $issue->id
            ))
            ->values();
        $salesByAgentItem = InventorySale::query()
            ->selectRaw('agent_profile_id, inventory_item_id, COALESCE(SUM(quantity), 0) as quantity')
            ->whereNotNull('agent_profile_id')
            ->groupBy('agent_profile_id', 'inventory_item_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->agent_profile_id.'|'.$row->inventory_item_id => (float) $row->quantity,
            ]);
        $reconciledSalesByAgentItem = InventoryReconciliation::query()
            ->selectRaw('agent_profile_id, inventory_item_id, COALESCE(SUM(sold_stock_qty), 0) as quantity')
            ->whereIn('status', ['approved', 'approved_with_variance', 'closed'])
            ->groupBy('agent_profile_id', 'inventory_item_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->agent_profile_id.'|'.$row->inventory_item_id => (float) $row->quantity,
            ]);
        $soldRemainingByAgentItem = $salesByAgentItem->map(fn (float $quantity) => $quantity);
        $reconciledRemainingByAgentItem = $reconciledSalesByAgentItem->map(fn (float $quantity) => $quantity);
        $outsideStockRows = $dispatchesForAllocation->map(function (StockIssue $dispatch) use ($soldRemainingByAgentItem, $reconciledRemainingByAgentItem) {
            $key = $dispatch->agent_profile_id.'|'.$dispatch->inventory_item_id;
            $issuedQuantity = (float) $dispatch->quantity_issued;
            $soldRemaining = (float) ($soldRemainingByAgentItem[$key] ?? 0);
            $soldAllocated = min($issuedQuantity, max($soldRemaining, 0));

            $soldRemainingByAgentItem[$key] = max($soldRemaining - $soldAllocated, 0);

            $reconciledRemaining = (float) ($reconciledRemainingByAgentItem[$key] ?? 0);
            $reconciledAllocated = min($soldAllocated, max($reconciledRemaining, 0));

            $reconciledRemainingByAgentItem[$key] = max($reconciledRemaining - $reconciledAllocated, 0);

            return [
                'dispatch_id' => $dispatch->id,
                'warehouse_id' => $dispatch->warehouse_id,
                'agent_profile_id' => $dispatch->agent_profile_id,
                'inventory_item_id' => $dispatch->inventory_item_id,
                'warehouse_name' => $dispatch->warehouse?->name ?: __('Unknown warehouse'),
                'agent_name' => $dispatch->agentProfile?->outlet_name ?: $dispatch->agentProfile?->user?->name ?: $dispatch->agentProfile?->vender?->name ?: __('Unknown agent'),
                'agent_code' => $dispatch->agentProfile?->agent_code,
                'item_name' => $dispatch->item?->name ?: __('Unknown product'),
                'item_unit' => $dispatch->item?->unit,
                'issue_reference' => $dispatch->issue_reference,
                'issued_on' => $dispatch->issued_on,
                'issued_quantity' => $issuedQuantity,
                'sold_quantity' => $soldAllocated,
                'unsold_quantity' => max($issuedQuantity - $soldAllocated, 0),
                'sold_pending_reconciliation' => max($soldAllocated - $reconciledAllocated, 0),
            ];
        })
            ->groupBy(fn (array $row) => implode('|', [
                $row['warehouse_id'],
                $row['agent_profile_id'],
                $row['inventory_item_id'],
            ]))
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'warehouse_name' => $first['warehouse_name'],
                    'agent_name' => $first['agent_name'],
                    'agent_code' => $first['agent_code'],
                    'item_name' => $first['item_name'],
                    'item_unit' => $first['item_unit'],
                    'issued_quantity' => (float) $rows->sum('issued_quantity'),
                    'sold_quantity' => (float) $rows->sum('sold_quantity'),
                    'unsold_quantity' => (float) $rows->sum('unsold_quantity'),
                    'sold_pending_reconciliation' => (float) $rows->sum('sold_pending_reconciliation'),
                    'latest_issue_date' => $rows->pluck('issued_on')->filter()->max(),
                    'references' => $rows->pluck('issue_reference')->filter()->values(),
                ];
            })
            ->filter(fn (array $row) => $row['unsold_quantity'] > 0.0001 || $row['sold_pending_reconciliation'] > 0.0001)
            ->sortBy([
                fn (array $row) => $row['warehouse_name'],
                fn (array $row) => $row['agent_name'],
                fn (array $row) => $row['item_name'],
            ])
            ->values();
        $outsideSummaryCards = [
            ['label' => 'Units Still With Agents', 'value' => number_format((float) $outsideStockRows->sum('unsold_quantity'), 2)],
            ['label' => 'Sold Pending Reconciliation', 'value' => number_format((float) $outsideStockRows->sum('sold_pending_reconciliation'), 2)],
            ['label' => 'Issued Outside', 'value' => number_format((float) $outsideStockRows->sum('issued_quantity'), 2)],
            ['label' => 'Open Outside Lines', 'value' => number_format($outsideStockRows->count())],
        ];

        return view('gondal.warehouse', [
            'tab' => $tab,
            'visibleTabs' => $this->visibleModuleTabs($request, 'warehouse-ops'),
            'warehouses' => $warehouses,
            'warehouseStocks' => $warehouseStocks,
            'dispatches' => $dispatches,
            'outsideStockRows' => $outsideStockRows,
            'outsideSummaryCards' => $outsideSummaryCards,
            'items' => InventoryItem::query()->orderBy('name')->get(),
            'agents' => AgentProfile::query()->with(['user', 'vender'])->orderBy('agent_code')->get(),
            'summaryCards' => [
                ['label' => 'Total Warehouses', 'value' => number_format($warehouses->count())],
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

        $issue = $this->createStockIssueFromRequest($request);

        $this->writeAuditLog($request, 'warehouse-ops', 'warehouse_dispatch_created', ['stock_issue_id' => $issue->id]);

        return redirect()->route('gondal.warehouse', ['tab' => 'dispatches'])->with('success', __('Stock issued from warehouse successfully.'));
    }

    public function extension(Request $request)
    {
        $tab = $this->resolveModuleTab($request, 'extension', 'agents');

        $visits = ExtensionVisit::query()->with('farmer')->orderByDesc('visit_date')->get();
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
            'farmers' => Vender::query()->orderBy('name')->get(),
        ]);
    }

    public function storeExtensionVisit(Request $request): RedirectResponse
    {
        $tab = $this->requireActionTab($request, 'extension', 'visits', true);
        $this->requireModulePermission($request, 'extension', $tab, 'create');

        $payload = $this->validateExtensionVisitPayload($request);
        $visit = ExtensionVisit::query()->create($payload);
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
            'fat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'snf_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grade' => ['required', 'in:A,B,C'],
            'adulteration_test' => ['required', 'in:passed,failed'],
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
            'officer_name' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:255'],
            'performance_score' => ['required', 'integer', 'between:0,100'],
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

        $collections = MilkCollection::query()->with('farmer.cooperative')->get();
        $trips = LogisticsTrip::query()->with(['rider', 'cooperative'])->get();
        $costs = OperationCost::query()->with('cooperative')->get();
        $requisitions = Requisition::query()->with(['requester', 'cooperative'])->latest()->get();
        $paymentBatches = PaymentBatch::query()->latest('period_end')->get();
        $items = InventoryItem::query()->orderBy('name')->get();
        $credits = InventoryCredit::query()->with(['item', 'agentProfile.user', 'agentProfile.vender'])->orderByDesc('credit_date')->get();
        $stockIssues = StockIssue::query()->with(['item', 'agentProfile.user', 'agentProfile.vender'])->orderByDesc('issued_on')->get();
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

    protected function createStockIssueFromRequest(Request $request): StockIssue
    {
        $payload = $request->validate([
            'agent_profile_id' => ['required', 'exists:gondal_agent_profiles,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'inventory_item_id' => ['required', 'exists:gondal_inventory_items,id'],
            'batch_reference' => ['nullable', 'string', 'max:255'],
            'quantity_issued' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'issued_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::query()->findOrFail($payload['inventory_item_id']);
        $warehouseStock = WarehouseStock::query()
            ->where('warehouse_id', $payload['warehouse_id'])
            ->where('inventory_item_id', $payload['inventory_item_id'])
            ->first();

        if (! $warehouseStock) {
            throw ValidationException::withMessages([
                'warehouse_id' => [__('The selected warehouse does not hold this product.')],
            ]);
        }

        if ((float) $payload['quantity_issued'] > (float) $warehouseStock->quantity || (float) $payload['quantity_issued'] > (float) $item->stock_qty) {
            throw ValidationException::withMessages([
                'quantity_issued' => [__('Issued quantity exceeds available warehouse stock.')],
            ]);
        }

        $issue = StockIssue::query()->create([
            'agent_profile_id' => $payload['agent_profile_id'],
            'warehouse_id' => $payload['warehouse_id'],
            'inventory_item_id' => $payload['inventory_item_id'],
            'issued_by' => $request->user()->id,
            'issue_reference' => $this->generateInventoryReference('ISS'),
            'batch_reference' => $payload['batch_reference'] ?? null,
            'quantity_issued' => $payload['quantity_issued'],
            'unit_cost' => $payload['unit_cost'],
            'issued_on' => $payload['issued_on'],
            'notes' => $payload['notes'] ?? null,
        ]);

        $item->update([
            'stock_qty' => (float) $item->stock_qty - (float) $payload['quantity_issued'],
        ]);
        $warehouseStock->update([
            'quantity' => (float) $warehouseStock->quantity - (float) $payload['quantity_issued'],
        ]);

        return $issue;
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
