@extends('layouts.admin')
@php
$profile = asset(Storage::url('uploads/avatar/'));
@endphp
@push('script-page')
    <script>
        $(document).on('click', '#billing_data', function() {
            $("[name='shipping_name']").val($("[name='billing_name']").val());
            $("[name='shipping_country']").val($("[name='billing_country']").val());
            $("[name='shipping_state']").val($("[name='billing_state']").val());
            $("[name='shipping_city']").val($("[name='billing_city']").val());
            $("[name='shipping_phone']").val($("[name='billing_phone']").val());
            $("[name='shipping_zip']").val($("[name='billing_zip']").val());
            $("[name='shipping_address']").val($("[name='billing_address']").val());
        })
    </script>
@endpush
@section('page-title')
    {{ __('Manage Vendors') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('Vendor')}}</li>
@endsection
@section('action-btn')
    <div class="float-end d-flex">
        <a href="#" class="btn btn-sm bg-brown-subtitle me-2" data-url="{{ route('vender.file.import') }}" data-ajax-popup="true" data-bs-toggle="tooltip"
           title="{{ __('Import Vendor CSV File') }}">
            <i class="ti ti-file-import"></i>
        </a>

        <a href="{{ route('vender.export') }}" class="btn btn-sm btn-secondary me-2" data-bs-toggle="tooltip" title="{{ __('Export') }}">
            <i class="ti ti-file-export"></i>
        </a>
        @can('create vender')
            <a href="#" data-size="lg" data-url="{{ route('vender.create') }}" data-ajax-popup="true" data-title="{{__('Create New Vendor')}}" data-bs-toggle="tooltip" title="{{ __('Create') }}" class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endcan

    </div>
@endsection
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="mt-2" id="multiCollapseExample1">
                <div class="card">
                    <div class="card-body">
                        {{ Form::open(array('route' => array('vender.index'),'method' => 'GET','id'=>'vender_filter')) }}
                        <div class="row align-items-center justify-content-end">
                            <div class="col-xl-10">
                                <div class="row">
                                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('gender', __('Gender'),['class'=>'form-label'])}}
                                            {{ Form::select('gender', ['' => 'All', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'],isset($_GET['gender'])?$_GET['gender']:'', array('class' => 'form-control select')) }}
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('status', __('Status'),['class'=>'form-label'])}}
                                            {{ Form::select('status',  ['' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive', 'Suspended' => 'Suspended'],isset($_GET['status'])?$_GET['status']:'', array('class' => 'form-control select')) }}
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('cooperative_id', __('Cooperative'),['class'=>'form-label'])}}
                                            {{ Form::select('cooperative_id', ['' => 'All'] + $cooperatives,isset($_GET['cooperative_id'])?$_GET['cooperative_id']:'', array('class' => 'form-control select')) }}
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                        <div class="btn-box">
                                            {{ Form::label('registration_date', __('Registration Date'),['class'=>'form-label'])}}
                                            {{ Form::text('registration_date', isset($_GET['registration_date'])?$_GET['registration_date']:null, array('class' => 'form-control flatpickr-to-input', 'placeholder' => 'Select Date Range')) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="row">
                                    <div class="col-auto mt-4">
                                        <a href="#" class="btn btn-sm btn-primary" onclick="document.getElementById('vender_filter').submit(); return false;" data-bs-toggle="tooltip" title="{{__('Apply')}}" data-original-title="{{__('apply')}}">
                                            <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                                        </a>
                                        <a href="{{route('vender.index')}}" class="btn btn-sm btn-danger " data-bs-toggle="tooltip"  title="{{ __('Reset') }}" data-original-title="{{__('Reset')}}">
                                            <span class="btn-inner--icon"><i class="ti ti-trash-restore text-white-off "></i></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Contact') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Balance') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($venders as $k => $Vender)
                                    <tr class="cust_tr" id="vend_detail">
                                        <td class="Id">
                                            @can('show vender')
                                                <a href="{{ route('vender.show', \Crypt::encrypt($Vender['id'])) }}" class="btn btn-outline-primary">
                                                    {{ AUth::user()->venderNumberFormat($Vender['vender_id']) }}
                                                </a>
                                            @else
                                                <a href="#" class="btn btn-outline-primary"> {{ AUth::user()->venderNumberFormat($Vender['vender_id']) }}
                                                </a>
                                            @endcan
                                        </td>
                                        <td>{{ $Vender['name'] }}</td>
                                        <td>{{ $Vender['contact'] }}</td>
                                        <td>{{ $Vender['email'] }}</td>
                                        <td>{{ \Auth::user()->priceFormat($Vender['balance']) }}</td>
                                        <td class="Action">
                                            <span>
                                                    @if ($Vender['is_active'] == 0)
                                                        <i class="fa fa-lock" title="Inactive"></i>
                                                    @else
                                                        @can('show vender')
                                                            <div class="action-btn me-2">
                                                                <a href="{{ route('vender.show', \Crypt::encrypt($Vender['id'])) }}"
                                                                    class="mx-3 btn btn-sm align-items-center bg-warning" data-bs-toggle="tooltip"
                                                                    title="{{ __('View') }}">
                                                                    <i class="ti ti-eye text-white text-white"></i>
                                                                </a>
                                                            </div>
                                                        @endcan
                                                        @can('edit vender')
                                                            <div class="action-btn me-2">
                                                                <a href="#" class="mx-3 btn btn-sm align-items-center bg-info" data-size="lg"
                                                                data-title="{{__('Edit Vendor')}}"
                                                                    data-url="{{ route('vender.edit', $Vender['id']) }}"
                                                                    data-ajax-popup="true" title="{{ __('Edit') }}"
                                                                    data-bs-toggle="tooltip" data-original-title="{{ __('Edit') }}">
                                                                    <i class="ti ti-pencil text-white"></i>
                                                                </a>
                                                            </div>
                                                        @endcan
                                                        @can('delete vender')
                                                            <div class="action-btn ">
                                                                {!! Form::open(['method' => 'DELETE', 'route' => ['vender.destroy', $Vender['id']], 'id' => 'delete-form-' . $Vender['id']]) !!}
                                                                    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger" data-bs-toggle="tooltip"
                                                                           data-original-title="{{ __('Delete') }}" title="{{ __('Delete') }}"
                                                                           data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                                                                           data-confirm-yes="document.getElementById('delete-form-{{ $Vender['id'] }}').submit();">
                                                                        <i class="ti ti-trash text-white text-white"></i>
                                                                    </a>
                                                                {!! Form::close() !!}
                                                            </div>
                                                        @endcan
                                                @endif
                                            </span>
                                        </td>
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
