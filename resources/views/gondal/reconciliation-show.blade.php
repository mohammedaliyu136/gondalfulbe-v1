@extends('layouts.admin')

@section('page-title', __('Reconciliation Details'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('gondal.inventory', ['tab' => 'reconciliation']) }}">{{ __('Reconciliation') }}</a></li>
    <li class="breadcrumb-item">{{ $reconciliation->reference }}</li>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h4 class="mb-1">{{ __('Reconciliation Details') }}</h4>
            <p class="text-muted mb-0">
                {{ $reconciliation->agentProfile?->outlet_name ?: $reconciliation->agentProfile?->user?->name ?: $reconciliation->agentProfile?->vender?->name ?: '-' }}
                | {{ $reconciliation->reference }}
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if (\App\Support\GondalPermissionRegistry::can(auth()->user(), 'inventory', 'reconciliation', 'edit'))
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resolveReconciliationModal">
                    {{ __('Resolve') }}
                </button>
            @endif
            <a href="{{ route('gondal.inventory', ['tab' => 'reconciliation']) }}" class="btn btn-outline-secondary">
                {{ __('Back to Reconciliation') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Expected Cash') }}</small>
                    <h5 class="mb-0 mt-2">₦{{ number_format($reconciliation->expected_cash_amount, 2) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Remitted Cash') }}</small>
                    <h5 class="mb-0 mt-2">₦{{ number_format($reconciliation->remitted_cash_amount, 2) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Expected Stock') }}</small>
                    <h5 class="mb-0 mt-2">{{ number_format($reconciliation->expected_stock_qty, 2) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Counted Stock') }}</small>
                    <h5 class="mb-0 mt-2">{{ number_format($reconciliation->counted_stock_qty, 2) }}</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Reconciliation Breakdown') }}</h6>
                </div>
                <div class="card-body">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr><th>{{ __('Agent') }}</th><td>{{ $reconciliation->agentProfile?->outlet_name ?: $reconciliation->agentProfile?->user?->name ?: $reconciliation->agentProfile?->vender?->name ?: '-' }}</td></tr>
                            <tr><th>{{ __('Product') }}</th><td>{{ $reconciliation->item?->name ?: __('All Products') }}</td></tr>
                            <tr><th>{{ __('Mode') }}</th><td>{{ \Illuminate\Support\Str::headline($reconciliation->reconciliation_mode) }}</td></tr>
                            <tr><th>{{ __('Period') }}</th><td>{{ optional($reconciliation->period_start)->toDateString() }} - {{ optional($reconciliation->period_end)->toDateString() }}</td></tr>
                            <tr><th>{{ __('Opening Stock') }}</th><td>{{ number_format($reconciliation->opening_stock_qty, 2) }}</td></tr>
                            <tr><th>{{ __('Issued Stock') }}</th><td>{{ number_format($reconciliation->issued_stock_qty, 2) }}</td></tr>
                            <tr><th>{{ __('Sold Stock') }}</th><td>{{ number_format($reconciliation->sold_stock_qty, 2) }}</td></tr>
                            <tr><th>{{ __('Returned Stock') }}</th><td>{{ number_format($reconciliation->returned_stock_qty, 2) }}</td></tr>
                            <tr><th>{{ __('Damaged Stock') }}</th><td>{{ number_format($reconciliation->damaged_stock_qty, 2) }}</td></tr>
                            <tr><th>{{ __('Stock Variance') }}</th><td>{{ number_format($reconciliation->stock_variance_qty, 2) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Cash and Review Details') }}</h6>
                </div>
                <div class="card-body">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr><th>{{ __('Cash Sales') }}</th><td>₦{{ number_format($reconciliation->cash_sales_amount, 2) }}</td></tr>
                            <tr><th>{{ __('Transfer Sales') }}</th><td>₦{{ number_format($reconciliation->transfer_sales_amount, 2) }}</td></tr>
                            <tr><th>{{ __('Credit Sales') }}</th><td>₦{{ number_format($reconciliation->credit_sales_amount, 2) }}</td></tr>
                            <tr><th>{{ __('Credit Collections') }}</th><td>₦{{ number_format($reconciliation->credit_collections_amount, 2) }}</td></tr>
                            <tr><th>{{ __('Cash Variance') }}</th><td>₦{{ number_format($reconciliation->cash_variance_amount, 2) }}</td></tr>
                            <tr><th>{{ __('Outstanding Credit') }}</th><td>₦{{ number_format($reconciliation->outstanding_credit_amount, 2) }}</td></tr>
                            <tr><th>{{ __('Status') }}</th><td>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $reconciliation->status)) }}</td></tr>
                            <tr><th>{{ __('Submitted By') }}</th><td>{{ $reconciliation->submitter?->name ?: '-' }}</td></tr>
                            <tr><th>{{ __('Reviewed By') }}</th><td>{{ $reconciliation->reviewer?->name ?: '-' }}</td></tr>
                            <tr><th>{{ __('Agent Notes') }}</th><td>{{ $reconciliation->agent_notes ?: '-' }}</td></tr>
                            <tr><th>{{ __('Review Notes') }}</th><td>{{ $reconciliation->review_notes ?: '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if (\App\Support\GondalPermissionRegistry::can(auth()->user(), 'inventory', 'reconciliation', 'edit'))
        <div class="modal fade" id="resolveReconciliationModal" tabindex="-1" aria-hidden="true">
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
                                <textarea class="form-control" name="review_notes" rows="4" placeholder="{{ __('Explain the decision taken for this reconciliation.') }}">{{ $reconciliation->review_notes }}</textarea>
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
    @endif
@endsection
