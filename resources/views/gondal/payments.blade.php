@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
    use Illuminate\Support\Str;
@endphp

@section('page-title')
    {{ __('Manage Payments') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Payments') }}</li>
@endsection

@section('action-btn')
    @php
        $addLabel = match ($tab) {
            'batches' => __('Create Payment Batch (Batches)'),
            'reconciliation' => __('Create Payment Batch (Reconciliation)'),
            default => __('Create Payment Batch (Overview)'),
        };
    @endphp
    <div class="float-end d-flex">
        @if (GondalPermissionRegistry::can(auth()->user(), 'payments', $tab, 'import'))
            <button type="button" class="btn btn-sm btn-secondary me-2" data-bs-toggle="modal"
                data-bs-target="#importPaymentsModal"
                title="{{ $tab === 'reconciliation' ? __('Import Reconciliation CSV') : __('Import Payment Batches CSV') }}">
                <i class="ti ti-file-import"></i>
            </button>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'payments', $tab, 'export'))
            <a href="{{ route('gondal.payments.export', array_merge(request()->query(), ['tab' => $tab])) }}"
                class="btn btn-sm btn-secondary me-2" title="{{ __('Export') }}">
                <i class="ti ti-file-export"></i>
            </a>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'payments', $tab, 'create'))
            <button type="button" class="btn btn-sm btn-info me-2" data-bs-toggle="modal"
                data-bs-target="#runSettlementModal" title="{{ __('Run Settlement Engine') }}">
                <i class="ti ti-rocket"></i> {{ __('Run Settlement') }}
            </button>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                data-bs-target="#createPaymentBatchModal" title="{{ $addLabel }}">
                <i class="ti ti-plus"></i>
                <span class="ms-1">{{ $addLabel }}</span>
            </button>
        @endif
    </div>
@endsection

@push('script-page')
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('createPaymentBatchModal');

                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
    @if ($errors->hasBag('import') && $errors->import->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('importPaymentsModal');

                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
@endpush

@section('content')
    @include('gondal.partials.alerts')

    <div class="row">
        @foreach ($overviewCards as $card)
            <div class="col-md-4 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ __($card['title']) }}</small>
                        <h4 class="mb-0 mt-2">{{ $card['amount'] }}</h4>
                        <small>{{ $card['meta'] }}</small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex gap-2 mb-4">
        @foreach ($visibleTabs as $visibleTab)
            <a href="{{ route('gondal.payments', ['tab' => $visibleTab['key']]) }}"
                class="btn btn-sm {{ $tab === $visibleTab['key'] ? 'btn-primary' : 'btn-light' }}">
                {{ __($visibleTab['label']) }}
            </a>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-12">
            @if ($tab === 'reconciliation')
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Reference') }}</th>
                                        <th>{{ __('Source') }}</th>
                                        <th>{{ __('Customer') }}</th>
                                        <th>{{ __('Item') }}</th>
                                        <th>{{ __('Payment Mode') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reconciliationRows as $row)
                                        <tr>
                                            <td>{{ $row['date'] ?: 'N/A' }}</td>
                                            <td>{{ $row['reference'] }}</td>
                                            <td>{{ __($row['source']) }}</td>
                                            <td>{{ $row['customer'] }}</td>
                                            <td>{{ $row['item'] }}</td>
                                            <td>{{ Str::title((string) $row['payment_mode']) }}</td>
                                            <td>₦{{ number_format((float) $row['amount'], 2) }}</td>
                                            <td>{{ ucfirst((string) $row['status']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Payee Type') }}</th>
                                        <th>{{ __('Period') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($batches as $batch)
                                        <tr>
                                            <td>{{ $batch->name }}</td>
                                            <td>{{ ucfirst($batch->payee_type) }}</td>
                                            <td>{{ optional($batch->period_start)->toDateString() }} - {{ optional($batch->period_end)->toDateString() }}</td>
                                            <td>₦{{ number_format($batch->total_amount, 2) }}</td>
                                            <td>{{ ucfirst($batch->status) }}</td>
                                            <td>
                                                @if (GondalPermissionRegistry::can(auth()->user(), 'payments', 'batches', 'edit') && $batch->status === 'approved')
                                                    <form method="POST" action="{{ route('gondal.payments.process', $batch->id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-primary" title="{{ __('Process Payment') }}">
                                                            <i class="ti ti-credit-card"></i> {{ __('Process') }}
                                                        </button>
                                                    </form>
                                                @else
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
            @endif
        </div>
    </div>

    <div class="modal fade" id="importPaymentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.payments.import') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $tab === 'reconciliation' ? __('Import Reconciliation CSV') : __('Import Payment Batches CSV') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            {{ $tab === 'reconciliation'
                                ? __('Expected columns: credit_date, item_sku, customer_name, amount, status.')
                                : __('Expected columns: name, payee_type, period_start, period_end, total_amount, status.') }}
                        </p>
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

    <div class="modal fade" id="createPaymentBatchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.payments.batches.store') }}">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Payment Batch') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Batch Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Payee Type') }}</label>
                            <select class="form-control" name="payee_type" required>
                                @foreach (['farmer', 'rider', 'staff'] as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Start') }}</label>
                                <input type="date" class="form-control" name="period_start" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('End') }}</label>
                                <input type="date" class="form-control" name="period_end" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Total Amount') }}</label>
                            <input type="number" step="0.01" class="form-control" name="total_amount" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select class="form-control" name="status" required>
                                @foreach (['draft', 'processing', 'approved', 'completed'] as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Save Batch') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="runSettlementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.payments.settlements.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Run Farmer Settlement Engine') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Farmer (Vender)') }}</label>
                            <select class="form-control" name="farmer_id" required>
                                <option value="" disabled selected>{{ __('Select Farmer') }}</option>
                                @foreach ($farmers as $farmer)
                                    <option value="{{ $farmer->id }}">{{ $farmer->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('The engine will compute deductions exclusively for this farmer.') }}</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Period Start') }}</label>
                                <input type="date" class="form-control" name="period_start" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Period End') }}</label>
                                <input type="date" class="form-control" name="period_end" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Max Deduction Limit (%)') }} <small>(Optional)</small></label>
                            <input type="number" step="0.01" max="100" class="form-control" name="max_deduction_percent" placeholder="e.g. 50">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Payout Floor Guarantee (₦)') }} <small>(Optional)</small></label>
                            <input type="number" step="0.01" class="form-control" name="payout_floor_amount" placeholder="e.g. 5000">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-info">{{ __('Ignite Engine') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
