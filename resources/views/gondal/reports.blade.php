@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
@endphp

@section('page-title')
    {{ __('Manage Reports') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Reports') }}</li>
@endsection

@section('action-btn')
    <div class="float-end d-flex">
        @if (GondalPermissionRegistry::can(auth()->user(), 'reports', 'overview', 'import'))
            <button type="button" class="btn btn-sm bg-brown-subtitle me-2" data-bs-toggle="modal"
                data-bs-target="#importReportsModal" title="{{ __('Import Report Snapshot CSV') }}">
                <i class="ti ti-file-import"></i>
            </button>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'reports', 'overview', 'export'))
            <a href="{{ route('gondal.reports.export', request()->query()) }}" class="btn btn-sm btn-secondary me-2"
                data-bs-toggle="tooltip" title="{{ __('Export') }}">
                <i class="ti ti-file-export"></i>
            </a>
        @endif
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
            data-bs-target="#reportFiltersModal" title="{{ __('Filter Reports') }}">
            <i class="ti ti-adjustments"></i>
        </button>
    </div>
@endsection

@push('script-page')
    @if ($errors->hasBag('import') && $errors->import->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('importReportsModal');

                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
@endpush

@section('content')
    @include('gondal.partials.alerts')

    <div class="d-flex flex-wrap gap-2 mb-4">
        <span class="badge bg-light text-dark">{{ __('From') }}: {{ $from ?: __('Any') }}</span>
        <span class="badge bg-light text-dark">{{ __('To') }}: {{ $to ?: __('Any') }}</span>
        <span class="badge bg-light text-dark">{{ __('Status') }}: {{ $selectedStatus ? ucfirst($selectedStatus) : __('All') }}</span>
        <span class="badge {{ $source === 'imported' ? 'bg-warning text-dark' : 'bg-light text-dark' }}">
            {{ __('Source') }}: {{ $source === 'imported' ? __('Imported Snapshot') : __('Live Data') }}
        </span>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card"><div class="card-body"><small class="text-muted">{{ __('Total Collection') }}</small><h4 class="mb-0 mt-2">{{ number_format($summary['total_collection'], 2) }} L</h4></div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body"><small class="text-muted">{{ __('Total Cost') }}</small><h4 class="mb-0 mt-2">₦{{ number_format($summary['total_cost'], 2) }}</h4></div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body"><small class="text-muted">{{ __('Net Value') }}</small><h4 class="mb-0 mt-2">{{ number_format($summary['net_value'], 2) }}</h4></div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body"><small class="text-muted">{{ __('Requisition Count') }}</small><h4 class="mb-0 mt-2">{{ number_format($summary['requisition_count'], 0) }}</h4></div></div>
        </div>
    </div>

    <div class="modal fade" id="importReportsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.reports.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Import Report Snapshot CSV') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            {{ __('Expected columns: metric, value. Supported metrics: total_collection, total_cost, net_value, requisition_count.') }}
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

    <div class="modal fade" id="reportFiltersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="GET" action="{{ route('gondal.reports') }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Filter Reports') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('From') }}</label>
                                <input type="date" class="form-control" name="from" value="{{ $from }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('To') }}</label>
                                <input type="date" class="form-control" name="to" value="{{ $to }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Requisition Status') }}</label>
                                <select class="form-control" name="status">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach (['pending', 'approved', 'rejected'] as $status)
                                        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('gondal.reports') }}" class="btn btn-light">{{ __('Reset') }}</a>
                        <button class="btn btn-primary">{{ __('Apply Filters') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
