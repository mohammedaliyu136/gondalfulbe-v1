@extends('layouts.admin')

@php
    $profilePhoto = !empty($vendor->profile_photo_path) ? asset('storage/' . $vendor->profile_photo_path) : asset('assets/images/user/avatar-4.jpg');
    $statusClass = $vendor->is_active ? 'bg-success' : 'bg-danger';
@endphp

@section('page-title')
    {{ __('Farmer Profile') }} - {{ $vendor->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('vender.index') }}">{{ __('Farmers') }}</a></li>
    <li class="breadcrumb-item">{{ $vendor->name }}</li>
@endsection

@section('action-btn')
    <div class="float-end d-flex gap-2">
        @can('create bill')
            <a href="{{ route('bill.create', $vendor->id) }}" class="btn btn-sm btn-primary">
                <i class="ti ti-plus me-1"></i> {{ __('Create Bill') }}
            </a>
        @endcan

        @can('edit vender')
            <a href="#" class="btn btn-sm btn-info" data-size="xl" data-url="{{ route('vender.edit', $vendor->id) }}"
                data-ajax-popup="true" title="{{ __('Edit') }}" data-bs-toggle="tooltip">
                <i class="ti ti-pencil"></i>
            </a>
        @endcan
        @can('delete vender')
            {!! Form::open([
                'method' => 'DELETE',
                'route' => ['vender.destroy', $vendor->id],
                'class' => 'delete-form-btn',
                'id' => 'delete-form-' . $vendor->id,
            ]) !!}
            <a href="#" class="btn btn-sm btn-danger bs-pass-para" data-bs-toggle="tooltip" title="{{ __('Delete') }}"
                data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                data-confirm-yes="document.getElementById('delete-form-{{ $vendor->id }}').submit();">
                <i class="ti ti-trash text-white"></i>
            </a>
            {!! Form::close() !!}
        @endcan
    </div>
@endsection

@section('content')
    <div class="row">
        <!-- Sidebar Profile -->
        <div class="col-xl-3">
            <div class="card sticky-top" style="top: 100px;">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="{{ $profilePhoto }}" class="rounded-circle img-thumbnail shadow-sm"
                            style="width: 120px; height: 120px; object-fit: cover;">
                        <span
                            class="position-absolute bottom-0 end-0 badge rounded-pill {{ $statusClass }} border border-white"
                            style="width: 15px; height: 15px;"></span>
                    </div>
                    <h5 class="mb-1">{{ $vendor->name }}</h5>
                    <p class="text-muted small mb-3">
                        <span class="badge bg-light-primary text-primary px-3">{{ __('Farmer ID') }}:
                            {{ \Auth::user()->venderNumberFormat($vendor->vender_id) }}</span>
                    </p>

                    <div class="text-start border-top pt-3 mt-3">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-light-primary rounded p-2 text-primary me-3">
                                <i class="ti ti-building-community fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">{{ __('Cooperative') }}</small>
                                <span class="fw-bold">{{ $vendor->cooperative?->name ?: __('N/A') }}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-light-info rounded p-2 text-info me-3">
                                <i class="ti ti-map-pin fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">{{ __('Location') }}</small>
                                <span class="fw-bold">{{ $vendor->billing_city ?: __('N/A') }}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-light-success rounded p-2 text-success me-3">
                                <i class="ti ti-phone fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">{{ __('Contact') }}</small>
                                <span class="fw-bold">{{ $vendor->contact ?: __('N/A') }}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center">
                            <div class="bg-light-warning rounded p-2 text-warning me-3">
                                <i class="ti ti-calendar-event fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">{{ __('Registered Since') }}</small>
                                <span class="fw-bold">{{ \Auth::user()->dateFormat($vendor->registration_date ?: $vendor->created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-xl-9">
            <!-- Summary Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-none border mb-0 bg-light-primary overflow-hidden">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary rounded p-2 text-white me-3">
                                    <i class="ti ti-wallet fs-4"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">{{ __('Wallet Balance') }}</p>
                                    <h5 class="mb-0 fw-bold">{{ \Auth::user()->priceFormat($vendor->balance) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-none border mb-0 bg-light-success overflow-hidden">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-success rounded p-2 text-white me-3">
                                    <i class="ti ti-bottle fs-4"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">{{ __('Lifetime Milk') }}</p>
                                    <h5 class="mb-0 fw-bold">{{ number_format($milkStats->total_liters ?? 0, 1) }} L</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-none border mb-0 bg-light-info overflow-hidden">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-info rounded p-2 text-white me-3">
                                    <i class="ti ti-award fs-4"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">{{ __('Avg. Quality (Fat)') }}</p>
                                    <h5 class="mb-0 fw-bold">{{ number_format($milkStats->avg_fat ?? 0, 2) }}%</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-none border mb-0 bg-light-danger overflow-hidden">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-danger rounded p-2 text-white me-3">
                                    <i class="ti ti-cash-banknote fs-4"></i>
                                </div>
                                <div>
                                    <p class="text-muted mb-0 small">{{ __('Outstanding Loans') }}</p>
                                    <h5 class="mb-0 fw-bold">{{ \Auth::user()->priceFormat($outstandingLoans) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabbed Interface -->
            <div class="card">
                <div class="card-header border-bottom py-3">
                    <ul class="nav nav-pills nav-fill gap-2" id="farmerTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="overview-tab" data-bs-toggle="tab" href="#overview"
                                role="tab">{{ __('Overview') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="milk-tab" data-bs-toggle="tab" href="#milk"
                                role="tab">{{ __('Milk Records') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="finance-tab" data-bs-toggle="tab" href="#finance"
                                role="tab">{{ __('Financials') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="kyc-tab" data-bs-toggle="tab" href="#kyc"
                                role="tab">{{ __('KYC & Documents') }}</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="farmerTabContent">
                        <!-- Overview Tab -->
                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                            <div class="row">
                                <div class="col-md-7">
                                    <h6 class="mb-3 fw-bold"><i class="ti ti-chart-line me-1"></i> {{ __('Production Trend (Liters)') }}</h6>
                                    <div class="card shadow-none border bg-white">
                                        <div class="card-body p-2">
                                            <div id="production-chart" height="280"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <h6 class="mb-3 fw-bold"><i class="ti ti-list-details me-1"></i> {{ __('Member Details') }}</h6>
                                    <div class="card shadow-none border bg-light-info-subtle">
                                        <div class="card-body">
                                            <ul class="list-group list-group-flush list-group-borderless">
                                                <li class="list-group-item d-flex justify-content-between bg-transparent px-0">
                                                    <span class="text-muted">{{ __('Gender') }}</span>
                                                    <span class="fw-bold">{{ ucfirst($vendor->gender) }}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between bg-transparent px-0">
                                                    <span class="text-muted">{{ __('State') }}</span>
                                                    <span class="fw-bold">{{ $vendor->state ?: 'Gombe' }}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between bg-transparent px-0">
                                                    <span class="text-muted">{{ __('LGA') }}</span>
                                                    <span class="fw-bold">{{ $vendor->lga ?: 'Akko' }}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between bg-transparent px-0">
                                                    <span class="text-muted">{{ __('Ward') }}</span>
                                                    <span class="fw-bold">{{ $vendor->ward ?: __('N/A') }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    @if(count($vendor->customField) > 0)
                                        <h6 class="mt-4 mb-3 fw-bold"><i class="ti ti-note me-1"></i> {{ __('Other Information') }}</h6>
                                        <div class="list-group list-group-flush border rounded">
                                            @foreach ($vendor->customField as $field)
                                                <div class="list-group-item d-flex justify-content-between py-2">
                                                    <span class="text-muted px-0">{{ $field->name }}</span>
                                                    <span class="fw-bold px-0">{{ $field->value ?? '-' }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Milk Tab -->
                        <div class="tab-pane fade" id="milk" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover datatable" id="milk-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Quantity') }}</th>
                                            <th>{{ __('Fat %') }}</th>
                                            <th>{{ __('SNF %') }}</th>
                                            <th>{{ __('Grade') }}</th>
                                            <th>{{ __('Total Price') }}</th>
                                            <th>{{ __('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentCollections as $collection)
                                            <tr>
                                                <td>{{ \Auth::user()->dateFormat($collection->collection_date) }}</td>
                                                <td class="fw-bold">{{ number_format($collection->quantity, 1) }} L</td>
                                                <td>{{ number_format((float)$collection->fat_percentage, 1) }}%</td>
                                                <td>{{ number_format((float)$collection->snf_percentage, 1) }}%</td>
                                                <td><span class="badge bg-light-info text-info">{{ $collection->quality_grade ?: '-' }}</span></td>
                                                <td>{{ \Auth::user()->priceFormat($collection->total_price) }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $collection->status === 'validated' ? 'success' : ($collection->status === 'pending' ? 'warning' : 'danger') }}">
                                                        {{ ucfirst($collection->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Finance Tab -->
                        <div class="tab-pane fade" id="finance" role="tabpanel">
                            <h6 class="mb-3 fw-bold"><i class="ti ti-receipt me-1"></i> {{ __('Payment History & Bills') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-hover datatable" id="finance-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Type') }}</th>
                                            <th>{{ __('Category') }}</th>
                                            <th>{{ __('Amount') }}</th>
                                            <th>{{ __('Description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentSettlements as $settlement)
                                            <tr>
                                                <td>{{ \Auth::user()->dateFormat($settlement->created_at) }}</td>
                                                <td><span class="badge bg-light-success text-success">{{ __('Payout') }}</span></td>
                                                <td>{{ __('Settlement') }}</td>
                                                <td class="fw-bold text-success">{{ \Auth::user()->priceFormat($settlement->net_payout) }}</td>
                                                <td>{{ $settlement->reference }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($recentTransactions as $transaction)
                                            <tr>
                                                <td>{{ \Auth::user()->dateFormat($transaction->date) }}</td>
                                                <td><span class="badge bg-light-primary text-primary">{{ ucfirst($transaction->type) }}</span></td>
                                                <td>{{ $transaction->category ?: __('Payout') }}</td>
                                                <td class="fw-bold text-success">{{ \Auth::user()->priceFormat($transaction->amount) }}</td>
                                                <td>{{ $transaction->description ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($recentBills as $bill)
                                            <tr>
                                                <td>{{ \Auth::user()->dateFormat($bill->bill_date) }}</td>
                                                <td><span class="badge bg-light-danger text-danger">{{ __('Bill') }}</span></td>
                                                <td>{{ __('Purchase') }}</td>
                                                <td class="fw-bold text-danger">{{ \Auth::user()->priceFormat($bill->getTotal()) }}</td>
                                                <td>{{ $bill->bill_id }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- KYC Tab -->
                        <div class="tab-pane fade" id="kyc" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 border-end">
                                    <h6 class="mb-4 fw-bold"><i class="ti ti-bank me-1"></i> {{ __('Financial Details') }}</h6>
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light-primary p-2 rounded text-primary me-3">
                                                    <i class="ti ti-building-bank"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">{{ __('Bank Name') }}</small>
                                                    <p class="mb-0 fw-bold">{{ $vendor->bank_name ?: __('Not Provided') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light-primary p-2 rounded text-primary me-3">
                                                    <i class="ti ti-credit-card"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">{{ __('Account Number') }}</small>
                                                    <p class="mb-0 fw-bold">{{ $vendor->account_number ?: __('Not Provided') }}</p>
                                                </div>
                                            </div>
                                            @if($vendor->account_number)
                                                <button class="btn btn-sm btn-light-primary copy-btn" data-text="{{ $vendor->account_number }}"><i class="ti ti-copy"></i></button>
                                            @endif
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light-primary p-2 rounded text-primary me-3">
                                                    <i class="ti ti-id"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">{{ __('BVN') }}</small>
                                                    <p class="mb-0 fw-bold">{{ $vendor->bvn ?: __('Not Provided') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 p-4">
                                    <h6 class="mb-4 fw-bold"><i class="ti ti-file me-1"></i> {{ __('Documents & Attachments') }}</h6>
                                    @php
                                        $docs = $vendor->document_paths ? json_decode($vendor->document_paths, true) : [];
                                    @endphp
                                    @if(count($docs) > 0)
                                        <div class="row g-2">
                                            @foreach($docs as $doc)
                                                <div class="col-md-6">
                                                    <a href="{{ Storage::url($doc) }}" target="_blank" class="card shadow-none border mb-0 text-decoration-none">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex align-items-center">
                                                                <i class="ti ti-file-text fs-4 text-info me-3"></i>
                                                                <div class="text-truncate">
                                                                    <p class="mb-0 small text-dark fw-bold text-truncate">{{ basename($doc) }}</p>
                                                                    <small class="text-muted">{{ __('View Document') }}</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-5 bg-light rounded">
                                            <i class="ti ti-files fs-1 text-muted mb-3 d-block"></i>
                                            <p class="text-muted mb-0">{{ __('No documents uploaded') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        $(document).on('click', '.copy-btn', function() {
            var text = $(this).data('text');
            navigator.clipboard.writeText(text);
            show_toastr('Success', 'Copied to clipboard', 'success');
        });

        (function() {
            var options = {
                chart: {
                    height: 300,
                    type: 'area',
                    toolbar: { show: false },
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                series: [{
                    name: 'Milk (L)',
                    data: {!! json_encode($chartData['data']) !!}
                }],
                xaxis: {
                    categories: {!! json_encode($chartData['labels']) !!},
                },
                colors: ['#36B37E'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.1,
                        stops: [0, 90, 100]
                    }
                },
                grid: {
                    borderColor: '#f1f1f1',
                }
            };
            var chart = new ApexCharts(document.querySelector("#production-chart"), options);
            chart.render();
        })();
    </script>
@endpush
