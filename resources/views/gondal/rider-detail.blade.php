@extends('layouts.admin')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('page-title', $rider->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.logistics', ['tab' => 'riders']) }}">{{ __('Logistics') }}</a></li>
    <li class="breadcrumb-item">{{ $rider->name }}</li>
@endsection

@push('script-page')
    <style>
        .gs-rider-profile-card {
            overflow: hidden;
        }

        .gs-rider-profile-cover {
            height: 88px;
            background: linear-gradient(135deg, #16324f 0%, #2b5876 55%, #4e4376 100%);
        }

        .gs-rider-profile-body {
            margin-top: -48px;
            padding: 0 1.5rem 1.5rem;
            text-align: center;
        }

        .gs-rider-profile-photo,
        .gs-rider-profile-placeholder {
            width: 128px;
            height: 128px;
            border-radius: 20px;
            border: 4px solid #fff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f5f8;
            overflow: hidden;
        }

        .gs-rider-profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gs-rider-profile-name {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: #17212f;
        }

        .gs-rider-profile-phone {
            margin: 0.35rem 0 1rem;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .gs-rider-profile-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            background: #f3f6fa;
            color: #425466;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }

        .gs-rider-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
            text-align: left;
        }

        .gs-rider-stat {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 0.9rem 1rem;
            background: #fbfcfe;
        }

        .gs-rider-stat-label {
            display: block;
            color: #7b8794;
            font-size: 0.78rem;
            margin-bottom: 0.35rem;
        }

        .gs-rider-stat-value {
            margin: 0;
            color: #17212f;
            font-size: 1rem;
            font-weight: 700;
        }
    </style>
@endpush

@section('content')
    @include('gondal.partials.alerts')

    <div class="row">
        <div class="col-md-4">
            <div class="card gs-rider-profile-card">
                <div class="gs-rider-profile-cover"></div>
                <div class="gs-rider-profile-body">
                    <div class="mb-0">
                        @if ($rider->photo_path)
                            <div class="gs-rider-profile-photo">
                                <img src="{{ asset(Storage::url($rider->photo_path)) }}" alt="{{ $rider->name }}">
                            </div>
                        @else
                            <div class="gs-rider-profile-placeholder text-muted">
                                {{ __('No Image') }}
                            </div>
                        @endif
                    </div>

                    <h4 class="gs-rider-profile-name">{{ $rider->name }}</h4>
                    <p class="gs-rider-profile-phone">{{ $rider->phone ?: __('N/A') }}</p>
                    <div class="gs-rider-profile-meta">
                        <span>{{ $rider->code }}</span>
                        <span>&bull;</span>
                        <span>{{ ucfirst((string) $rider->status) }}</span>
                    </div>

                    <div class="gs-rider-stat-grid">
                        <div class="gs-rider-stat">
                            <span class="gs-rider-stat-label">{{ __('Trips') }}</span>
                            <p class="gs-rider-stat-value">{{ number_format((int) $rider->trips_count) }}</p>
                        </div>
                        <div class="gs-rider-stat">
                            <span class="gs-rider-stat-label">{{ __('Last Trip') }}</span>
                            <p class="gs-rider-stat-value">{{ $lastTripDate ?: 'N/A' }}</p>
                        </div>
                        <div class="gs-rider-stat">
                            <span class="gs-rider-stat-label">{{ __('Total Liters') }}</span>
                            <p class="gs-rider-stat-value">{{ number_format($totalLiters, 2) }} L</p>
                        </div>
                        <div class="gs-rider-stat">
                            <span class="gs-rider-stat-label">{{ __('Total Fuel Cost') }}</span>
                            <p class="gs-rider-stat-value">₦{{ number_format($totalFuelCost, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Payment Information') }}</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">{{ __('Bank Name') }}</small>
                    <h6>{{ $rider->bank_name ?: 'N/A' }}</h6>
                    <small class="text-muted">{{ __('Account Number') }}</small>
                    <h6>{{ $rider->account_number ?: 'N/A' }}</h6>
                    <small class="text-muted">{{ __('Account Name') }}</small>
                    <h6>{{ $rider->account_name ?: 'N/A' }}</h6>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Bike & Identification') }}</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">{{ __('Bike Make') }}</small>
                    <h6>{{ $rider->bike_make ?: 'N/A' }}</h6>
                    <small class="text-muted">{{ __('Bike Model') }}</small>
                    <h6>{{ $rider->bike_model ?: 'N/A' }}</h6>
                    <small class="text-muted">{{ __('Plate Number') }}</small>
                    <h6>{{ $rider->bike_plate_number ?: 'N/A' }}</h6>
                    <small class="text-muted">{{ __('Identification Type') }}</small>
                    <h6>{{ $rider->identification_type ?: 'N/A' }}</h6>
                    <small class="text-muted">{{ __('Identification Number') }}</small>
                    <h6>{{ $rider->identification_number ?: 'N/A' }}</h6>
                    <small class="text-muted">{{ __('Identification Document') }}</small>
                    <h6>
                        @if ($rider->identification_document_path)
                            <a href="{{ asset(Storage::url($rider->identification_document_path)) }}" target="_blank">{{ __('View Document') }}</a>
                        @else
                            {{ __('N/A') }}
                        @endif
                    </h6>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{ __('Trip History') }}</h5>
                    <a href="{{ route('gondal.logistics.export', ['tab' => 'trips', 'rider_id' => $rider->id]) }}"
                        class="btn btn-sm btn-light"
                        title="{{ __('Export Trip History') }}">
                        <i class="ti ti-file-export"></i>
                    </a>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Milk Collection Center') }}</th>
                                    <th>{{ __('Vehicle') }}</th>
                                    <th>{{ __('Liters') }}</th>
                                    <th>{{ __('Fuel') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($trips as $trip)
                                    <tr>
                                        <td>{{ optional($trip->trip_date)->toDateString() }}</td>
                                        <td>{{ $trip->cooperative?->location ?: $trip->cooperative?->name ?: 'N/A' }}</td>
                                        <td>{{ $trip->vehicle_name ?: 'N/A' }}</td>
                                        <td>{{ number_format((float) $trip->volume_liters, 2) }} L</td>
                                        <td>₦{{ number_format((float) $trip->fuel_cost, 2) }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', (string) $trip->status)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ __('No trips recorded yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
