@extends('layouts.admin')

@section('page-title', __('Gondal Farmers'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Farmers') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('gondal.farmers', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-sm btn-primary">
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
                    <form method="GET" action="{{ route('gondal.farmers') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Search') }}</label>
                                <input type="text" class="form-control" name="search" value="{{ $search }}" placeholder="{{ __('Name, phone, email, code') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('MCC') }}</label>
                                <select class="form-control" name="mcc">
                                    <option value="all">{{ __('All MCCs') }}</option>
                                    @foreach ($mccOptions as $mcc)
                                        <option value="{{ $mcc }}" @selected($selectedMcc === $mcc)>{{ $mcc }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select class="form-control" name="status">
                                    <option value="all">{{ __('All Statuses') }}</option>
                                    @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">{{ __('Apply') }}</button>
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
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Cooperative') }}</th>
                                    <th>{{ __('MCC') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Ledger') }}</th>
                                    <th>{{ __('Open Orders') }}</th>
                                    <th>{{ __('Sponsor Cover') }}</th>
                                    <th>{{ __('Digital') }}</th>
                                    <th>{{ __('Last Supply') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($farmers as $farmer)
                                    <tr>
                                        <td>{{ $farmer['code'] }}</td>
                                        <td>{{ $farmer['name'] }}</td>
                                        <td>{{ $farmer['phone'] }}</td>
                                        <td>{{ $farmer['cooperative'] }}</td>
                                        <td>{{ $farmer['mcc'] }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark">{{ $farmer['status'] }}</span>
                                        </td>
                                        <td>₦{{ number_format((float) $farmer['ledger_balance'], 2) }}</td>
                                        <td>₦{{ number_format((float) $farmer['open_order_balance'], 2) }}</td>
                                        <td>₦{{ number_format((float) $farmer['sponsor_order_balance'], 2) }}</td>
                                        <td>{{ $farmer['digital_payment'] ? __('Yes') : __('No') }}</td>
                                        <td>{{ $farmer['last_supply_at'] ?: 'N/A' }}</td>
                                        <td class="d-flex gap-2">
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editFarmer{{ $farmer['id'] }}">
                                                <i class="ti ti-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('gondal.farmers.destroy', $farmer['id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger" onclick="return confirm('{{ __('Delete this farmer?') }}')">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
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
                    <h5 class="mb-0">{{ __('Register Farmer') }}</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('gondal.farmers.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="text" class="form-control" name="phone" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Gender') }}</label>
                                <select class="form-control" name="gender" required>
                                    <option value="male">{{ __('Male') }}</option>
                                    <option value="female">{{ __('Female') }}</option>
                                    <option value="other">{{ __('Other') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Target Liters') }}</label>
                                <input type="number" step="0.01" class="form-control" name="target_liters" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('MCC') }}</label>
                                <select class="form-control" name="mcc" required>
                                    @foreach ($mccOptions as $mcc)
                                        <option value="{{ $mcc }}">{{ $mcc }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Cooperative') }}</label>
                                <select class="form-control" name="cooperative_id" required>
                                    @foreach ($cooperatives as $cooperative)
                                        <option value="{{ $cooperative->id }}">{{ $cooperative->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('State') }}</label>
                                <input type="text" class="form-control" name="state" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('LGA') }}</label>
                                <input type="text" class="form-control" name="lga" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Ward') }}</label>
                                <input type="text" class="form-control" name="ward" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Community') }}</label>
                                <input type="text" class="form-control" name="community" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Bank Name') }}</label>
                            <input type="text" class="form-control" name="bank_name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Account Number') }}</label>
                            <input type="text" class="form-control" name="account_number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Profile Photo') }}</label>
                            <input type="file" class="form-control" name="profile_photo">
                        </div>
                        <button class="btn btn-primary w-100">{{ __('Save Farmer') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @foreach ($farmers as $farmer)
        <div class="modal fade" id="editFarmer{{ $farmer['id'] }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.farmers.update', $farmer['id']) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Edit Farmer') }} - {{ $farmer['name'] }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Name') }}</label>
                                    <input type="text" class="form-control" name="name" value="{{ $farmer['name'] }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Email') }}</label>
                                    <input type="email" class="form-control" name="email" value="{{ $farmer['email'] !== 'N/A' ? $farmer['email'] : '' }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Phone') }}</label>
                                    <input type="text" class="form-control" name="phone" value="{{ $farmer['phone'] !== 'N/A' ? $farmer['phone'] : '' }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Gender') }}</label>
                                    <select class="form-control" name="gender" required>
                                        @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                                            <option value="{{ $value }}" @selected(strtolower($farmer['gender']) === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Status') }}</label>
                                    <select class="form-control" name="status" required>
                                        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                                            <option value="{{ $value }}" @selected($farmer['status_key'] === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Target Liters') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="target_liters" value="{{ $farmer['target_liters'] }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('MCC') }}</label>
                                    <select class="form-control" name="mcc" required>
                                        @foreach ($mccOptions as $mcc)
                                            <option value="{{ $mcc }}" @selected($farmer['mcc'] === $mcc)>{{ $mcc }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Cooperative') }}</label>
                                    <select class="form-control" name="cooperative_id" required>
                                        @foreach ($cooperatives as $cooperative)
                                            <option value="{{ $cooperative->id }}" @selected((int) $farmer['cooperative_id'] === (int) $cooperative->id)>{{ $cooperative->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('State') }}</label>
                                    <input type="text" class="form-control" name="state" value="{{ $farmer['state'] }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('LGA') }}</label>
                                    <input type="text" class="form-control" name="lga" value="{{ $farmer['lga'] }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Ward') }}</label>
                                    <input type="text" class="form-control" name="ward" value="{{ $farmer['ward'] }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Community') }}</label>
                                    <input type="text" class="form-control" name="community" value="{{ $farmer['community'] }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Bank Name') }}</label>
                                    <input type="text" class="form-control" name="bank_name" value="{{ $farmer['bank_name'] }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Account Number') }}</label>
                                    <input type="text" class="form-control" name="account_number" value="{{ $farmer['account_number'] }}">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">{{ __('Replace Photo') }}</label>
                                    <input type="file" class="form-control" name="profile_photo">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-primary">{{ __('Update Farmer') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
