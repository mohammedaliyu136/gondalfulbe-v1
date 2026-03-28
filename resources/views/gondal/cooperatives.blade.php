@extends('layouts.admin')

@section('page-title', __('Gondal Cooperatives'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Cooperatives') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('gondal.cooperatives', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-sm btn-primary">
        <i class="ti ti-file-export"></i> {{ __('Export CSV') }}
    </a>
@endsection

@section('content')
    @include('gondal.partials.nav')
    @include('gondal.partials.alerts')

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('gondal.cooperatives') }}">
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">{{ __('Search') }}</label>
                                <input type="text" class="form-control" name="search" value="{{ $search }}" placeholder="{{ __('Name, code, leader') }}">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">{{ __('MCC') }}</label>
                                <select class="form-control" name="mcc">
                                    <option value="all">{{ __('All MCCs') }}</option>
                                    @foreach ($mccOptions as $mcc)
                                        <option value="{{ $mcc }}" @selected($selectedMcc === $mcc)>{{ $mcc }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary w-100">{{ __('Apply') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('MCC') }}</th>
                                    <th>{{ __('Leader') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Members') }}</th>
                                    <th>{{ __('Avg Daily Supply') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cooperatives as $cooperative)
                                    <tr>
                                        <td>{{ $cooperative['code'] }}</td>
                                        <td>{{ $cooperative['name'] }}</td>
                                        <td>{{ $cooperative['mcc'] }}</td>
                                        <td>{{ $cooperative['leader_name'] }}</td>
                                        <td>{{ $cooperative['leader_phone'] }}</td>
                                        <td>{{ $cooperative['members_count'] }}</td>
                                        <td>{{ number_format($cooperative['avg_daily_supply'], 2) }} L</td>
                                        <td>
                                            <a href="{{ route('gondal.cooperatives.show', $cooperative['id']) }}" class="btn btn-sm bg-warning text-white">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Register Cooperative') }}</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('gondal.cooperatives.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('MCC') }}</label>
                            <input type="text" class="form-control" name="mcc" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Leader Name') }}</label>
                            <input type="text" class="form-control" name="leader_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Leader Phone') }}</label>
                            <input type="text" class="form-control" name="leader_phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Site Location') }}</label>
                            <input type="text" class="form-control" name="site_location" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Formation Date') }}</label>
                            <input type="date" class="form-control" name="formation_date">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select class="form-control" name="status" required>
                                <option value="active">{{ __('Active') }}</option>
                                <option value="inactive">{{ __('Inactive') }}</option>
                            </select>
                        </div>
                        <button class="btn btn-primary w-100">{{ __('Save Cooperative') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
