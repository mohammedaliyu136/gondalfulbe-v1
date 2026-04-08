@extends('layouts.admin')

@section('page-title', $dashboardTitle)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ $dashboardTitle }}</li>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-uppercase small text-muted fw-bold">{{ $roleLabel }}</div>
                <h3 class="mb-1">{{ $dashboardTitle }}</h3>
                <p class="text-muted mb-0">{{ $dashboardSubtitle }}</p>
            </div>
            <a href="{{ $standardDashboardUrl }}" class="btn btn-outline-secondary">
                <i class="ti ti-layout-dashboard me-1"></i>{{ __('Standard Dashboard') }}
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">{{ __('From Date') }}</label>
                    <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">{{ __('To Date') }}</label>
                    <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">{{ __('Project / Program ID') }}</label>
                    <input type="number" name="project_id" class="form-control" placeholder="Optional" value="{{ request('project_id') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">{{ __('MCC ID') }}</label>
                    <input type="number" name="mcc_id" class="form-control" placeholder="Optional" value="{{ request('mcc_id') }}">
                </div>
                <div class="col-md-12 text-end mt-3">
                    @if(request()->hasAny(['from', 'to', 'project_id', 'mcc_id', 'status']))
                        <a href="{{ url()->current() }}" class="btn btn-light border me-2">{{ __('Clear Filters') }}</a>
                    @endif
                    <button type="submit" class="btn btn-primary px-4 bg-gradient">
                        <i class="ti ti-filter me-1"></i>{{ __('Apply Filters') }}
                    </button>
                    @if(request()->is('*/role/*'))
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success ms-2">
                            <i class="ti ti-download me-1"></i>{{ __('Export CSV') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        @foreach ($cards as $card)
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-uppercase small text-muted fw-bold">{{ $card['label'] }}</div>
                        <div class="fs-4 fw-bold text-dark mt-2">{{ $card['value'] }}</div>
                        <div class="text-muted small mt-2">{{ $card['meta'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Quick Links') }}</h6>
                </div>
                <div class="card-body d-grid gap-2">
                    @foreach ($quickLinks as $link)
                        <a href="{{ $link['url'] }}" class="btn btn-light text-start border">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Action Queue') }}</h6>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    @forelse ($recentQueue as $item)
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold">{{ $item['title'] }}</div>
                                    <div class="small text-muted">{{ $item['meta'] }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">{{ $item['value'] }}</div>
                                    <div class="small text-muted">{{ $item['status'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No items to show.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Watch List') }}</h6>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    @forelse ($secondaryQueue as $item)
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold">{{ $item['title'] }}</div>
                                    <div class="small text-muted">{{ $item['meta'] }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">{{ $item['value'] }}</div>
                                    <div class="small text-muted">{{ $item['status'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No watch list items right now.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
