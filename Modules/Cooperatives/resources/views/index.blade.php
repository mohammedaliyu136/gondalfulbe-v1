@extends('layouts.admin')

@section('page-title')
    {{ __('Manage Cooperatives') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('Cooperatives')}}</li>
@endsection

@section('action-btn')
    <div class="row">
        <div class="col-auto float-end ms-auto">
            <a href="#" data-size="md"  data-bs-toggle="tooltip" title="{{__('Import')}}" data-url="{{ route('cooperatives.file.import') }}" data-ajax-popup="true" data-title="{{__('Import Cooperative CSV file')}}" class="btn btn-sm btn-primary">
                <i class="ti ti-file-import"></i>
            </a>
            <a href="{{route('cooperatives.export')}}" data-bs-toggle="tooltip" title="{{__('Export')}}" class="btn btn-sm btn-primary">
                <i class="ti ti-file-export"></i>
            </a>
            @can('manage vender')
                <a href="#" data-size="lg" data-url="{{ route('cooperatives.create') }}" data-ajax-popup="true" data-bs-toggle="tooltip" title="{{__('Create')}}" class="btn btn-sm btn-primary">
                    <i class="ti ti-plus"></i>
                </a>
            @endcan
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Location') }}</th>
                                    <th>{{ __('Leader Name') }}</th>
                                    <th>{{ __('Leader Phone') }}</th>
                                    <th>{{ __('Formation Date') }}</th>
                                    <th>{{ __('Avg Daily Supply') }}</th>
                                    <th>{{ __('Members') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cooperatives as $coop)
                                    <tr>
                                        <td>{{ $coop->name }}</td>
                                        <td>{{ $coop->location }}</td>
                                        <td>{{ $coop->leader_name }}</td>
                                        <td>{{ $coop->leader_phone }}</td>
                                        <td>{{ $coop->formation_date }}</td>
                                        <td>{{ $coop->average_daily_supply }}</td>
                                        <td>{{ $coop->farmers_count }}</td>
                                        <td class="Action">
                                            <span>
                                                @can('manage vender')
                                                    <div class="action-btn me-2">
                                                        <a href="{{ route('cooperatives.show', $coop->id) }}" class="mx-3 btn btn-sm align-items-center bg-warning" data-bs-toggle="tooltip" title="{{__('View')}}" data-original-title="{{ __('View') }}">
                                                            <i class="ti ti-eye text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('edit vender')
                                                    <div class="action-btn me-2">
                                                        <a href="#" class="mx-3 btn btn-sm align-items-center bg-info" data-size="lg"
                                                            data-url="{{ route('cooperatives.edit', $coop->id) }}"
                                                            data-ajax-popup="true" title="{{ __('Edit') }}"
                                                            data-title="{{__('Edit Cooperative')}}"
                                                            data-bs-toggle="tooltip" data-original-title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('delete vender')
                                                    <div class="action-btn">
                                                        {!! Form::open(['method' => 'DELETE', 'route' => ['cooperatives.destroy', $coop->id], 'id' => 'delete-form-' . $coop->id]) !!}
                                                            <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger" data-bs-toggle="tooltip"
                                                                   data-original-title="{{ __('Delete') }}" title="{{ __('Delete') }}"
                                                                   data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                                                                   data-confirm-yes="document.getElementById('delete-form-{{ $coop->id }}').submit();">
                                                                <i class="ti ti-trash text-white text-white"></i>
                                                            </a>
                                                        {!! Form::close() !!}
                                                    </div>
                                                @endcan
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
