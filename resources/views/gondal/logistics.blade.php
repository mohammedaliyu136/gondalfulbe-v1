@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
@endphp

@section('page-title')
    {{ __('Manage Logistics') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Logistics') }}</li>
@endsection

@section('action-btn')
    @php
        $addLabel = $tab === 'trips' ? __('Add Trip (Trips)') : __('Add Rider (Riders)');
    @endphp
    <div class="float-end d-flex">
        @if (GondalPermissionRegistry::can(auth()->user(), 'logistics', $tab, 'import'))
            <button type="button" class="btn btn-sm btn-secondary me-2" data-bs-toggle="modal"
                title="{{ $tab === 'trips' ? __('Import Trips CSV') : __('Import Riders CSV') }}"
                data-bs-target="#importLogisticsModal">
                <i class="ti ti-file-import"></i>
            </button>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'logistics', $tab, 'export'))
            <a href="{{ route('gondal.logistics.export', array_merge(request()->query(), ['tab' => $tab])) }}"
                class="btn btn-sm btn-secondary me-2" title="{{ __('Export') }}">
                <i class="ti ti-file-export"></i>
            </a>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'logistics', $tab, 'create'))
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                title="{{ $addLabel }}"
                data-bs-target="#{{ $tab === 'trips' ? 'recordTripModal' : 'createRiderModal' }}">
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
                const modalElement = document.getElementById('{{ $tab === 'trips' ? 'recordTripModal' : 'createRiderModal' }}');

                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
    @if ($errors->hasBag('import') && $errors->import->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('importLogisticsModal');

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
        @foreach ($cards as $card)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ __($card['label']) }}</small>
                        <h4 class="mb-0 mt-2">{{ $card['value'] }}</h4>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex gap-2 mb-4">
        @foreach ($visibleTabs as $visibleTab)
            <a href="{{ route('gondal.logistics', ['tab' => $visibleTab['key']]) }}"
                class="btn btn-sm {{ $tab === $visibleTab['key'] ? 'btn-primary' : 'btn-light' }}">
                {{ __($visibleTab['label']) }}
            </a>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-12">
            @if ($tab === 'trips')
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Center') }}</th>
                                        <th>{{ __('Rider') }}</th>
                                        <th>{{ __('Vehicle') }}</th>
                                        <th>{{ __('Liters') }}</th>
                                        <th>{{ __('Fuel') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trips as $trip)
                                        <tr>
                                            <td>{{ optional($trip->trip_date)->toDateString() }}</td>
                                            <td>{{ $trip->cooperative?->name ?: 'N/A' }}</td>
                                            <td>{{ $trip->rider?->name ?: 'N/A' }}</td>
                                            <td>{{ $trip->vehicle_name }}</td>
                                            <td>{{ number_format($trip->volume_liters, 2) }} L</td>
                                            <td>₦{{ number_format($trip->fuel_cost, 2) }}</td>
                                            <td>{{ ucfirst($trip->status) }}</td>
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
                                        <th>{{ __('Code') }}</th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Phone') }}</th>
                                        <th>{{ __('Trips') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($riders as $rider)
                                        <tr>
                                            <td>{{ $rider->code }}</td>
                                            <td>{{ $rider->name }}</td>
                                            <td>{{ $rider->phone ?: 'N/A' }}</td>
                                            <td>{{ $rider->trips->count() }}</td>
                                            <td>{{ ucfirst($rider->status) }}</td>
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

    @if ($tab === 'trips')
        <div class="modal fade" id="importLogisticsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.logistics.import') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $tab === 'trips' ? __('Import Trips CSV') : __('Import Riders CSV') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                {{ $tab === 'trips'
                                    ? __('Expected columns: trip_date, cooperative_code, rider_code, vehicle_name, departure_time, arrival_time, volume_liters, distance_km, fuel_cost, status.')
                                    : __('Expected columns: code, name, phone, status.') }}
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

        <div class="modal fade" id="recordTripModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.logistics.trips.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Record Trip') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Trip Date') }}</label>
                                <input type="date" class="form-control" name="trip_date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Cooperative') }}</label>
                                <select class="form-control" name="cooperative_id" required>
                                    @foreach ($cooperatives as $cooperative)
                                        <option value="{{ $cooperative->id }}">{{ $cooperative->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Rider') }}</label>
                                <select class="form-control" name="rider_id" required>
                                    @foreach ($riders as $rider)
                                        <option value="{{ $rider->id }}">{{ $rider->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Vehicle') }}</label>
                                <input type="text" class="form-control" name="vehicle_name" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Departure') }}</label>
                                    <input type="text" class="form-control" name="departure_time" placeholder="07:30" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Arrival') }}</label>
                                    <input type="text" class="form-control" name="arrival_time" placeholder="09:15" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Liters') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="volume_liters" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Distance') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="distance_km" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Fuel') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="fuel_cost" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select class="form-control" name="status" required>
                                    @foreach (['scheduled', 'in_transit', 'completed', 'cancelled'] as $status)
                                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button class="btn btn-primary">{{ __('Save Trip') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="modal fade" id="importLogisticsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.logistics.import') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Import Riders CSV') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">{{ __('Expected columns: code, name, phone, status.') }}</p>
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

        <div class="modal fade" id="createRiderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.logistics.riders.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Create Rider') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select class="form-control" name="status" required>
                                    <option value="active">{{ __('Active') }}</option>
                                    <option value="inactive">{{ __('Inactive') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button class="btn btn-primary">{{ __('Save Rider') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
