@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
@endphp

@section('page-title')
    {{ __('Manage Requisitions') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Requisitions') }}</li>
@endsection

@section('action-btn')
    <div class="float-end d-flex">
        @if (GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'requests', 'import'))
            <button type="button" class="btn btn-sm bg-brown-subtitle me-2" data-bs-toggle="modal"
                data-bs-target="#importRequisitionsModal" title="{{ __('Import Requisitions CSV') }}">
                <i class="ti ti-file-import"></i>
            </button>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'requests', 'export'))
            <a href="{{ route('gondal.requisitions.export', array_merge(request()->query(), ['tab' => $tab])) }}"
                class="btn btn-sm btn-secondary me-2" title="{{ __('Export') }}">
                <i class="ti ti-file-export"></i>
            </a>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'requests', 'create'))
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                data-bs-target="#createRequisitionModal" title="{{ __('Create Requisition') }}">
                <i class="ti ti-plus"></i>
            </button>
        @endif
    </div>
@endsection

@push('script-page')
    <script>
        document.addEventListener('click', function (event) {
            const addItemButton = event.target.closest('[data-add-item]');

            if (addItemButton) {
                const container = document.getElementById('requisition-items');
                const row = document.querySelector('[data-item-row]').cloneNode(true);
                row.querySelectorAll('input').forEach((input) => input.value = '');
                container.appendChild(row);
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const actionModal = document.getElementById('requisitionActionModal');

            if (actionModal) {
                actionModal.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;

                    if (!trigger) {
                        return;
                    }

                    const form = actionModal.querySelector('form');
                    const title = actionModal.querySelector('[data-modal-title]');
                    const submitButton = actionModal.querySelector('[data-submit-button]');
                    const notesGroup = actionModal.querySelector('[data-notes-group]');
                    const notesField = actionModal.querySelector('[name="notes"]');
                    const submitClass = trigger.getAttribute('data-submit-class') || 'btn-primary';
                    const needsNotes = trigger.getAttribute('data-needs-notes') === 'true';

                    form.action = trigger.getAttribute('data-action');
                    title.textContent = trigger.getAttribute('data-title') || '';
                    submitButton.textContent = trigger.getAttribute('data-submit-label') || '{{ __('Confirm') }}';
                    submitButton.className = 'btn ' + submitClass;
                    notesGroup.classList.toggle('d-none', !needsNotes);
                    notesField.disabled = !needsNotes;
                    notesField.required = needsNotes;
                    notesField.value = needsNotes ? (trigger.getAttribute('data-default-notes') || '') : '';
                });
            }

            @if ($errors->any())
                const createModal = document.getElementById('createRequisitionModal');

                if (createModal && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(createModal).show();
                }
            @endif

            @if ($errors->hasBag('import') && $errors->import->any())
                const importModal = document.getElementById('importRequisitionsModal');

                if (importModal && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(importModal).show();
                }
            @endif
        });
    </script>
@endpush

@section('content')
    @include('gondal.partials.alerts')

    <div class="d-flex gap-2 mb-4">
        @foreach ($statusTabs as $statusTab)
            <a href="{{ route('gondal.requisitions', ['tab' => $statusTab['key']]) }}"
                class="btn btn-sm {{ $tab === $statusTab['key'] ? 'btn-primary' : 'btn-light' }}">
                {{ __($statusTab['label']) }}
            </a>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Requester') }}</th>
                                    <th>{{ __('Priority') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                                <tbody>
                                    @foreach ($requisitions as $requisition)
                                        <tr>
                                            <td>
                                                @if (GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'details', 'show'))
                                                    <a href="{{ route('gondal.requisitions.show', $requisition->id) }}">{{ $requisition->reference }}</a>
                                                @else
                                                    {{ $requisition->reference }}
                                                @endif
                                            </td>
                                            <td>{{ $requisition->title }}</td>
                                            <td>{{ $requisition->requester?->name ?: 'N/A' }}</td>
                                            <td>{{ ucfirst($requisition->priority) }}</td>
                                            <td>₦{{ number_format($requisition->total_amount, 2) }}</td>
                                            <td>{{ ucfirst($requisition->status) }}</td>
                                            <td class="d-flex gap-2">
                                                @if (GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'details', 'show'))
                                                    <a href="{{ route('gondal.requisitions.show', $requisition->id) }}" class="btn btn-sm btn-warning">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                @endif
                                                @if (GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'approvals', 'edit'))
                                                    @php
                                                        $userType = strtolower(auth()->user()->type);
                                                        $amt = $requisition->total_amount;
                                                        $isED = in_array($userType, ['executive director', 'company', 'super admin']);
                                                        $isLead = $isED || in_array($userType, ['component lead']);
                                                        $isFinance = $isLead || in_array($userType, ['finance officer', 'finance']);
                                                        $canApprove = false;
                                                        if ($amt > 200000 && $isED) $canApprove = true;
                                                        elseif ($amt >= 50000 && $amt <= 200000 && $isLead) $canApprove = true;
                                                        elseif ($amt < 50000 && $isFinance) $canApprove = true;
                                                    @endphp
                                                    @if ($requisition->status === 'pending')
                                                        @if($canApprove)
                                                            <button type="button" class="btn btn-sm btn-success"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#requisitionActionModal"
                                                                data-action="{{ route('gondal.requisitions.approve', $requisition->id) }}"
                                                                data-title="{{ __('Approve Requisition') }}"
                                                                data-submit-label="{{ __('Approve') }}"
                                                                data-submit-class="btn-success"
                                                                data-needs-notes="false">
                                                                <i class="ti ti-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#requisitionActionModal"
                                                                data-action="{{ route('gondal.requisitions.reject', $requisition->id) }}"
                                                                data-title="{{ __('Reject Requisition') }}"
                                                                data-submit-label="{{ __('Reject') }}"
                                                                data-submit-class="btn-danger"
                                                                data-needs-notes="true"
                                                                data-default-notes="{{ __('Rejected from requisitions table') }}">
                                                                <i class="ti ti-x"></i>
                                                            </button>
                                                        @else
                                                            <span class="badge bg-light-secondary" title="{{ __('Role threshold too low') }}"><i class="ti ti-lock"></i></span>
                                                        @endif
                                                    @endif
                                                @endif
                                                @if (!GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'details', 'show') && !(GondalPermissionRegistry::can(auth()->user(), 'requisitions', 'approvals', 'edit') && $requisition->status === 'pending'))
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importRequisitionsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.requisitions.import') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Import Requisitions CSV') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">{{ __('Expected columns: reference, requester_email, cooperative_code, title, description, priority, status, total_amount, item_name, item_quantity, item_unit, item_cost.') }}</p>
                        <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Import CSV') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createRequisitionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.requisitions.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Requisition') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Title') }}</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Priority') }}</label>
                                <select class="form-control" name="priority" required>
                                    @foreach (['low', 'medium', 'high'] as $priority)
                                        <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Cooperative') }}</label>
                                <select class="form-control" name="cooperative_id">
                                    <option value="">{{ __('Optional') }}</option>
                                    @foreach ($cooperatives as $cooperative)
                                        <option value="{{ $cooperative->id }}">{{ $cooperative->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div id="requisition-items">
                            <div class="border rounded p-3 mb-3" data-item-row>
                                <div class="mb-2">
                                    <label class="form-label">{{ __('Item Name') }}</label>
                                    <input type="text" class="form-control" name="item_name[]">
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">{{ __('Qty') }}</label>
                                        <input type="number" step="0.01" class="form-control" name="item_quantity[]">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">{{ __('Unit') }}</label>
                                        <input type="text" class="form-control" name="item_unit[]">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">{{ __('Unit Cost') }}</label>
                                        <input type="number" step="0.01" class="form-control" name="item_cost[]">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-light w-100 mb-3" data-add-item>{{ __('Add Another Item') }}</button>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Total Amount') }}</label>
                            <input type="number" step="0.01" class="form-control" name="total_amount" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Submit Requisition') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="requisitionActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" data-modal-title>{{ __('Confirm Action') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">{{ __('Review this action before continuing.') }}</p>
                        <div class="mb-0 d-none" data-notes-group>
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea class="form-control" name="notes" rows="3" disabled></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" data-submit-button>{{ __('Confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
