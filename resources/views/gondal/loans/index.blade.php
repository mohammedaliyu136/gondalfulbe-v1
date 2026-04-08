@extends('layouts.admin')

@section('page-title')
    {{ __('Loans') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Loans') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title">{{ __('Farmers Loans Management') }}</h5>
            </div>
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table" id="pc-dt-simple">
                        <thead>
                            <tr>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Farmer') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Principal') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loans as $loan)
                                <tr>
                                    <td>{{ $loan->reference }}</td>
                                    <td>{{ $loan->farmer->name ?? 'N/A' }}</td>
                                    <td>{{ Str::headline($loan->type) }}</td>
                                    <td>{{ number_format($loan->principal_amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $loan->status === 'approved' ? 'primary' : ($loan->status === 'disbursed' ? 'success' : 'warning') }} p-2 px-3 rounded">
                                            {{ Str::ucfirst($loan->status) }}
                                        </span>
                                    </td>
                                    <td class="Action">
                                        <div class="action-btn bg-info ms-2">
                                            <a href="#" class="mx-3 btn btn-sm align-items-center" data-bs-toggle="modal" data-bs-target="#loanModal{{ $loan->id }}">
                                                <i class="ti ti-eye text-white"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Modal for Actions -->
                                <div class="modal fade" id="loanModal{{ $loan->id }}" tabindex="-1" aria-labelledby="loanModalLabel" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="loanModalLabel">{{ __('Loan Action') }} - {{ $loan->reference }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body">
                                          @if($loan->status === 'pending')
                                              <form method="POST" action="{{ route('gondal.loans.approve', $loan->id) }}">
                                                  @csrf
                                                  <p>{{ __('Are you sure you want to approve this loan?') }}</p>
                                                  <button type="submit" class="btn btn-primary">{{ __('Approve Loan') }}</button>
                                              </form>
                                          @elseif($loan->status === 'approved')
                                              <form method="POST" action="{{ route('gondal.loans.disburse', $loan->id) }}">
                                                  @csrf
                                                  <div class="form-group mb-3">
                                                      <label>{{ __('Disbursal Date') }}</label>
                                                      <input type="date" name="disbursal_date" class="form-control" required value="{{ date('Y-m-d') }}">
                                                  </div>
                                                  <button type="submit" class="btn btn-success">{{ __('Disburse Loan') }}</button>
                                              </form>
                                          @else
                                              <p>{{ __('Loan is already disbursed. Obligation exists.') }}</p>
                                          @endif
                                      </div>
                                    </div>
                                  </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
