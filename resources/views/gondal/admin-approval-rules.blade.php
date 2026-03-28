@extends('layouts.admin')

@section('page-title', __('Gondal Approval Rules'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Approval Rules') }}</li>
@endsection

@section('content')
    @include('gondal.partials.nav')
    @include('gondal.partials.alerts')

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Range') }}</th>
                                    <th>{{ __('Approver Role') }}</th>
                                    <th>{{ __('Active') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rules as $rule)
                                    <tr>
                                        <td>{{ $rule->name }}</td>
                                        <td>₦{{ number_format($rule->min_amount, 2) }} - ₦{{ number_format($rule->max_amount, 2) }}</td>
                                        <td>{{ $rule->approver_role }}</td>
                                        <td>{{ $rule->is_active ? __('Yes') : __('No') }}</td>
                                        <td>
                                            <button class="btn btn-sm bg-info text-white" data-bs-toggle="modal" data-bs-target="#editRule{{ $rule->id }}">
                                                <i class="ti ti-pencil"></i>
                                            </button>
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
                <div class="card-header"><h5 class="mb-0">{{ __('Create Rule') }}</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('gondal.admin.approval-rules.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Min Amount') }}</label>
                                <input type="number" step="0.01" class="form-control" name="min_amount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Max Amount') }}</label>
                                <input type="number" step="0.01" class="form-control" name="max_amount" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Approver Role') }}</label>
                            <select class="form-control" name="approver_role" required>
                                @foreach (['system_admin', 'executive_director', 'finance_officer', 'center_manager'] as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="is_active" value="1">
                        <button class="btn btn-primary w-100">{{ __('Save Rule') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @foreach ($rules as $rule)
        <div class="modal fade" id="editRule{{ $rule->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.admin.approval-rules.update', $rule->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Edit Rule') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" class="form-control" name="name" value="{{ $rule->name }}" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Min Amount') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="min_amount" value="{{ $rule->min_amount }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Max Amount') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="max_amount" value="{{ $rule->max_amount }}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Approver Role') }}</label>
                                <select class="form-control" name="approver_role" required>
                                    @foreach (['system_admin', 'executive_director', 'finance_officer', 'center_manager'] as $role)
                                        <option value="{{ $role }}" @selected($rule->approver_role === $role)>{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ruleActive{{ $rule->id }}" @checked($rule->is_active)>
                                <label class="form-check-label" for="ruleActive{{ $rule->id }}">{{ __('Active') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-primary">{{ __('Update Rule') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
