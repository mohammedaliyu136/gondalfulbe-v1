@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
    use Illuminate\Support\Facades\Storage;
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
    <style>
        .gs-rider-image-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .gs-rider-image-preview {
            width: 160px;
            height: 160px;
            border: 1px dashed #c7ced9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8f9fb;
            color: #8a94a6;
            text-align: center;
            font-size: 0.875rem;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .gs-rider-image-preview:hover {
            border-color: #51459d;
            background: #f2f4ff;
        }

        .gs-rider-image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gs-rider-image-input {
            display: none;
        }

        .gs-rider-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
        }

        .gs-rider-card {
            border: 1px solid #e6e9f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            text-decoration: none;
            display: block;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .gs-rider-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1);
        }

        .gs-rider-card-image {
            height: 220px;
            background: #f4f6fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .gs-rider-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gs-rider-card-placeholder {
            color: #8a94a6;
            font-size: 0.9rem;
        }

        .gs-rider-card-body {
            padding: 1rem 1rem 1.1rem;
            text-align: center;
        }

        .gs-rider-card-name {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
        }

        .gs-rider-card-phone {
            margin-top: 0.35rem;
            color: #6b7280;
            font-size: 0.95rem;
        }
    </style>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.querySelector('#createRiderModal input[name="photo"]');
            const preview = document.getElementById('riderImagePreview');

            if (!input || !preview) {
                return;
            }

            preview.addEventListener('click', function () {
                input.click();
            });

            preview.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    input.click();
                }
            });

            input.addEventListener('change', function (event) {
                const file = event.target.files && event.target.files[0];

                if (!file) {
                    preview.innerHTML = '<span>{{ __('Square preview') }}</span>';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (loadEvent) {
                    preview.innerHTML = '<img src="' + loadEvent.target.result + '" alt="{{ __('Rider Image Preview') }}">';
                };
                reader.readAsDataURL(file);
            });
        });
    </script>
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
                                        <th>{{ __('Milk Collection Center') }}</th>
                                        <th>{{ __('Rider') }}</th>
                                        <th>{{ __('Vehicle') }}</th>
                                        <th>{{ __('Liters') }}</th>
                                        <th>{{ __('Fuel') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trips as $trip)
                                        <tr>
                                            <td>{{ optional($trip->trip_date)->toDateString() }}</td>
                                            <td>{{ $trip->cooperative?->location ?: $trip->cooperative?->name ?: 'N/A' }}</td>
                                            <td>{{ $trip->rider?->name ?: 'N/A' }}</td>
                                            <td>{{ $trip->vehicle_name }}</td>
                                            <td>{{ number_format($trip->volume_liters, 2) }} L</td>
                                            <td>₦{{ number_format($trip->fuel_cost, 2) }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</td>
                                            <td>
                                                @if (GondalPermissionRegistry::can(auth()->user(), 'logistics', 'trips', 'manage') && $trip->status === 'completed' && !$trip->payment_batch_id)
                                                    <form method="POST" action="{{ route('gondal.logistics.trips.approve', $trip->id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="{{ __('Approve and Send to Payment') }}">
                                                            <i class="ti ti-check"></i> {{ __('Approve') }}
                                                        </button>
                                                    </form>
                                                @elseif ($trip->payment_batch_id)
                                                    <a href="{{ route('gondal.payments', ['tab' => 'batches']) }}" class="btn btn-sm btn-primary" title="{{ __('View Payment Batch') }}">
                                                        <i class="ti ti-credit-card"></i> {{ __('Payment') }}
                                                    </a>
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
            @else
                <div class="card">
                    <div class="card-body">
                        @if ($riders->isEmpty())
                            <p class="text-muted mb-0 text-center">{{ __('No rider') }}</p>
                        @else
                            <div class="gs-rider-card-grid">
                                @foreach ($riders as $rider)
                                    <a href="{{ route('gondal.logistics.riders.show', $rider->id) }}" class="gs-rider-card">
                                        <div class="gs-rider-card-image">
                                            @if ($rider->photo_path)
                                                <img src="{{ asset(Storage::url($rider->photo_path)) }}" alt="{{ $rider->name }}">
                                            @else
                                                <span class="gs-rider-card-placeholder">{{ __('No Image') }}</span>
                                            @endif
                                        </div>
                                        <div class="gs-rider-card-body">
                                            <h3 class="gs-rider-card-name">{{ $rider->name }}</h3>
                                            <p class="gs-rider-card-phone">{{ $rider->phone ?: __('N/A') }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
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
                                    ? __('Expected columns: trip_date, milk_collection_center_code (or cooperative_code), rider_code, vehicle_name, departure_time, arrival_time, volume_liters, distance_km, fuel_cost, status.')
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
                                <label class="form-label">{{ __('Milk Collection Center') }}</label>
                                <select class="form-control" name="cooperative_id" required>
                                    @foreach ($cooperatives as $cooperative)
                                        <option value="{{ $cooperative->id }}">{{ $cooperative->location ? $cooperative->location . ' - ' . $cooperative->name : $cooperative->name }}</option>
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
                            <p class="text-muted small mb-3">{{ __('Expected columns: code, name, phone, bank_name, account_number, account_name, bike_make, bike_model, bike_plate_number, identification_type, identification_number, status.') }}</p>
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
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.logistics.riders.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Create Rider') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="gs-rider-image-upload">
                                <label class="form-label mb-0">{{ __('Rider Image') }}</label>
                                <div class="gs-rider-image-preview" id="riderImagePreview" role="button" tabindex="0">
                                    <span>{{ __('Square preview') }}</span>
                                </div>
                                <input type="file" class="gs-rider-image-input" name="photo" accept="image/*">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" class="form-control" name="phone" value="{{ old('phone') }}">
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Bank Name') }}</label>
                                    <input type="text" class="form-control" name="bank_name" value="{{ old('bank_name') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Account Number') }}</label>
                                    <input type="text" class="form-control" name="account_number" value="{{ old('account_number') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Account Name') }}</label>
                                    <input type="text" class="form-control" name="account_name" value="{{ old('account_name') }}">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Bike Make') }}</label>
                                    <input type="text" class="form-control" name="bike_make" value="{{ old('bike_make') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Bike Model') }}</label>
                                    <input type="text" class="form-control" name="bike_model" value="{{ old('bike_model') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Plate Number') }}</label>
                                    <input type="text" class="form-control" name="bike_plate_number" value="{{ old('bike_plate_number') }}">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Identification Type') }}</label>
                                    <input type="text" class="form-control" name="identification_type" value="{{ old('identification_type') }}" placeholder="NIN, Voter Card, Driver License">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Identification Number') }}</label>
                                    <input type="text" class="form-control" name="identification_number" value="{{ old('identification_number') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Identification Document') }}</label>
                                    <input type="file" class="form-control" name="identification_document" accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select class="form-control" name="status" required>
                                    <option value="active" @selected(old('status', 'active') === 'active')>{{ __('Active') }}</option>
                                    <option value="inactive" @selected(old('status') === 'inactive')>{{ __('Inactive') }}</option>
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
