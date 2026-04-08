@extends('layouts.admin')

@push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const locationHierarchy = @json($warehouseLocationHierarchy ?? []);
            const stateSelect = document.getElementById('ossState');
            const lgaSelect = document.getElementById('ossLga');
            const communitySelect = document.getElementById('ossCommunity');

            const renderOptions = (select, options, placeholder, selectedValue = '') => {
                if (!select) {
                    return;
                }

                select.innerHTML = '';

                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                placeholderOption.selected = selectedValue === '';
                select.appendChild(placeholderOption);

                options.forEach((optionValue) => {
                    const option = document.createElement('option');
                    option.value = optionValue.value ?? optionValue;
                    option.textContent = optionValue.label ?? optionValue;
                    option.selected = String(option.value) === String(selectedValue);
                    select.appendChild(option);
                });
            };

            const syncOssLocationOptions = () => {
                if (!stateSelect || !lgaSelect || !communitySelect) {
                    return;
                }

                const selectedState = stateSelect.value;
                const stateData = locationHierarchy[selectedState] || {};
                const lgas = Object.keys(stateData);
                const keepLga = lgas.includes(lgaSelect.value) ? lgaSelect.value : '';

                renderOptions(lgaSelect, lgas, '{{ __('Select LGA') }}', keepLga);

                const communities = keepLga ? (stateData[keepLga] || []) : [];
                const communityOptions = communities.map((community) => ({
                    value: community.id,
                    label: community.name,
                }));
                const keepCommunity = communityOptions.some((community) => String(community.value) === String(communitySelect.value))
                    ? communitySelect.value
                    : '';

                renderOptions(communitySelect, communityOptions, '{{ __('Select community') }}', keepCommunity);
            };

            stateSelect?.addEventListener('change', syncOssLocationOptions);
            lgaSelect?.addEventListener('change', syncOssLocationOptions);
            syncOssLocationOptions();
        });
    </script>
@endpush

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
                        {{ $tab === 'registry' ? __('Manage the core warehouses and one-stop shops serving Gondal inventory.') : ($tab === 'stock' ? __('Load inventory into a selected warehouse.') : ($tab === 'outside' ? __('Track stock already outside the warehouse, especially what is currently held at one-stop shops.') : __('Dispatch stock from a warehouse to a one-stop shop and keep inventory synchronized.'))) }}
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if ($tab === 'registry')
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createWarehouseModal">
                            <i class="ti ti-plus me-1"></i>{{ __('Add Warehouse') }}
                        </button>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createOneStopShopModal">
                            <i class="ti ti-building-store me-1"></i>{{ __('Add One-Stop Shop') }}
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
                                    <th>{{ __('Location') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Address') }}</th>
                                    <th>{{ __('Coverage') }}</th>
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
                                        <td>{{ __('Warehouse') }}</td>
                                        <td>{{ $warehouse->address }}</td>
                                        <td>{{ $warehouse->city }} {{ $warehouse->city_zip }}</td>
                                        <td>{{ number_format($warehouseRows->pluck('inventory_item_id')->unique()->count()) }}</td>
                                        <td>{{ number_format((float) $warehouseRows->sum('quantity'), 2) }}</td>
                                    </tr>
                                @endforeach
                                @foreach ($oneStopShops as $shop)
                                    @php
                                        $shopRows = $oneStopShopStocks->where('one_stop_shop_id', $shop->id);
                                    @endphp
                                    <tr>
                                        <td class="fw-bold">{{ $shop->name }}</td>
                                        <td>{{ __('One-Stop Shop') }}</td>
                                        <td>{{ $shop->address ?: '-' }}</td>
                                        <td>{{ $shop->community?->name ?: collect([$shop->lga, $shop->state])->filter()->implode(', ') ?: '-' }}</td>
                                        <td>{{ number_format($shopRows->pluck('inventory_item_id')->unique()->count()) }}</td>
                                        <td>{{ number_format((float) $shopRows->sum('quantity'), 2) }}</td>
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
                                    <th>{{ __('One-Stop Shop') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Issued Out') }}</th>
                                    <th>{{ __('Transferred Out') }}</th>
                                    <th>{{ __('Current OSS Stock') }}</th>
                                    <th>{{ __('Pending Reconciliation') }}</th>
                                    <th>{{ __('Latest Update') }}</th>
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
                                            {{ __('No stock is currently held at one-stop shops.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        @else
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Reference') }}</th>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('One-Stop Shop') }}</th>
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
                                        <td>{{ $dispatch->oneStopShop?->name ?: '-' }}</td>
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
                        <h5 class="modal-title">{{ __('Dispatch Stock To One-Stop Shop') }}</h5>
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
                            <label class="form-label">{{ __('One-Stop Shop') }}</label>
                            <select class="form-select" name="one_stop_shop_id" required>
                                @foreach ($oneStopShops as $shop)
                                    <option value="{{ $shop->id }}">{{ $shop->name }}</option>
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
                        <button class="btn btn-primary">{{ __('Dispatch Stock') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createOneStopShopModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.one-stop-shops.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create One-Stop Shop') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Linked Warehouse') }}</label>
                            <select class="form-select" name="warehouse_id">
                                <option value="">{{ __('Select warehouse') }}</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('State') }}</label>
                                <select class="form-select" id="ossState" name="state">
                                    <option value="">{{ __('Select state') }}</option>
                                    @foreach ($warehouseStateOptions as $state)
                                        <option value="{{ $state }}">{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">{{ __('LGA') }}</label>
                                <select class="form-select" id="ossLga" name="lga">
                                    <option value="">{{ __('Select LGA') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Community') }}</label>
                            <select class="form-select" id="ossCommunity" name="community_id">
                                <option value="">{{ __('Select community') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Address') }}</label>
                            <textarea class="form-control" name="address" rows="3"></textarea>
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
                        <button class="btn btn-primary">{{ __('Save One-Stop Shop') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
