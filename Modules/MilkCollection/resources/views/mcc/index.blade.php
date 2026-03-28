@extends('layouts.admin')
@section('page-title')
    {{__('Milk Collection Centers')}}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item"><a href="{{route('milkcollection.index')}}">{{__('Milk Collections')}}</a></li>
    <li class="breadcrumb-item">{{__('MCCs')}}</li>
@endsection

@section('action-btn')
    <div class="row">
        <div class="col-auto float-end ms-auto">
            @can('create mcc')
                <a href="#" data-size="md" data-url="{{ route('mcc.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Create MCC')}}" class="btn btn-sm btn-primary">
                    <i class="ti ti-plus"></i>
                </a>
            @endcan
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                            <tr>
                                <th>{{__('Name')}}</th>
                                <th>{{__('Location')}}</th>
                                <th>{{__('Contact Number')}}</th>
                                <th width="200px">{{__('Action')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($mccs as $mcc)
                                <tr>
                                    <td>{{ $mcc->name }}</td>
                                    <td>{{ $mcc->location }}</td>
                                    <td>{{ $mcc->contact_number }}</td>
                                    <td class="Action">
                                        @can('edit mcc')
                                            <div class="action-btn bg-info align-items-center justify-content-center d-inline-flex ms-2">
                                                <a href="#" class="mx-3 btn btn-sm align-items-center" data-url="{{ route('mcc.edit',$mcc->id) }}" data-ajax-popup="true" data-title="{{__('Edit MCC')}}" data-bs-toggle="tooltip" title="{{__('Edit')}}" data-original-title="{{__('Edit')}}">
                                                    <i class="ti ti-pencil text-white"></i>
                                                </a>
                                            </div>
                                        @endcan
                                        @can('delete mcc')
                                            <div class="action-btn bg-danger align-items-center justify-content-center d-inline-flex ms-2">
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['mcc.destroy', $mcc->id],'id'=>'delete-form-'.$mcc->id]) !!}
                                                <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para" data-bs-toggle="tooltip" title="{{__('Delete')}}" data-original-title="{{__('Delete')}}" data-confirm="{{__('Are You Sure?').'|'.__('This action can not be undone. Do you want to continue?')}}" data-confirm-yes="document.getElementById('delete-form-{{$mcc->id}}').submit();">
                                                    <i class="ti ti-trash text-white"></i>
                                                </a>
                                                {!! Form::close() !!}
                                            </div>
                                        @endcan
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
