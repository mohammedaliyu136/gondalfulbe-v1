@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
    use Illuminate\Support\Str;
@endphp

@push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const locationHierarchy = @json($agentLocationHierarchy ?? []);
            const loginDetails = @json(session('agent_login_details'));
            const stateSelect = document.querySelector('#agentState');
            const lgaSelect = document.querySelector('#agentLga');
            const communitySelect = document.querySelector('#agentCommunity');
            const assignedCommunitiesSelect = document.querySelector('#agentAssignedCommunities');
            const loginModeInputs = Array.from(document.querySelectorAll('input[name="login_mode"]'));
            const passwordModeInputs = Array.from(document.querySelectorAll('input[name="password_mode"]'));
            const existingLoginWrapper = document.getElementById('existingLoginAccountWrap');
            const existingLoginSelect = document.getElementById('agentUserId');
            const newLoginPasswordWrap = document.getElementById('newLoginPasswordWrap');
            const manualPasswordFields = document.querySelectorAll('[data-manual-password-field]');
            const agentModal = document.getElementById('createAgentModal');
            const importModal = document.getElementById('importAgentModal');
            const loginDetailsModal = document.getElementById('agentLoginDetailsModal');
            const agentForm = document.getElementById('createAgentForm');
            const agentSteps = Array.from(document.querySelectorAll('.agent-form-step'));
            const agentIndicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
            const prevStepButton = document.getElementById('agentWizardPrev');
            const nextStepButton = document.getElementById('agentWizardNext');
            const submitStepButton = document.getElementById('agentWizardSubmit');
            const copyFeedback = document.getElementById('agentLoginCopyFeedback');
            let activeAgentStep = 0;

            const renderOptions = (select, values, placeholder, selectedValues = []) => {
                if (!select) {
                    return;
                }

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
                if (!communitySelect || !assignedCommunitiesSelect) {
                    return;
                }

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
                if (!stateSelect || !lgaSelect || !communitySelect || !assignedCommunitiesSelect) {
                    return;
                }

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

            const syncLoginModeUi = () => {
                const selectedMode = loginModeInputs.find((input) => input.checked)?.value || 'new';
                const usingExisting = selectedMode === 'existing';

                if (existingLoginWrapper) {
                    existingLoginWrapper.classList.toggle('d-none', !usingExisting);
                }

                if (existingLoginSelect) {
                    existingLoginSelect.disabled = !usingExisting;
                    existingLoginSelect.required = usingExisting;

                    if (!usingExisting) {
                        existingLoginSelect.value = '';
                    }
                }

                if (newLoginPasswordWrap) {
                    newLoginPasswordWrap.classList.toggle('d-none', usingExisting);
                }

                syncPasswordModeUi();
            };

            const syncPasswordModeUi = () => {
                const selectedLoginMode = loginModeInputs.find((input) => input.checked)?.value || 'new';
                const selectedPasswordMode = passwordModeInputs.find((input) => input.checked)?.value || 'auto';
                const useManualPassword = selectedLoginMode === 'new' && selectedPasswordMode === 'manual';

                manualPasswordFields.forEach((field) => {
                    field.classList.toggle('d-none', !useManualPassword);
                });

                const passwordInput = document.getElementById('agentLoginPassword');
                const passwordConfirmInput = document.getElementById('agentLoginPasswordConfirmation');

                if (passwordInput) {
                    passwordInput.disabled = !useManualPassword;
                    passwordInput.required = useManualPassword;
                    if (!useManualPassword) {
                        passwordInput.value = '';
                    }
                }

                if (passwordConfirmInput) {
                    passwordConfirmInput.disabled = !useManualPassword;
                    passwordConfirmInput.required = useManualPassword;
                    if (!useManualPassword) {
                        passwordConfirmInput.value = '';
                    }
                }
            };

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

                prevStepButton?.classList.toggle('d-none', activeAgentStep === 0);
                nextStepButton?.classList.toggle('d-none', activeAgentStep === agentSteps.length - 1);
                submitStepButton?.classList.toggle('d-none', activeAgentStep !== agentSteps.length - 1);

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

            stateSelect?.addEventListener('change', syncLocationOptions);
            lgaSelect?.addEventListener('change', syncLocationOptions);
            communitySelect?.addEventListener('change', syncAssignedCommunitySelection);
            loginModeInputs.forEach((input) => input.addEventListener('change', syncLoginModeUi));
            passwordModeInputs.forEach((input) => input.addEventListener('change', syncPasswordModeUi));
            syncLocationOptions();
            syncLoginModeUi();

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
                    syncLoginModeUi();
                    syncAgentStepUi();
                }, 0);
            });

            agentForm?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && activeAgentStep < agentSteps.length - 1) {
                    event.preventDefault();
                    nextStepButton?.click();
                }
            });

            document.querySelectorAll('[data-copy-text]').forEach((button) => {
                button.addEventListener('click', async function () {
                    const text = button.getAttribute('data-copy-text') || '';
                    if (!text) {
                        return;
                    }

                    try {
                        if (navigator.clipboard?.writeText) {
                            await navigator.clipboard.writeText(text);
                        } else {
                            const helper = document.createElement('textarea');
                            helper.value = text;
                            helper.setAttribute('readonly', 'readonly');
                            helper.style.position = 'absolute';
                            helper.style.left = '-9999px';
                            document.body.appendChild(helper);
                            helper.select();
                            document.execCommand('copy');
                            document.body.removeChild(helper);
                        }

                        if (copyFeedback) {
                            copyFeedback.textContent = button.getAttribute('data-copy-label') || '{{ __('Copied to clipboard.') }}';
                        }
                    } catch (error) {
                        if (copyFeedback) {
                            copyFeedback.textContent = '{{ __('Copy failed. You can select and copy the text manually.') }}';
                        }
                    }
                });
            });

            syncAgentStepUi();

            @if ($errors->import->any())
                if (importModal && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(importModal).show();
                }
            @endif

            if (loginDetails && loginDetailsModal && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(loginDetailsModal).show();
            }
        });
    </script>
@endpush

@section('page-title', __('Agents'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Agents') }}</li>
@endsection

@section('action-btn')
    <div class="d-flex gap-2">
        <a href="{{ route('gondal.agents.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-layout-dashboard"></i> {{ __('Agent Dashboard') }}
        </a>
        @if (GondalPermissionRegistry::can(auth()->user(), 'inventory', 'agents', 'create'))
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importAgentModal">
                <i class="ti ti-file-import"></i> {{ __('Import Agents') }}
            </button>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'inventory', 'agents', 'create'))
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createAgentModal">
                <i class="ti ti-user-plus"></i> {{ __('Add Agent') }}
            </button>
        @endif
    </div>
@endsection

@section('content')
    @include('gondal.partials.alerts')

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
        <div class="card-header">
            <h5 class="mb-1">{{ __('Agents') }}</h5>
            <p class="text-muted mb-0">{{ __('Manage who sells on behalf of the company and how each agent settles stock and credit.') }}</p>
        </div>
        <div class="card-body table-border-style">
            <div class="table-responsive">
                <table class="table datatable table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Agent Code') }}</th>
                            <th>{{ __('Identity') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Project') }}</th>
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
                                    <div>{{ $agent->project?->project_name ?: (in_array($agent->agent_type, ['farmer', 'independent_reseller']) ? __('Unassigned') : '—') }}</div>
                                    @if ($agent->project?->client?->name)
                                        <small class="text-muted">{{ $agent->project->client->name }}</small>
                                    @elseif ($agent->sponsor?->name)
                                        <small class="text-muted">{{ $agent->sponsor->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $agent->outlet_name ?: '-' }}</div>
                                    <small class="text-muted">
                                        {{ $agent->oneStopShop?->name ? __('OSS').': '.$agent->oneStopShop->name.' · ' : '' }}{{ $agent->communityRecord?->name ?: ($agent->community ?: __('No primary community')) }}{{ !empty($agent->assigned_communities) ? ' · '.implode(', ', $agent->assigned_communities) : '' }}
                                        @if ($agent->cooperatives->isNotEmpty())
                                            {{ ' · '.__('Coops').': '.$agent->cooperatives->pluck('name')->implode(', ') }}
                                        @endif
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
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importAgentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.agents.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Import Agents') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if ($errors->import->any())
                            <div class="alert alert-danger">
                                {{ $errors->import->first('import_file') ?: $errors->import->first() }}
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">{{ __('CSV File') }}</label>
                            <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div class="small text-muted">
                                {{ __('Download a ready-made sample if you want to test the import format first.') }}
                            </div>
                            <a href="{{ route('gondal.agents.sample') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-download"></i> {{ __('Download Sample CSV') }}
                            </a>
                        </div>

                        <div class="alert alert-light border mb-3">
                            <div class="fw-semibold mb-2">{{ __('Supported columns') }}</div>
                            <div class="small text-muted mb-2">{{ __('Use IDs, emails, or names for internal user and supervisor. Assigned communities can be separated with commas, semicolons, or pipes. Use one_stop_shop_name for the assigned one-stop shop.') }}</div>
                            <code class="d-block small text-wrap">
                                user_id,internal_user_email,supervisor_user_id,supervisor_email,project_name,cooperative_ids,agent_type,first_name,middle_name,last_name,gender,phone_number,email,nin,state,lga,community,residential_address,permanent_address,one_stop_shop_name,assigned_communities,reconciliation_frequency,settlement_mode,account_number,account_name,bank_details,status
                            </code>
                        </div>

                        <div class="small text-muted">
                            {{ __('If assigned_communities is omitted, the primary community will be used automatically. Farmer and independent reseller agents should include project_name or project_id. one_stop_shop_name can use the OSS name or code. cooperative_ids can contain IDs, codes, or names separated by commas, semicolons, or pipes. Defaults: reconciliation_frequency = weekly, settlement_mode = consignment, status = active.') }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Import CSV') }}</button>
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
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('First Name') }}</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Middle Name') }}</label>
                                    <input type="text" class="form-control" name="middle_name">
                                </div>
                                <div class="col-md-4">
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
                                    <label class="form-label d-block">{{ __('Login Account') }}</label>
                                    <div class="d-flex flex-wrap gap-3 pt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="login_mode" id="agentLoginModeNew" value="new" checked>
                                            <label class="form-check-label" for="agentLoginModeNew">{{ __('Create New Login') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="login_mode" id="agentLoginModeExisting" value="existing">
                                            <label class="form-check-label" for="agentLoginModeExisting">{{ __('Use Existing Login') }}</label>
                                        </div>
                                    </div>
                                    <div class="alert alert-light border mt-3 mb-0">
                                        <div class="fw-semibold mb-1">{{ __('Create New Login') }}</div>
                                        <div class="small text-muted">{{ __('The system will create a login using the email address above. You can auto-generate a temporary password or set one manually.') }}</div>
                                    </div>
                                    <div class="mt-3" id="newLoginPasswordWrap">
                                        <label class="form-label d-block">{{ __('Password Setup') }}</label>
                                        <div class="d-flex flex-wrap gap-3 pt-1">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="password_mode" id="agentPasswordModeAuto" value="auto" checked>
                                                <label class="form-check-label" for="agentPasswordModeAuto">{{ __('Auto Generate Password') }}</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="password_mode" id="agentPasswordModeManual" value="manual">
                                                <label class="form-check-label" for="agentPasswordModeManual">{{ __('Set Password Manually') }}</label>
                                            </div>
                                        </div>
                                        <div class="row g-3 mt-0">
                                            <div class="col-md-6 d-none" data-manual-password-field>
                                                <label class="form-label">{{ __('Password') }}</label>
                                                <input type="password" class="form-control" id="agentLoginPassword" name="password" minlength="6">
                                            </div>
                                            <div class="col-md-6 d-none" data-manual-password-field>
                                                <label class="form-label">{{ __('Confirm Password') }}</label>
                                                <input type="password" class="form-control" id="agentLoginPasswordConfirmation" name="password_confirmation" minlength="6">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 d-none" id="existingLoginAccountWrap">
                                        <label class="form-label">{{ __('Existing Login Account') }}</label>
                                        <select class="form-select select2" id="agentUserId" name="user_id" data-placeholder="{{ __('Search staff or independent agent account') }}">
                                            <option value="">{{ __('Search and select account') }}</option>
                                            @if ($internalUsers->isNotEmpty())
                                                <optgroup label="{{ __('Internal Staff Users') }}">
                                                    @foreach ($internalUsers as $user)
                                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ Str::headline((string) $user->type) }})</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                            @if (($independentAgentUsers ?? collect())->isNotEmpty())
                                                <optgroup label="{{ __('Independent Agent Users') }}">
                                                    @foreach (($independentAgentUsers ?? collect()) as $user)
                                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        </select>
                                        <small class="text-muted">{{ __('Choose an existing login only if this agent already has an account. Otherwise keep Create New Login selected.') }}</small>
                                    </div>
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
                                    <label class="form-label">{{ __('Cooperatives') }}</label>
                                    <select class="form-select select2" id="agentCooperatives" name="cooperative_ids[]" multiple data-placeholder="{{ __('Select cooperatives') }}" required>
                                        @foreach ($cooperatives as $cooperative)
                                            <option value="{{ $cooperative->id }}">{{ $cooperative->name }}{{ $cooperative->code ? ' ('.$cooperative->code.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">{{ __('Select the cooperative groups this agent belongs to.') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Residential Address') }}</label>
                                    <textarea class="form-control" name="residential_address" rows="2" required></textarea>
                                </div>
                            </div>
                            <div class="row g-3 mt-0">
                                <div class="col-md-12">
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
                                    <label class="form-label">{{ __('Project') }}</label>
                                    <select class="form-select select2" name="project_id" data-placeholder="{{ __('Search project') }}">
                                        <option value="">{{ __('Select project') }}</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}">{{ $project->project_name }}{{ $project->client?->name ? ' ('.$project->client->name.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">{{ __('Use a project to group farmer and independent reseller agents and report their performance by project.') }}</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('Assigned One-Stop Shop') }}</label>
                                    <select class="form-select select2" name="one_stop_shop_id" data-placeholder="{{ __('Search one-stop shop') }}" required>
                                        <option value="">{{ __('Select one-stop shop') }}</option>
                                        @foreach ($oneStopShops as $shop)
                                            <option value="{{ $shop->id }}">{{ $shop->name }}{{ $shop->community?->name ? ' ('.$shop->community->name.')' : '' }}</option>
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

    @if (session('agent_login_details'))
        @php
            $loginDetails = session('agent_login_details');
            $agentName = $loginDetails['agent_name'] ?? __('New Agent');
            $agentCode = $loginDetails['agent_code'] ?? '—';
            $loginEmail = $loginDetails['email'] ?? '';
            $loginPassword = $loginDetails['password'] ?? '';
            $loginPhone = $loginDetails['phone_number'] ?? '';
            $whatsAppPhone = preg_replace('/\D+/', '', (string) $loginPhone);
            if (Str::startsWith($whatsAppPhone, '0')) {
                $whatsAppPhone = '234'.substr($whatsAppPhone, 1);
            }
            $loginMessage = trim(
                __('Hello :name, your Gondal agent login has been created.', ['name' => $agentName])."\n\n".
                __('Agent Code: :code', ['code' => $agentCode])."\n".
                __('Login Email: :email', ['email' => $loginEmail])."\n".
                __('Password: :password', ['password' => $loginPassword])."\n\n".
                __('Please sign in and change your password after first login.')
            );
            $mailTo = 'mailto:'.rawurlencode($loginEmail)
                .'?subject='.rawurlencode(__('Your Gondal Agent Login Details'))
                .'&body='.rawurlencode($loginMessage);
            $whatsAppUrl = $whatsAppPhone !== '' ? 'https://wa.me/'.$whatsAppPhone.'?text='.rawurlencode($loginMessage) : null;
        @endphp

        <div class="modal fade" id="agentLoginDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">{{ __('Login Created') }}</h5>
                            <div class="small text-muted">{{ __('Share these credentials with the agent now. This only appears when a new login account is created.') }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('Agent') }}</div>
                                    <div class="fw-semibold">{{ $agentName }}</div>
                                    <div class="text-muted">{{ __('Code') }}: {{ $agentCode }}</div>
                                    <div class="text-muted">{{ __('Phone') }}: {{ $loginPhone ?: '—' }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('Login Email') }}</div>
                                    <div class="fw-semibold">{{ $loginEmail }}</div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-copy-text="{{ $loginEmail }}" data-copy-label="{{ __('Email copied.') }}">
                                        <i class="ti ti-copy"></i> {{ __('Copy Email') }}
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="border rounded-3 p-3">
                                    <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('Password') }}</div>
                                    <div class="fw-semibold font-monospace">{{ $loginPassword }}</div>
                                    <div class="small text-muted mt-2">{{ __('Ask the agent to change this password after first login.') }}</div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-copy-text="{{ $loginPassword }}" data-copy-label="{{ __('Password copied.') }}">
                                        <i class="ti ti-copy"></i> {{ __('Copy Password') }}
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="border rounded-3 p-3 bg-light">
                                    <div class="small text-muted text-uppercase fw-semibold mb-2">{{ __('Full Message') }}</div>
                                    <pre class="mb-3 small text-dark" style="white-space: pre-wrap;">{{ $loginMessage }}</pre>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-primary" data-copy-text="{{ $loginMessage }}" data-copy-label="{{ __('Full login details copied.') }}">
                                            <i class="ti ti-copy"></i> {{ __('Copy Full Details') }}
                                        </button>
                                        <a href="{{ $mailTo }}" class="btn btn-outline-secondary">
                                            <i class="ti ti-mail"></i> {{ __('Send by Email') }}
                                        </a>
                                        @if ($whatsAppUrl)
                                            <a href="{{ $whatsAppUrl }}" target="_blank" rel="noopener" class="btn btn-outline-success">
                                                <i class="ti ti-brand-whatsapp"></i> {{ __('Send by WhatsApp') }}
                                            </a>
                                        @endif
                                    </div>
                                    <div class="small text-success mt-3" id="agentLoginCopyFeedback"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
