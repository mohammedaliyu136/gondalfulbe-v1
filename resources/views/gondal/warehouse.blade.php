@extends('layouts.admin')

@section('page-title', __('Warehouse'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Warehouse') }}</li>
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

    <div class="card">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
            <ul class="nav nav-pills gap-2 flex-wrap">
                @foreach ($visibleTabs as $visibleTab)
                    <li class="nav-item">
                        <a href="{{ route('gondal.warehouse', ['tab' => $visibleTab['key']]) }}" class="nav-link {{ $tab === $visibleTab['key'] ? 'active' : '' }}">
                            {{ __($visibleTab['label']) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h5 class="mb-1 text-dark">
                        {{ $tab === 'registry' ? __('Warehouse Registry') : ($tab === 'stock' ? __('Warehouse Stock') : ($tab === 'outside' ? __('Stock Outside Warehouse') : __('Warehouse Dispatches'))) }}
                    </h5>
                    <p class="text-muted mb-0">
                        {{ $tab === 'registry' ? __('Manage the real warehouses serving Gondal inventory.') : ($tab === 'stock' ? __('Load one-stop-shop inventory into a selected warehouse.') : ($tab === 'outside' ? __('Track stock already outside the warehouse, what agents still hold unsold, and what has been sold but is still waiting for approved reconciliation.') : __('Issue stock directly from a warehouse to an agent and keep inventory synchronized.'))) }}
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if ($tab === 'registry')
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createWarehouseModal">
                            <i class="ti ti-plus me-1"></i>{{ __('Add Warehouse') }}
                        </button>
                    @endif
                    @if ($tab === 'stock')
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createWarehouseStockModal">
                            <i class="ti ti-plus me-1"></i>{{ __('Load Stock') }}
                        </button>
                    @endif
                    @if ($tab === 'dispatches')
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createWarehouseIssueModal">
                            <i class="ti ti-package-export me-1"></i>{{ __('Issue Stock') }}
                        </button>
                    @endif
                </div>
            </div>

            @if ($tab === 'outside')
                <div class="row mb-4">
                    @foreach ($outsideSummaryCards as $card)
                        <div class="col-md-3">
                            <div class="card bg-light border-0 shadow-none h-100">
                                <div class="card-body">
                                    <small class="text-muted fw-bold text-uppercase">{{ __($card['label']) }}</small>
                                    <h5 class="mb-0 mt-2 text-dark">{{ $card['value'] }}</h5>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            @endif

            <div class="table-border-style">
                @if ($tab === 'dispatches')
                    <div class="mb-3">
                        <h6 class="mb-1 text-dark">{{ __('Dispatch History') }}</h6>
                        <p class="text-muted small mb-0">{{ __('Every stock issue from warehouse to agent, including already sold or reconciled lines.') }}</p>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table datatable table-hover align-middle">
                        @if ($tab === 'registry')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('Address') }}</th>
                                    <th>{{ __('City') }}</th>
                                    <th>{{ __('Tracked SKUs') }}</th>
                                    <th>{{ __('Units') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($warehouses as $warehouse)
                                    @php
                                        $warehouseRows = $warehouseStocks->where('warehouse_id', $warehouse->id);
                                    @endphp
                                    <tr>
                                        <td class="fw-bold">{{ $warehouse->name }}</td>
                                        <td>{{ $warehouse->address }}</td>
                                        <td>{{ $warehouse->city }} {{ $warehouse->city_zip }}</td>
                                        <td>{{ number_format($warehouseRows->pluck('inventory_item_id')->unique()->count()) }}</td>
                                        <td>{{ number_format((float) $warehouseRows->sum('quantity'), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'stock')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Reorder Level') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($warehouseStocks as $stock)
                                    <tr>
                                        <td class="fw-bold">{{ $stock->warehouse?->name }}</td>
                                        <td>{{ $stock->item?->name ?: '-' }}</td>
                                        <td>{{ number_format($stock->quantity, 2) }} {{ $stock->item?->unit }}</td>
                                        <td>{{ number_format($stock->reorder_level, 2) }}</td>
                                        <td>
                                            @if ($stock->quantity <= 0)
                                                <span class="badge bg-danger rounded-pill">{{ __('Out of Stock') }}</span>
                                            @elseif ($stock->quantity <= $stock->reorder_level)
                                                <span class="badge bg-warning rounded-pill">{{ __('Reorder') }}</span>
                                            @else
                                                <span class="badge bg-success rounded-pill">{{ __('Healthy') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @elseif ($tab === 'outside')
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('Agent') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Issued Out') }}</th>
                                    <th>{{ __('Sold') }}</th>
                                    <th>{{ __('Still With Agent') }}</th>
                                    <th>{{ __('Sold Not Reconciled') }}</th>
                                    <th>{{ __('Latest Issue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($outsideStockRows as $row)
                                    <tr>
                                        <td class="fw-bold">{{ $row['warehouse_name'] }}</td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $row['agent_name'] }}</div>
                                            @if ($row['agent_code'])
                                                <small class="text-muted">{{ $row['agent_code'] }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $row['item_name'] }}</td>
                                        <td>{{ number_format($row['issued_quantity'], 2) }} {{ $row['item_unit'] }}</td>
                                        <td>{{ number_format($row['sold_quantity'], 2) }} {{ $row['item_unit'] }}</td>
                                        <td>
                                            <span class="badge bg-warning-subtle text-warning rounded-pill">
                                                {{ number_format($row['unsold_quantity'], 2) }} {{ $row['item_unit'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill">
                                                {{ number_format($row['sold_pending_reconciliation'], 2) }} {{ $row['item_unit'] }}
                                            </span>
                                        </td>
                                        <td>{{ optional($row['latest_issue_date'])->toDateString() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            {{ __('No issued stock is currently sitting outside the warehouse or waiting for reconciliation.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        @else
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('Agent') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Issued On') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dispatches as $dispatch)
                                    <tr>
                                        <td class="fw-bold">{{ $dispatch->issue_reference }}</td>
                                        <td>{{ $dispatch->warehouse?->name ?: '-' }}</td>
                                        <td>{{ $dispatch->agentProfile?->outlet_name ?: $dispatch->agentProfile?->user?->name ?: $dispatch->agentProfile?->vender?->name ?: '-' }}</td>
                                        <td>{{ $dispatch->item?->name ?: '-' }}</td>
                                        <td>{{ number_format($dispatch->quantity_issued, 2) }}</td>
                                        <td>{{ optional($dispatch->issued_on)->toDateString() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createWarehouseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.warehouse.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Warehouse') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Address') }}</label>
                            <textarea class="form-control" name="address" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('City') }}</label>
                                <input type="text" class="form-control" name="city" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('ZIP / Area Code') }}</label>
                                <input type="text" class="form-control" name="city_zip" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Save Warehouse') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createWarehouseStockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.warehouse.stocks.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Load Warehouse Stock') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Warehouse') }}</label>
                            <select class="form-select" name="warehouse_id" required>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
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
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Quantity') }}</label>
                                <input type="number" step="0.01" class="form-control" name="quantity" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Reorder Level') }}</label>
                                <input type="number" step="0.01" class="form-control" name="reorder_level" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Load Stock') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createWarehouseIssueModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.warehouse.issues.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Issue Stock From Warehouse') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Warehouse') }}</label>
                            <select class="form-select" name="warehouse_id" required>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Agent') }}</label>
                            <select class="form-select" name="agent_profile_id" required>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_code }} - {{ $agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name }}</option>
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
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Quantity') }}</label>
                                <input type="number" step="0.01" class="form-control" name="quantity_issued" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('Unit Cost') }}</label>
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
@endsection
