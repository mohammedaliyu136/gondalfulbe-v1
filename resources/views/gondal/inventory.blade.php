@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
    use Illuminate\Support\Str;

    $tabMeta = [
        'stock' => ['label' => 'Product Catalog', 'icon' => 'ti ti-box'],
        'sales' => ['label' => 'Sales Entry', 'icon' => 'ti ti-receipt'],
        'credit' => ['label' => 'Credit Tracking', 'icon' => 'ti ti-credit-card'],
        'agents' => ['label' => 'Agents', 'icon' => 'ti ti-users'],
        'issues' => ['label' => 'Stock Issues', 'icon' => 'ti ti-package-export'],
        'remittances' => ['label' => 'Remittances', 'icon' => 'ti ti-cash-banknote'],
        'reconciliation' => ['label' => 'Reconciliation', 'icon' => 'ti ti-scale'],
    ];
    $activeTabTitle = $tabMeta[$tab]['label'] ?? 'Inventory';
@endphp

@push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const locationHierarchy = @json($agentLocationHierarchy ?? []);
            const stateSelect = document.querySelector('#agentState');
            const lgaSelect = document.querySelector('#agentLga');
            const communitySelect = document.querySelector('#agentCommunity');
            const assignedCommunitiesSelect = document.querySelector('#agentAssignedCommunities');
            const agentModal = document.getElementById('createAgentModal');
            const agentForm = document.getElementById('createAgentForm');
            const agentSteps = Array.from(document.querySelectorAll('.agent-form-step'));
            const agentIndicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
            const prevStepButton = document.getElementById('agentWizardPrev');
            const nextStepButton = document.getElementById('agentWizardNext');
            const submitStepButton = document.getElementById('agentWizardSubmit');
            let activeAgentStep = 0;

            if (!stateSelect || !lgaSelect || !communitySelect || !assignedCommunitiesSelect) {
                return;
            }

            const renderOptions = (select, values, placeholder, selectedValues = []) => {
                const normalizedSelected = selectedValues.map(String);
                select.innerHTML = '';

                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                if (!select.multiple) {
                    placeholderOption.selected = normalizedSelected.length === 0;
                }
                select.appendChild(placeholderOption);

                values.forEach((value) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    option.selected = normalizedSelected.includes(String(value));
                    select.appendChild(option);
                });
            };

            const syncAssignedCommunitySelection = () => {
                const selectedCommunity = communitySelect.value;
                if (! selectedCommunity) {
                    return;
                }

                const selectedValues = new Set(
                    Array.from(assignedCommunitiesSelect.selectedOptions).map((option) => option.value)
                );

                selectedValues.add(selectedCommunity);

                Array.from(assignedCommunitiesSelect.options).forEach((option) => {
                    if (option.value === '') {
                        option.selected = false;
                        return;
                    }

                    option.selected = selectedValues.has(option.value);
                });
            };

            const syncLocationOptions = () => {
                const selectedState = stateSelect.value;
                const stateData = locationHierarchy[selectedState] || {};
                const lgas = Object.keys(stateData);
                const keepLga = lgas.includes(lgaSelect.value) ? lgaSelect.value : '';

                renderOptions(lgaSelect, lgas, '{{ __('Select LGA') }}', keepLga ? [keepLga] : []);

                const communities = keepLga ? (stateData[keepLga] || []) : [];
                const keepCommunity = communities.includes(communitySelect.value) ? communitySelect.value : '';
                const keepAssigned = Array.from(assignedCommunitiesSelect.selectedOptions)
                    .map((option) => option.value)
                    .filter((value) => communities.includes(value));

                renderOptions(communitySelect, communities, '{{ __('Select community') }}', keepCommunity ? [keepCommunity] : []);
                renderOptions(assignedCommunitiesSelect, communities, '{{ __('Select communities') }}', keepAssigned);
                syncAssignedCommunitySelection();
            };

            stateSelect.addEventListener('change', syncLocationOptions);
            lgaSelect.addEventListener('change', syncLocationOptions);
            communitySelect.addEventListener('change', syncAssignedCommunitySelection);
            syncLocationOptions();

            const syncAgentStepUi = () => {
                agentSteps.forEach((step, index) => {
                    step.classList.toggle('d-none', index !== activeAgentStep);
                });

                agentIndicators.forEach((indicator, index) => {
                    indicator.classList.toggle('bg-primary-subtle', index === activeAgentStep);
                    indicator.classList.toggle('text-primary', index === activeAgentStep);
                    indicator.classList.toggle('border-primary-subtle', index === activeAgentStep);
                    indicator.classList.toggle('bg-light', index !== activeAgentStep);
                    indicator.classList.toggle('text-muted', index !== activeAgentStep);
                });

                if (prevStepButton) {
                    prevStepButton.classList.toggle('d-none', activeAgentStep === 0);
                }

                if (nextStepButton) {
                    nextStepButton.classList.toggle('d-none', activeAgentStep === agentSteps.length - 1);
                }

                if (submitStepButton) {
                    submitStepButton.classList.toggle('d-none', activeAgentStep !== agentSteps.length - 1);
                }

                const modalBody = agentModal?.querySelector('.modal-body');
                if (modalBody) {
                    modalBody.scrollTop = 0;
                }
            };

            const validateAgentStep = (stepIndex) => {
                const currentStep = agentSteps[stepIndex];
                if (!currentStep) {
                    return true;
                }

                const fields = Array.from(currentStep.querySelectorAll('input, select, textarea'));
                for (const field of fields) {
                    if (!field.checkValidity()) {
                        field.reportValidity();
                        return false;
                    }
                }

                return true;
            };

            nextStepButton?.addEventListener('click', function () {
                if (!validateAgentStep(activeAgentStep)) {
                    return;
                }

                if (activeAgentStep < agentSteps.length - 1) {
                    activeAgentStep += 1;
                    syncAgentStepUi();
                }
            });

            prevStepButton?.addEventListener('click', function () {
                if (activeAgentStep > 0) {
                    activeAgentStep -= 1;
                    syncAgentStepUi();
                }
            });

            agentIndicators.forEach((indicator, index) => {
                indicator.addEventListener('click', function () {
                    if (index <= activeAgentStep) {
                        activeAgentStep = index;
                        syncAgentStepUi();
                    }
                });
            });

            agentModal?.addEventListener('shown.bs.modal', function () {
                activeAgentStep = 0;
                syncAgentStepUi();
            });

            agentModal?.addEventListener('hidden.bs.modal', function () {
                agentForm?.reset();
            });

            agentForm?.addEventListener('reset', function () {
                activeAgentStep = 0;
                window.setTimeout(function () {
                    syncLocationOptions();
                    syncAgentStepUi();
                }, 0);
            });

            agentForm?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && activeAgentStep < agentSteps.length - 1) {
                    event.preventDefault();
                    nextStepButton?.click();
                }
            });

            syncAgentStepUi();
        });
    </script>
@endpush

@section('page-title')
    {{ __('Inventory (One-Stop Shop)') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Inventory') }}</li>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="row">
        @foreach ($summaryCards as $card)
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-body">
                        <small class="text-muted fw-bold text-uppercase">{{ __($card['label']) }}</small>
                        <h4 class="mb-0 mt-2 text-dark">{{ $card['value'] }}</h4>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Active Agents') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($agentKpis['agents']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Units Issued') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($agentKpis['stock_issued'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Remitted Value') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">₦{{ number_format($agentKpis['remitted'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Open Variances') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($agentKpis['open_variances']) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
            <ul class="nav nav-pills gap-2 flex-wrap" id="inventoryTabs" role="tablist">
                @foreach ($visibleTabs as $visibleTab)
                    @php
                        $meta = $tabMeta[$visibleTab['key']] ?? ['label' => $visibleTab['label'], 'icon' => 'ti ti-layout-grid'];
                    @endphp
                    <li class="nav-item">
                        <a href="{{ route('gondal.inventory', ['tab' => $visibleTab['key']]) }}" class="nav-link {{ $tab === $visibleTab['key'] ? 'active' : '' }}">
                            <i class="{{ $meta['icon'] }} me-1"></i> {{ __($meta['label']) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h5 class="mb-1 text-dark">{{ __($activeTabTitle) }}</h5>
                    @if ($tab === 'agents')
                        <p class="text-muted mb-0">{{ __('Manage who sells on behalf of the company and how each agent settles stock and credit.') }}</p>
                    @elseif ($tab === 'issues')
                        <p class="text-muted mb-0">{{ __('Issue warehouse stock to field agents with batch references and traceable custody.') }}</p>
                    @elseif ($tab === 'remittances')
                        <p class="text-muted mb-0">{{ __('Track how much each agent has returned against daily, weekly, or batch settlement periods.') }}</p>
                    @elseif ($tab === 'reconciliation')
                        <p class="text-muted mb-0">{{ __('See, per agent, what stock should be on hand, what cash should have been remitted today, and where shortages or overages exist.') }}</p>
                    @endif
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if ($tab === 'sales' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'sales', 'create'))
                        <button class="btn lovable-btn shadow-sm" data-bs-toggle="modal" data-bs-target="#recordSaleModal">
                            <i class="ti ti-plus me-1"></i> {{ __('New Sale') }}
                        </button>
                    @endif
                    @if ($tab === 'stock' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'stock', 'create'))
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createItemModal">
                            <i class="ti ti-box me-1"></i> {{ __('Add Product') }}
                        </button>
                    @endif
                    @if ($tab === 'credit' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'credit', 'create'))
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createCreditModal">
                            <i class="ti ti-plus me-1"></i> {{ __('Manual Credit Entry') }}
                        </button>
                    @endif
                    @if ($tab === 'agents' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'agents', 'create'))
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createAgentModal">
                            <i class="ti ti-user-plus me-1"></i> {{ __('Add Agent') }}
                        </button>
                    @endif
                    @if ($tab === 'issues' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'issues', 'create'))
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createIssueModal">
                            <i class="ti ti-package-export me-1"></i> {{ __('Issue Stock') }}
                        </button>
                    @endif
                    @if ($tab === 'remittances' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'remittances', 'create'))
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createRemittanceModal">
                            <i class="ti ti-cash-banknote me-1"></i> {{ __('Record Remittance') }}
                        </button>
                    @endif
                    @if ($tab === 'reconciliation' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'reconciliation', 'create'))
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createReconciliationModal">
                            <i class="ti ti-scale me-1"></i> {{ __('New Reconciliation') }}
                        </button>
                    @endif
                </div>
            </div>

            <div class="table-border-style">
                @if ($tab === 'reconciliation')
                    <div class="row mb-4">
                        @foreach ($reconciliationWorkflowCards as $card)
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                {{ $card['step'] }}
                                            </div>
                                            <div>
                                                <div class="fw-bold">{{ $card['title'] }}</div>
                                                <div class="small text-muted">{{ $card['text'] }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="row mb-4">
                        @foreach ($reconciliationSummaryCards as $card)
                            <div class="col-md-3">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <small class="text-muted fw-bold text-uppercase">{{ __($card['label']) }}</small>
                                        <h5 class="mb-0 mt-2 text-dark">{{ $card['value'] }}</h5>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="alert alert-light border mb-4">
                        <strong>{{ __('How to read this') }}:</strong>
                        {{ __('Expected Cash Today = cash sales + transfer sales. Credit sales are shown separately as receivables and are not counted as cash shortage until collected.') }}
                    </div>
                @endif
                <div class="table-responsive">
                    @if ($tab === 'reconciliation')
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="fw-bold mb-1">{{ __('Submitted Reconciliation Snapshots') }}</h6>
                                <p class="text-muted small mb-0">{{ __('Saved reconciliation records used for audit trail, review, and approval.') }}</p>
                            </div>
                            <div class="card-body pt-3">
                                <table class="table datatable table-hover align-middle mb-0">
                        @else
                    <table class="table datatable table-hover align-middle">
                    @endif
                        @if ($tab === 'stock')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('SKU') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Unit') }}</th>
                                    <th>{{ __('Price (₦)') }}</th>
                                    <th>{{ __('Company Stock') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td><span class="text-muted">{{ $item->sku }}</span></td>
                                        <td class="fw-bold">{{ $item->name }}</td>
                                        <td>{{ $item->category ?: '-' }}</td>
                                        <td>{{ $item->unit ?: '-' }}</td>
                                        <td>{{ number_format($item->unit_price, 2) }}</td>
                                        <td>{{ number_format($item->stock_qty, 2) }}</td>
                                        <td>
                                            @if($item->stock_qty > 10)
                                                <span class="badge bg-success rounded-pill">{{ __('In Stock') }}</span>
                                            @elseif($item->stock_qty > 0)
                                                <span class="badge bg-warning rounded-pill">{{ __('Low Stock') }}</span>
                                            @else
                                                <span class="badge bg-danger rounded-pill">{{ __('Out of Stock') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'sales')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Agent') }}</th>
                                    <th>{{ __('Buyer') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Payment') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sales as $sale)
                                    <tr>
                                        <td>{{ optional($sale->sold_on)->toDateString() }}</td>
                                        <td>{{ $sale->agentProfile?->outlet_name ?: $sale->agentProfile?->user?->name ?: $sale->agentProfile?->vender?->name ?: __('Direct Sale') }}</td>
                                        <td class="fw-bold">{{ $sale->vender?->name ?: $sale->customer_name ?: '-' }}</td>
                                        <td>{{ $sale->item?->name ?: 'N/A' }}</td>
                                        <td>{{ number_format($sale->quantity, 2) }} {{ $sale->item?->unit }}</td>
                                        <td>₦{{ number_format($sale->total_amount ?: ($sale->quantity * $sale->unit_price), 2) }}</td>
                                        <td>
                                            <span class="badge {{ in_array($sale->payment_method, ['Credit', 'Milk Collection Balance']) ? 'bg-danger' : ($sale->payment_method === 'Transfer' ? 'bg-primary' : 'bg-success') }} rounded-pill px-3">
                                                {{ __($sale->payment_method) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'credit')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Agent') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Outstanding (₦)') }}</th>
                                    <th>{{ __('Due Date') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($credits as $credit)
                                    <tr>
                                        <td class="fw-bold">{{ $credit->vender?->name ?: $credit->customer_name }}</td>
                                        <td>{{ $credit->agentProfile?->outlet_name ?: $credit->agentProfile?->user?->name ?: $credit->agentProfile?->vender?->name ?: '-' }}</td>
                                        <td>{{ $credit->item?->name ?: '-' }}</td>
                                        <td class="text-danger">₦{{ number_format($credit->outstanding_amount ?: $credit->amount, 2) }}</td>
                                        <td>{{ optional($credit->due_date)->toDateString() ?: '-' }}</td>
                                        <td><span class="badge bg-light text-dark rounded-pill">{{ __(Str::headline($credit->status)) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'agents')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Agent Code') }}</th>
                                    <th>{{ __('Identity') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Outlet') }}</th>
                                    <th>{{ __('Credit') }}</th>
                                    <th>{{ __('Supervisor') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($agentProfiles as $agent)
                                    <tr>
                                        <td class="fw-bold">{{ $agent->agent_code }}</td>
                                        <td>
                                            <div>{{ $agent->full_name ?: ($agent->user?->name ?: $agent->vender?->name ?: __('Unlinked Agent')) }}</div>
                                            <small class="text-muted">{{ $agent->phone_number ?: ($agent->email ?: __('No contact saved')) }}</small>
                                        </td>
                                        <td>{{ __(Str::headline(str_replace('_', ' ', $agent->agent_type))) }}</td>
                                        <td>
                                            <div>{{ $agent->outlet_name ?: '-' }}</div>
                                            <small class="text-muted">
                                                {{ $agent->communityRecord?->name ?: ($agent->community ?: __('No primary community')) }}{{ !empty($agent->assigned_communities) ? ' · '.implode(', ', $agent->assigned_communities) : '' }}
                                            </small>
                                        </td>
                                        <td>
                                            <div>{{ $agent->credit_sales_enabled ? __('Enabled') : __('Disabled') }}</div>
                                            <small class="text-muted">₦{{ number_format($creditExposureByAgent[$agent->id] ?? 0, 2) }} / ₦{{ number_format($agent->credit_limit, 2) }}</small>
                                        </td>
                                        <td>{{ $agent->supervisor?->name ?: '-' }}</td>
                                        <td><span class="badge {{ $agent->status === 'active' ? 'bg-success' : ($agent->status === 'suspended' ? 'bg-danger' : 'bg-secondary') }} rounded-pill">{{ __(Str::headline($agent->status)) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'issues')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Agent') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Unit Cost') }}</th>
                                    <th>{{ __('Issued On') }}</th>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('Batch') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stockIssues as $issue)
                                    <tr>
                                        <td class="fw-bold">{{ $issue->issue_reference }}</td>
                                        <td>{{ $issue->agentProfile?->outlet_name ?: $issue->agentProfile?->user?->name ?: $issue->agentProfile?->vender?->name ?: '-' }}</td>
                                        <td>{{ $issue->item?->name ?: '-' }}</td>
                                        <td>{{ number_format($issue->quantity_issued, 2) }}</td>
                                        <td>₦{{ number_format($issue->unit_cost, 2) }}</td>
                                        <td>{{ optional($issue->issued_on)->toDateString() }}</td>
                                        <td>{{ $issue->warehouse?->name ?: '-' }}</td>
                                        <td>{{ $issue->batch_reference ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'remittances')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Agent') }}</th>
                                    <th>{{ __('Mode') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Payment Method') }}</th>
                                    <th>{{ __('Period') }}</th>
                                    <th>{{ __('Received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($remittances as $remittance)
                                    <tr>
                                        <td class="fw-bold">{{ $remittance->reference }}</td>
                                        <td>{{ $remittance->agentProfile?->outlet_name ?: $remittance->agentProfile?->user?->name ?: $remittance->agentProfile?->vender?->name ?: '-' }}</td>
                                        <td>{{ __(Str::headline($remittance->reconciliation_mode)) }}</td>
                                        <td>₦{{ number_format($remittance->amount, 2) }}</td>
                                        <td>{{ __(Str::headline(str_replace('_', ' ', $remittance->payment_method))) }}</td>
                                        <td>{{ optional($remittance->period_start)->toDateString() ?: '-' }} - {{ optional($remittance->period_end)->toDateString() ?: '-' }}</td>
                                        <td>{{ optional($remittance->remitted_at)->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'reconciliation')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Agent') }}</th>
                                    <th>{{ __('Period') }}</th>
                                    <th>{{ __('Cash Gap') }}</th>
                                    <th>{{ __('Stock Gap') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reconciliations as $reconciliation)
                                    <tr>
                                        <td class="fw-bold">{{ $reconciliation->reference }}</td>
                                        <td>{{ $reconciliation->agentProfile?->outlet_name ?: $reconciliation->agentProfile?->user?->name ?: $reconciliation->agentProfile?->vender?->name ?: '-' }}</td>
                                        <td>{{ optional($reconciliation->period_start)->toDateString() }} - {{ optional($reconciliation->period_end)->toDateString() }}</td>
                                        <td class="{{ $reconciliation->cash_variance_amount < 0 ? 'text-danger' : 'text-success' }}">
                                            ₦{{ number_format($reconciliation->cash_variance_amount, 2) }}
                                        </td>
                                        <td class="{{ $reconciliation->stock_variance_qty < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($reconciliation->stock_variance_qty, 2) }}
                                        </td>
                                        <td><span class="badge {{ in_array($reconciliation->status, ['submitted', 'approved'], true) ? 'bg-success' : 'bg-warning text-dark' }} rounded-pill">{{ __(Str::headline(str_replace('_', ' ', $reconciliation->status))) }}</span></td>
                                        <td>
                                            <a href="{{ route('gondal.inventory.reconciliations.show', $reconciliation->id) }}" class="btn btn-sm btn-light border">
                                                {{ __('Details') }}
                                            </a>
                                            @if (GondalPermissionRegistry::can(auth()->user(), 'inventory', 'reconciliation', 'edit'))
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resolveReconciliationModal{{ $reconciliation->id }}">
                                                    {{ __('Resolve') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                    </table>
                    @if ($tab === 'reconciliation')
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="recordSaleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('gondal.inventory.sales.store') }}">
                    @csrf
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold">{{ __('Record Sale') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body" style="max-height: calc(100vh - 210px); overflow-y: auto;">
                        <div class="mb-3">
                            <label class="form-label text-muted">{{ __('Selling Agent') }}</label>
                            <select class="form-select" name="agent_profile_id">
                                <option value="">{{ __('Direct warehouse sale') }}</option>
                                @foreach ($agentProfiles as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_code }} - {{ $agent->full_name ?: ($agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">{{ __('Buyer / Farmer') }}</label>
                            <select class="form-select" name="vender_id">
                                <option value="">{{ __('Walk-in / unnamed customer') }}</option>
                                @foreach ($farmers as $farmer)
                                    <option value="{{ $farmer->id }}">{{ $farmer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">{{ __('Customer Name Override') }}</label>
                            <input type="text" class="form-control" name="customer_name" placeholder="{{ __('Used when the buyer is not in farmer records') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">{{ __('Select product') }}</label>
                            <select class="form-select" name="inventory_item_id" id="saleInventoryItem" required>
                                <option value="" disabled selected>{{ __('-- Select a Product --') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}" data-price="{{ $item->unit_price }}">{{ $item->name }} (₦{{ number_format($item->unit_price, 2) }} - {{ number_format($item->stock_qty, 2) }} {{ $item->unit }} {{ __('available') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label text-muted">{{ __('Quantity') }}</label>
                                <input type="number" step="0.01" class="form-control" name="quantity" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label text-muted">{{ __('Unit Price') }}</label>
                                <input type="number" step="0.01" class="form-control" id="saleUnitPrice" name="unit_price" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label text-muted">{{ __('Payment Method') }}</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="Cash">{{ __('Cash') }}</option>
                                    <option value="Credit">{{ __('Credit') }}</option>
                                    <option value="Transfer">{{ __('Transfer') }}</option>
                                    <option value="Milk Collection Balance">{{ __('Milk Collection Balance') }}</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label text-muted">{{ __('Receivable Due Date') }}</label>
                                <input type="date" class="form-control" name="due_date">
                            </div>
                        </div>
                        <input type="hidden" name="sold_on" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn lovable-btn w-50">{{ __('Record Sale') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.inventory.items.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Product') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Product Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Category') }}</label>
                                <select class="form-select" name="category">
                                    <option value="Feed">{{ __('Feed') }}</option>
                                    <option value="Veterinary">{{ __('Veterinary') }}</option>
                                    <option value="Equipment">{{ __('Equipment') }}</option>
                                    <option value="Consumables">{{ __('Consumables') }}</option>
                                    <option value="Other">{{ __('Other') }}</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Unit') }}</label>
                                <select class="form-select" name="unit">
                                    <option value="bag">{{ __('bag') }}</option>
                                    <option value="bottle">{{ __('bottle') }}</option>
                                    <option value="pack">{{ __('pack') }}</option>
                                    <option value="piece">{{ __('piece') }}</option>
                                    <option value="kit">{{ __('kit') }}</option>
                                    <option value="kg">{{ __('kg') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Initial Stock') }}</label>
                                <input type="number" step="0.01" class="form-control" name="stock_qty" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Unit Price (₦)') }}</label>
                                <input type="number" step="0.01" class="form-control" name="unit_price" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select class="form-select" name="status" required>
                                <option value="active">{{ __('Active') }}</option>
                                <option value="inactive">{{ __('Inactive') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Save Product') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createCreditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.inventory.credits.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Manual Credit Adjustment') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Agent') }}</label>
                            <select class="form-select" name="agent_profile_id">
                                <option value="">{{ __('No specific agent') }}</option>
                                @foreach ($agentProfiles as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_code }} - {{ $agent->full_name ?: ($agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Farmer') }}</label>
                            <select class="form-select" name="vender_id">
                                <option value="">{{ __('No farmer linked') }}</option>
                                @foreach ($farmers as $farmer)
                                    <option value="{{ $farmer->id }}">{{ $farmer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Customer Name') }}</label>
                            <input type="text" class="form-control" name="customer_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Amount (₦)') }}</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Date') }}</label>
                                <input type="date" class="form-control" name="credit_date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Due Date') }}</label>
                                <input type="date" class="form-control" name="due_date">
                            </div>
                        </div>
                        <input type="hidden" name="status" value="open">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Related Product') }}</label>
                            <select class="form-select" name="inventory_item_id" required>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Save Credit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createAgentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.inventory.agents.store') }}" id="createAgentForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Agent Profile') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="max-height: calc(100vh - 210px); overflow-y: auto;">
                        <div class="d-flex flex-wrap gap-2 mb-3" id="agentWizardSteps">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" data-step-indicator="0">{{ __('1. Bio') }}</span>
                            <span class="badge bg-light text-muted border px-3 py-2" data-step-indicator="1">{{ __('2. Location') }}</span>
                            <span class="badge bg-light text-muted border px-3 py-2" data-step-indicator="2">{{ __('3. Operations') }}</span>
                            <span class="badge bg-light text-muted border px-3 py-2" data-step-indicator="3">{{ __('4. Banking') }}</span>
                        </div>

                        <div class="border rounded-3 p-3 mb-3 agent-form-step" data-step="0">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div class="fw-semibold text-dark">{{ __('Bio') }}</div>
                                <small class="text-muted">{{ __('Identity and contact details') }}</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('First Name') }}</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Middle Name') }}</label>
                                    <input type="text" class="form-control" name="middle_name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Last Name') }}</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                            </div>
                            <div class="row g-3 mt-0">
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Gender') }}</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="male">{{ __('Male') }}</option>
                                        <option value="female">{{ __('Female') }}</option>
                                        <option value="other">{{ __('Other') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Phone Number') }}</label>
                                    <input type="text" class="form-control" name="phone_number" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Email Address') }}</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="row g-3 mt-0">
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('NIN') }}</label>
                                    <input type="text" class="form-control" name="nin">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">{{ __('Internal User') }}</label>
                                    <select class="form-select" name="user_id" required>
                                        <option value="">{{ __('Select user') }}</option>
                                        @foreach ($internalUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->type }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-3 p-3 mb-3 agent-form-step d-none" data-step="1">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div class="fw-semibold text-dark">{{ __('Location') }}</div>
                                <small class="text-muted">{{ __('Primary posting and community coverage') }}</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('State') }}</label>
                                    <select class="form-select" id="agentState" name="state" required>
                                        <option value="">{{ __('Select state') }}</option>
                                        @foreach ($agentStateOptions as $state)
                                            <option value="{{ $state }}">{{ $state }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('LGA') }}</label>
                                    <select class="form-select" id="agentLga" name="lga" required>
                                        <option value="">{{ __('Select LGA') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Community') }}</label>
                                    <select class="form-select" id="agentCommunity" name="community" required>
                                        <option value="">{{ __('Select community') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mt-0">
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Residential Address') }}</label>
                                    <textarea class="form-control" name="residential_address" rows="2" required></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Permanent Address') }}</label>
                                    <textarea class="form-control" name="permanent_address" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">{{ __('Assigned Communities') }}</label>
                                <select class="form-select" id="agentAssignedCommunities" name="assigned_communities[]" multiple required>
                                </select>
                                <small class="text-muted">{{ __('This list updates from the selected state and LGA.') }}</small>
                            </div>
                        </div>

                        <div class="border rounded-3 p-3 mb-3 agent-form-step d-none" data-step="2">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div class="fw-semibold text-dark">{{ __('Operations') }}</div>
                                <small class="text-muted">{{ __('Role, supervision, and settlement setup') }}</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Agent Type') }}</label>
                                    <select class="form-select" name="agent_type" required>
                                        <option value="employee">{{ __('Employee') }}</option>
                                        <option value="farmer">{{ __('Farmer') }}</option>
                                        <option value="independent_reseller">{{ __('Independent Reseller') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Outlet Name') }}</label>
                                    <input type="text" class="form-control" name="outlet_name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Assigned One-Stop Shop') }}</label>
                                    <select class="form-select" name="one_stop_shop_id" required>
                                        <option value="">{{ __('Select one-stop shop') }}</option>
                                        @foreach ($oneStopShops as $shop)
                                            <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mt-0">
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Reconciliation Frequency') }}</label>
                                    <select class="form-select" name="reconciliation_frequency" required>
                                        <option value="daily">{{ __('Daily') }}</option>
                                        <option value="weekly">{{ __('Weekly') }}</option>
                                        <option value="batch">{{ __('Batch') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Settlement Mode') }}</label>
                                    <select class="form-select" name="settlement_mode" required>
                                        <option value="consignment">{{ __('Consignment') }}</option>
                                        <option value="outright_purchase">{{ __('Outright Purchase') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Supervisor') }}</label>
                                    <select class="form-select" name="supervisor_user_id" required>
                                        <option value="">{{ __('Select supervisor') }}</option>
                                        @foreach ($supervisors as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mt-0">
                                <div class="col-md-12">
                                    <label class="form-label">{{ __('Status') }}</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active">{{ __('Active') }}</option>
                                        <option value="inactive">{{ __('Inactive') }}</option>
                                        <option value="suspended">{{ __('Suspended') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-3 p-3 agent-form-step d-none" data-step="3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div class="fw-semibold text-dark">{{ __('Banking') }}</div>
                                <small class="text-muted">{{ __('Payout account details') }}</small>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Account Number') }}</label>
                                    <input type="text" class="form-control" name="account_number">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Account Name') }}</label>
                                    <input type="text" class="form-control" name="account_name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Bank Details') }}</label>
                                    <input type="text" class="form-control" name="bank_details" placeholder="{{ __('Bank name, branch, or payment notes') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="agentWizardPrev">{{ __('Back') }}</button>
                        <button type="button" class="btn btn-primary" id="agentWizardNext">{{ __('Next') }}</button>
                        <button class="btn btn-primary d-none" id="agentWizardSubmit">{{ __('Create Agent') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createIssueModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.inventory.issues.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Issue Stock From One-Stop Shop To Agent') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('One-Stop Shop') }}</label>
                            <select class="form-select" name="one_stop_shop_id" required>
                                @foreach ($oneStopShops as $shop)
                                    <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Agent') }}</label>
                            <select class="form-select" name="agent_profile_id" required>
                                @foreach ($agentProfiles as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_code }} - {{ $agent->full_name ?: ($agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name) }}{{ $agent->oneStopShop?->name ? ' · '.$agent->oneStopShop->name : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Product') }}</label>
                            <select class="form-select" name="inventory_item_id" required>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ number_format($item->stock_qty, 2) }} {{ $item->unit }} {{ __('available') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Quantity Issued') }}</label>
                                <input type="number" step="0.01" class="form-control" name="quantity_issued" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Unit Cost (₦)') }}</label>
                                <input type="number" step="0.01" class="form-control" name="unit_cost" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Issued On') }}</label>
                                <input type="date" class="form-control" name="issued_on" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Batch Reference') }}</label>
                                <input type="text" class="form-control" name="batch_reference">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Issue Stock') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createRemittanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.inventory.remittances.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Record Agent Remittance') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Agent') }}</label>
                            <select class="form-select" name="agent_profile_id" required>
                                @foreach ($agentProfiles as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_code }} - {{ $agent->full_name ?: ($agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Mode') }}</label>
                                <select class="form-select" name="reconciliation_mode" required>
                                    <option value="daily">{{ __('Daily') }}</option>
                                    <option value="weekly">{{ __('Weekly') }}</option>
                                    <option value="batch">{{ __('Batch') }}</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Payment Method') }}</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="transfer">{{ __('Transfer') }}</option>
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="pos">{{ __('POS') }}</option>
                                    <option value="bank_deposit">{{ __('Bank Deposit') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Amount (₦)') }}</label>
                                <input type="number" step="0.01" class="form-control" name="amount" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Remitted On') }}</label>
                                <input type="date" class="form-control" name="remitted_at" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Period Start') }}</label>
                                <input type="date" class="form-control" name="period_start">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Period End') }}</label>
                                <input type="date" class="form-control" name="period_end">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Save Remittance') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createReconciliationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.inventory.reconciliations.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Reconciliation Snapshot') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Agent') }}</label>
                            <select class="form-select" name="agent_profile_id" required>
                                @foreach ($agentProfiles as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_code }} - {{ $agent->full_name ?: ($agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Product') }}</label>
                            <select class="form-select" name="inventory_item_id" required>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-4 mb-3">
                                <label class="form-label">{{ __('Mode') }}</label>
                                <select class="form-select" name="reconciliation_mode" required>
                                    <option value="daily">{{ __('Daily') }}</option>
                                    <option value="weekly">{{ __('Weekly') }}</option>
                                    <option value="batch">{{ __('Batch') }}</option>
                                </select>
                            </div>
                            <div class="col-4 mb-3">
                                <label class="form-label">{{ __('Period Start') }}</label>
                                <input type="date" class="form-control" name="period_start" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-4 mb-3">
                                <label class="form-label">{{ __('Period End') }}</label>
                                <input type="date" class="form-control" name="period_end" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Counted Stock Quantity') }}</label>
                            <input type="number" step="0.01" class="form-control" name="counted_stock_qty" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Agent Notes') }}</label>
                            <textarea class="form-control" name="agent_notes" rows="3" placeholder="{{ __('Explain shortages, damages, pending collections, or any exceptions.') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Generate Snapshot') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($tab === 'reconciliation' && GondalPermissionRegistry::can(auth()->user(), 'inventory', 'reconciliation', 'edit'))
        @foreach ($reconciliations as $reconciliation)
            <div class="modal fade" id="resolveReconciliationModal{{ $reconciliation->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('gondal.inventory.reconciliations.resolve', $reconciliation->id) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">{{ __('Resolve Reconciliation') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <div class="small text-muted">{{ __('Agent') }}</div>
                                    <div class="fw-bold">{{ $reconciliation->agentProfile?->outlet_name ?: $reconciliation->agentProfile?->user?->name ?: $reconciliation->agentProfile?->vender?->name ?: '-' }}</div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="small text-muted">{{ __('Expected Cash') }}</div>
                                        <div class="fw-bold">₦{{ number_format($reconciliation->expected_cash_amount, 2) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted">{{ __('Remitted Cash') }}</div>
                                        <div class="fw-bold">₦{{ number_format($reconciliation->remitted_cash_amount, 2) }}</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="small text-muted">{{ __('Expected Stock') }}</div>
                                        <div class="fw-bold">{{ number_format($reconciliation->expected_stock_qty, 2) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted">{{ __('Counted Stock') }}</div>
                                        <div class="fw-bold">{{ number_format($reconciliation->counted_stock_qty, 2) }}</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Resolution Action') }}</label>
                                    <select class="form-select" name="action" required>
                                        <option value="approve">{{ __('Approve') }}</option>
                                        <option value="approve_with_variance">{{ __('Approve With Variance') }}</option>
                                        <option value="escalate">{{ __('Escalate') }}</option>
                                        <option value="request_recount">{{ __('Request Recount') }}</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Review Notes') }}</label>
                                    <textarea class="form-control" name="review_notes" rows="4" placeholder="{{ __('Explain the reason for the shortage, overage, or decision taken.') }}">{{ $reconciliation->review_notes }}</textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button class="btn btn-primary">{{ __('Save Resolution') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection

@push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const productSelect = document.getElementById('saleInventoryItem');
            const unitPriceInput = document.getElementById('saleUnitPrice');

            if (productSelect && unitPriceInput) {
                productSelect.addEventListener('change', function () {
                    const selectedOption = this.options[this.selectedIndex];
                    const price = selectedOption.getAttribute('data-price');
                    if (price) {
                        unitPriceInput.value = price;
                    }
                });
            }
        });
    </script>
@endpush
