@extends('layouts.admin')
@section('page-title')
    {{__('Milk Collections')}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('Milk Collections')}}</li>
@endsection
@section('action-btn')
    <div class="row">
        <div class="col-auto float-end ms-auto">
            @can('manage mcc')
                <a href="{{ route('mcc.index') }}" data-bs-toggle="tooltip" title="{{__('Manage MCC')}}" class="btn btn-sm btn-info">
                    <i class="ti ti-building"></i> {{__('Collection Centers')}}
                </a>
            @endcan
            @can('manage vender')
                <a href="#" data-size="md" data-url="{{ route('milkcollection.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Quick Entry')}}" class="btn btn-sm btn-primary">
                    <i class="ti ti-plus"></i> {{__('Record Milk')}}
                </a>
            @endcan
        </div>
    </div>
@endsection

@section('content')

    <div class="row">
        <div class="col-sm-12">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center justify-content-between">
                                <div class="col-auto mb-3 mb-sm-0">
                                    <div class="d-flex align-items-center">
                                        <div class="theme-avtar bg-primary">
                                            <i class="ti ti-droplet"></i>
                                        </div>
                                        <div class="ms-3">
                                            <small class="text-muted">{{__('Total Litres (Filtered)')}}</small>
                                            <h6 class="m-0">{{ number_format($totalLitres, 2) }} L</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center justify-content-between">
                                <div class="col-auto mb-3 mb-sm-0">
                                    <div class="d-flex align-items-center">
                                        <div class="theme-avtar bg-info">
                                            <i class="ti ti-users"></i>
                                        </div>
                                        <div class="ms-3">
                                            <small class="text-muted">{{__('Active Farmers')}}</small>
                                            <h6 class="m-0">{{ $uniqueFarmersCount }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center justify-content-between">
                                <div class="col-auto mb-3 mb-sm-0">
                                    <div class="d-flex align-items-center">
                                        <div class="theme-avtar bg-success">
                                            <i class="ti ti-award"></i>
                                        </div>
                                        <div class="ms-3">
                                            <small class="text-muted">{{__('Avg Quality Grade')}}</small>
                                            <h6 class="m-0">Grade {{ $avgQuality }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="col-sm-12 mt-2">
            <div class="card">
                <div class="card-body">
                    {{ Form::open(array('route' => array('milkcollection.index'),'method' => 'GET','id'=>'frm_submit')) }}
                    <div class="row align-items-center justify-content-end">
                        <div class="col-xl-10">
                            <div class="row">
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                    <div class="btn-box">
                                        {{ Form::select('mcc_id', [''=>'All MCCs'] + $mccs, isset($_GET['mcc_id'])?$_GET['mcc_id']:'', array('class' => 'form-control select')) }}
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                    <div class="btn-box">
                                        {{ Form::select('farmer_id', $farmers, isset($_GET['farmer_id'])?$_GET['farmer_id']:'', array('class' => 'form-control select')) }}
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                    <div class="btn-box">
                                        {{ Form::select('quality_grade', [''=>'All Grades','A'=>'Grade A', 'B'=>'Grade B', 'C'=>'Grade C (Rejected)'], isset($_GET['quality_grade'])?$_GET['quality_grade']:'', array('class' => 'form-control select')) }}
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                    <div class="btn-box d-flex">
                                        {{ Form::date('start_date', isset($_GET['start_date'])?$_GET['start_date']:'', array('class' => 'form-control', 'placeholder' => 'Start Date')) }}
                                        <span class="mx-2 mt-2">-</span>
                                        {{ Form::date('end_date', isset($_GET['end_date'])?$_GET['end_date']:'', array('class' => 'form-control', 'placeholder' => 'End Date')) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="row">
                                <div class="col-auto mt-4">
                                    <button type="submit" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{__('Apply')}}"><i class="ti ti-search text-white"></i></button>
                                    <a href="{{ route('milkcollection.index') }}" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="{{__('Reset')}}"><i class="ti ti-trash-off text-white"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{ Form::close() }}
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
                                <th>{{__('Batch ID')}}</th>
                                <th>{{__('Date')}}</th>
                                <th>{{__('MCC')}}</th>
                                <th>{{__('Farmer')}}</th>
                                <th>{{__('Quantity (L)')}}</th>
                                <th>{{__('Fat %')}}</th>
                                <th>{{__('Temp °C')}}</th>
                                <th>{{__('Grade')}}</th>
                                <th>{{__('Recorded By')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($collections as $collection)
                                <tr>
                                    <td>{{ $collection->batch_id }}</td>
                                    <td>{{ \Auth::user()->dateFormat($collection->collection_date) }}</td>
                                    <td>{{ $collection->mcc_id }}</td>
                                    <td>{{ !empty($collection->farmer)?$collection->farmer->name:'-' }}</td>
                                    <td>{{ $collection->quantity }} L</td>
                                    <td>{{ $collection->fat_percentage ? $collection->fat_percentage.'%' : '-' }}</td>
                                    <td>{{ $collection->temperature ? $collection->temperature.'°C' : '-' }}</td>
                                    <td>
                                        @if($collection->quality_grade == 'A')
                                            <span class="badge bg-success p-2 px-3 rounded">Grade A</span>
                                        @elseif($collection->quality_grade == 'B')
                                            <span class="badge bg-warning p-2 px-3 rounded">Grade B</span>
                                        @elseif($collection->quality_grade == 'C')
                                            <span class="badge bg-danger p-2 px-3 rounded" data-bs-toggle="tooltip" title="{{$collection->rejection_reason}}">Grade C (Rejected)</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ !empty($collection->recorder)?$collection->recorder->name:'-' }}</td>
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
