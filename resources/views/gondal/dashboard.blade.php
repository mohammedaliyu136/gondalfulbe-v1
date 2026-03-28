@extends('layouts.admin')

@section('page-title', $dashboardTitle ?? __('Gondal Dashboard'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ $dashboardTitle ?? __('Gondal') }}</li>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    @if (!empty($dashboardTitle) || !empty($dashboardSubtitle))
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1">{{ $dashboardTitle ?? __('Gondal Dashboard') }}</h4>
                @if (!empty($dashboardSubtitle))
                    <p class="text-muted mb-0">{{ $dashboardSubtitle }}</p>
                @endif
            </div>
        </div>
    @endif

    <!-- Top Stats Row -->
    <div class="gondal-stats-container">
        <!-- Active Farmers -->
        <div class="gs-card">
            <div class="gs-card-body">
                <div class="gs-header">
                    <div>
                        <p class="gs-label">{{ __('Active Farmers') }}</p>
                        <p class="gs-value">{{ number_format($activeFarmers ?? 0) }}</p>
                        <p class="gs-sub">of {{ number_format($totalFarmers ?? 0) }} registered</p>
                    </div>
                    <div class="gs-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                </div>
                <p class="gs-trend">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                        <polyline points="16 7 22 7 22 13"></polyline>
                    </svg>
                    +{{ $farmersThisMonth ?? 0 }} this month
                </p>
            </div>
        </div>

        <!-- Daily Collection -->
        <div class="gs-card">
            <div class="gs-card-body">
                <div class="gs-header">
                    <div>
                        <p class="gs-label">{{ __('Daily Collection') }}</p>
                        <p class="gs-value">{{ number_format($dailyCollection ?? 0) }}L</p>
                        <p class="gs-sub">{{ number_format($weeklyCollection ?? 0) }}L this week</p>
                    </div>
                    <div class="gs-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 2h8"></path>
                            <path d="M9 2v2.789a4 4 0 0 1-.672 2.219l-.656.984A4 4 0 0 0 7 10.212V20a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-9.789a4 4 0 0 0-.672-2.219l-.656-.984A4 4 0 0 1 15 4.788V2"></path>
                            <path d="M7 15a6.472 6.472 0 0 1 5 0 6.47 6.47 0 0 0 5 0"></path>
                        </svg>
                    </div>
                </div>
                @php
                    $isPositive = (isset($weeklyPercentageChange) && $weeklyPercentageChange >= 0);
                    $trendVal = number_format(abs($weeklyPercentageChange ?? 0), 1);
                @endphp
                <p class="gs-trend {{ $isPositive ? '' : 'down' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        @if($isPositive)
                            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                            <polyline points="16 7 22 7 22 13"></polyline>
                        @else
                            <polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline>
                            <polyline points="16 17 22 17 22 11"></polyline>
                        @endif
                    </svg>
                    {{ $trendVal }}% vs last week
                </p>
            </div>
        </div>

        <!-- Financial Inclusion -->
        <div class="gs-card">
            <div class="gs-card-body">
                <div class="gs-header">
                    <div>
                        <p class="gs-label">{{ __('Financial Inclusion') }}</p>
                        <p class="gs-value">{{ $financialInclusion ?? '0%' }}</p>
                        <p class="gs-sub">digital payment enabled</p>
                    </div>
                    <div class="gs-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Centers Operational -->
        <div class="gs-card">
            <div class="gs-card-body">
                <div class="gs-header">
                    <div>
                        <p class="gs-label">{{ __('Centers Operational') }}</p>
                        <p class="gs-value">{{ $activeCooperatives ?? 0 }}/{{ $totalCooperatives ?? 0 }}</p>
                        <p class="gs-sub">all centers active</p>
                    </div>
                    <div class="gs-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path>
                            <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                            <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path>
                            <path d="M10 6h4"></path>
                            <path d="M10 10h4"></path>
                            <path d="M10 14h4"></path>
                            <path d="M10 18h4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Requisitions -->
        <div class="gs-card">
            <div class="gs-card-body">
                <div class="gs-header">
                    <div>
                        <p class="gs-label">{{ __('Pending Requisitions') }}</p>
                        <p class="gs-value">{{ number_format($pendingRequisitions ?? 0) }}</p>
                        <p class="gs-sub">awaiting approval</p>
                    </div>
                    <div class="gs-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                            <path d="M10 9H8"></path>
                            <path d="M16 13H8"></path>
                            <path d="M16 17H8"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Cooperatives -->
        <div class="gs-card">
            <div class="gs-card-body">
                <div class="gs-header">
                    <div>
                        <p class="gs-label">{{ __('Total Cooperatives') }}</p>
                        <p class="gs-value">{{ $totalCooperatives ?? 0 }}</p>
                        <p class="gs-sub">{{ number_format($totalCoopMembers ?? 0) }} total members</p>
                    </div>
                    <div class="gs-icon-box purple">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m11 17 2 2a1 1 0 1 0 3-3"></path>
                            <path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"></path>
                            <path d="m21 3 1 11h-2"></path>
                            <path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"></path>
                            <path d="M3 4h8"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Milk Collection Trend Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-0 fw-bold">{{ __('Milk Collection Trend (Last 7 Days)') }}</h6>
                </div>
                <div class="card-body">
                    <div id="trendChart"></div>
                </div>
            </div>
        </div>

        <!-- Daily Collection by Center Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-0 fw-bold">{{ __('Daily Collection by Center') }}</h6>
                </div>
                <div class="card-body">
                    <div id="centerChart"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="row">
        <!-- Gender Breakdown Chart -->
        <div class="col-xl-4 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-0 fw-bold">{{ __('Gender Distribution') }}</h6>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div id="genderChart"></div>
                </div>
            </div>
        </div>

        <!-- Team Activity -->
        <div class="col-xl-4 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-0 fw-bold">{{ __('Team Activity') }}</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        @foreach ($teamActivities as $item)
                            <div class="border rounded-3 p-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <h6 class="mb-1 fw-bold">{{ $item['name'] }}</h6>
                                        <div class="text-muted small">{{ $item['role'] }}</div>
                                    </div>
                                    <span class="badge bg-light text-dark border">{{ $item['status'] }}</span>
                                </div>
                                <p class="mb-1 mt-2 text-muted small">{{ ucfirst($item['activity']) }}</p>
                                <div class="small text-secondary">{{ $item['time'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payment Batches -->
        <div class="col-xl-4 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pb-0 container-fluid">
                   <h6 class="mb-0 fw-bold">{{ __('Recent Payment Batches') }}</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @forelse ($recentPaymentBatches as $batch)
                            <li class="list-group-item px-0 py-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 fw-bold">{{ ucfirst($batch->payee_type) }} — {{ $batch->name }}</h6>
                                        <small class="text-muted">{{ $batch->transactions_count ?? rand(5,20) }} transactions</small>
                                    </div>
                                    <div class="text-end">
                                        <h6 class="mb-1 fw-bold">₦{{ number_format($batch->total_amount, 0) }}</h6>
                                        @if(strtolower($batch->status) == 'completed')
                                            <span class="badge bg-success rounded-pill px-3 py-1">{{ ucfirst($batch->status) }}</span>
                                        @elseif(strtolower($batch->status) == 'processing')
                                            <span class="badge bg-secondary rounded-pill px-3 py-1">{{ ucfirst($batch->status) }}</span>
                                        @else
                                            <span class="badge bg-light text-dark rounded-pill px-3 py-1 border">{{ ucfirst($batch->status) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @empty
                            <p class="text-muted mb-0 my-3">{{ __('No recent payment batches.') }}</p>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <!-- User Roles -->
        <div class="col-xl-4 col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-0 fw-bold">{{ __('Users and Roles') }}</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Role') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($teamUsers as $item)
                                    <tr>
                                        <td>{{ $item['name'] }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $item['role'] }}</span>
                                            <div class="small text-muted mt-1">{{ $item['last_seen'] }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-muted">{{ __('No user records found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var trendData = {!! json_encode($trend) !!};
        var centerData = {!! json_encode($centerSummaries) !!};
        var genderData = {!! json_encode($genderBreakdown) !!};

        // Trend Line Chart
        var trendOptions = {
            series: [{
                name: 'Liters',
                data: trendData.map(function(item) { return item.liters; })
            }],
            chart: {
                height: 300,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            colors: ['#00aba9'],
            dataLabels: { enabled: false },
            stroke: { curve: 'straight', width: 2 },
            markers: { size: 5, colors: ['#00aba9'], strokeColors: '#fff', strokeWidth: 2, hover: { size: 7 } },
            xaxis: {
                categories: trendData.map(function(item) { return item.label; }),
                axisBorder: { show: true },
                axisTicks: { show: true },
            },
            yaxis: {
                min: 0,
                tickAmount: 5,
                labels: { formatter: function (val) { return val.toFixed(0); } }
            },
            grid: {
                borderColor: '#e7e7e7',
                strokeDashArray: 4,
            }
        };
        var trendChart = new ApexCharts(document.querySelector("#trendChart"), trendOptions);
        trendChart.render();

        // Centers Bar Chart
        var centerOptions = {
            series: [{
                name: 'Liters',
                data: centerData.map(function(item) { return item.liters; })
            }],
            chart: {
                height: 300,
                type: 'bar',
                toolbar: { show: false }
            },
            colors: ['#2e59d9'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '60%',
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: centerData.map(function(item) { return item.name; }),
                axisBorder: { show: true },
                axisTicks: { show: true },
            },
            yaxis: {
                min: 0,
                labels: { formatter: function (val) { return val.toFixed(0); } }
            },
            grid: {
                borderColor: '#e7e7e7',
                strokeDashArray: 4,
            }
        };
        var centerChart = new ApexCharts(document.querySelector("#centerChart"), centerOptions);
        centerChart.render();

        // Gender Pie Chart
        // Only get Male and Female counts for simplicity
        var genderLabels = [];
        var genderCounts = [];
        genderData.forEach(function(item) {
            if(item.label.toLowerCase() === 'male' || item.label.toLowerCase() === 'female') {
                genderLabels.push(item.label);
                genderCounts.push(item.count);
            }
        });

        var genderOptions = {
            series: genderCounts,
            chart: {
                height: 300,
                type: 'pie',
            },
            labels: genderLabels,
            colors: ['#4e73df', '#1cc88a'],
            dataLabels: {
                enabled: true,
                formatter: function (val, opts) {
                    return opts.w.config.labels[opts.seriesIndex] + ': ' + opts.w.globals.seriesTotals[opts.seriesIndex];
                },
                style: {
                    fontSize: '14px',
                    fontFamily: 'Helvetica, Arial, sans-serif',
                },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    padding: 4,
                    borderRadius: 2,
                    borderWidth: 1,
                    borderColor: '#fff',
                }
            },
            legend: { show: false },
            stroke: { width: 2, colors: ['#fff'] }
        };
        var genderChart = new ApexCharts(document.querySelector("#genderChart"), genderOptions);
        genderChart.render();
    });
</script>
@endpush
