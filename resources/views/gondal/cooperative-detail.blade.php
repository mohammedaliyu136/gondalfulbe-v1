@extends('layouts.admin')

@section('page-title', $cooperative->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.cooperatives') }}">{{ __('Cooperatives') }}</a></li>
    <li class="breadcrumb-item">{{ $cooperative->name }}</li>
@endsection

@section('content')
    @include('gondal.partials.nav')
    @include('gondal.partials.alerts')

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">{{ __('Code') }}</small>
                    <h5>{{ $cooperative->code }}</h5>
                    <small class="text-muted">{{ __('MCC') }}</small>
                    <h5>{{ $cooperative->location ?: 'N/A' }}</h5>
                    <small class="text-muted">{{ __('Leader') }}</small>
                    <h5>{{ $cooperative->leader_name ?: 'N/A' }}</h5>
                    <small class="text-muted">{{ __('Phone') }}</small>
                    <h5>{{ $cooperative->leader_phone ?: 'N/A' }}</h5>
                    <small class="text-muted">{{ __('Site') }}</small>
                    <h5>{{ $cooperative->site_location ?: 'N/A' }}</h5>
                    <small class="text-muted">{{ __('Total Liters') }}</small>
                    <h5>{{ number_format($totalLiters, 2) }} L</h5>
                    <small class="text-muted">{{ __('Last Collection') }}</small>
                    <h5>{{ $lastCollectionDate ?: 'N/A' }}</h5>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Registered Farmers') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Gender') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($farmers as $farmer)
                                    <tr>
                                        <td>FARM-{{ str_pad($farmer->vender_id, 3, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $farmer->name }}</td>
                                        <td>{{ ucfirst((string) $farmer->gender) }}</td>
                                        <td>{{ $farmer->contact }}</td>
                                        <td>{{ ucfirst((string) $farmer->status) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">{{ __('No farmers assigned yet.') }}</td>
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
