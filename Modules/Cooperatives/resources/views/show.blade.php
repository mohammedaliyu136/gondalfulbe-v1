@extends('layouts.admin')

@section('page-title')
    {{ __('Cooperative Details') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item"><a href="{{route('cooperatives.index')}}">{{__('Cooperatives')}}</a></li>
    <li class="breadcrumb-item">{{ $cooperative->name }}</li>
@endsection

@section('action-btn')
    <div class="row">
        <div class="col-auto float-end ms-auto">
            <a href="{{ route('cooperatives.farmers.export', $cooperative->id) }}" data-bs-toggle="tooltip" title="{{__('Export Linked Farmers')}}" class="btn btn-sm btn-primary">
                <i class="ti ti-file-export"></i> {{ __('Export Farmers') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pb-0">
                    <h4 class="mb-0">{{ $cooperative->name }} - {{ __('Registered Farmers') }} ({{ $cooperative->farmers->count() }})</h4>
                </div>
                <div class="card-body table-border-style mt-3">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Avatar') }}</th>
                                    <th>{{ __('Farmer ID') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Gender') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Registered Date') }}</th>
                                    <th>{{ __('Avg Supply') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cooperative->farmers as $farmer)
                                    <tr>
                                        <td>
                                            @if(!empty($farmer->avatar))
                                                <a href="{{ asset(Storage::url('uploads/avatar/'.$farmer->avatar)) }}" target="_blank">
                                                    <img src="{{ asset(Storage::url('uploads/avatar/'.$farmer->avatar)) }}" class="rounded-circle" style="width: 40px; height: 40px;" alt="">
                                                </a>
                                            @else
                                                <img src="{{ asset(Storage::url('uploads/avatar/avatar.png')) }}" class="rounded-circle" style="width: 40px; height: 40px;" alt="">
                                            @endif
                                        </td>
                                        <td>
                                            @can('show vender')
                                                <a href="{{ route('vender.show', $farmer->id) }}" class="btn btn-outline-primary">{{ \Auth::user()->venderNumberFormat($farmer->vender_id) }}</a>
                                            @else
                                                <a href="#" class="btn btn-outline-primary">{{ \Auth::user()->venderNumberFormat($farmer->vender_id) }}</a>
                                            @endcan
                                        </td>
                                        <td>{{ $farmer->name }}</td>
                                        <td>{{ $farmer->gender }}</td>
                                        <td>
                                            @if($farmer->is_active == 1)
                                                <span class="badge bg-success p-2 px-3">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-danger p-2 px-3">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ \Auth::user()->dateFormat($farmer->created_at) }}</td>
                                        <td>0.00 L</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
