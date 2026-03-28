@extends('layouts.admin')

@section('page-title', __('Gondal Audit Log'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Audit Log') }}</li>
@endsection

@section('content')
    @include('gondal.partials.nav')
    @include('gondal.partials.alerts')

    <div class="card">
        <div class="card-body table-border-style">
            <div class="table-responsive">
                <table class="table datatable">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Module') }}</th>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Context') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at }}</td>
                                <td>{{ $log->module }}</td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->user?->name ?: 'System' }}</td>
                                <td><small>{{ json_encode($log->context) }}</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
