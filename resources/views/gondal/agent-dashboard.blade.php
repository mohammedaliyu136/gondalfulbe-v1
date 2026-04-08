@extends('layouts.admin')

@section('page-title', $dashboardTitle)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ $dashboardTitle }}</li>
@endsection

@section('action-btn')
    <div class="d-flex gap-2">
        <a href="{{ route('gondal.agents') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-users"></i> {{ __('Agents') }}
        </a>
        <a href="{{ route('gondal.agents.analytics') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-chart-bar"></i> {{ __('Analytics') }}
        </a>
        <a href="{{ route('gondal.inventory', ['tab' => 'reconciliation']) }}" class="btn btn-sm btn-primary">
            <i class="ti ti-scale"></i> {{ __('Reconciliation') }}
        </a>
    </div>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-uppercase small text-muted fw-bold">{{ __('Field Operations') }}</div>
                <h3 class="mb-1">{{ $dashboardTitle }}</h3>
                <p class="text-muted mb-2">{{ $dashboardSubtitle }}</p>
                @if ($dashboardAgent)
                    <div class="small text-muted">
                        {{ $dashboardAgent->full_name ?: ($dashboardAgent->user?->name ?: __('Unknown agent')) }}
                        · {{ $dashboardAgent->agent_code }}
                        · {{ $dashboardAgent->communityRecord?->name ?: ($dashboardAgent->community ?: __('No primary community')) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row">
        @foreach ($cards as $card)
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-uppercase small text-muted fw-bold">{{ $card['label'] }}</div>
                        <div class="fs-4 fw-bold text-dark mt-2">{{ $card['value'] }}</div>
                        <div class="text-muted small mt-2">{{ $card['meta'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Sales Trend') }}</h6>
                </div>
                <div class="card-body">
                    <div id="agentSalesTrendChart"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Payment Mix') }}</h6>
                </div>
                <div class="card-body">
                    <div id="agentPaymentMixChart"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Quick Links') }}</h6>
                </div>
                <div class="card-body d-grid gap-2">
                    @foreach ($quickLinks as $link)
                        <a href="{{ $link['url'] }}" class="btn btn-light text-start border">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Field Summary') }}</h6>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    <div class="border rounded-3 p-3">
                        <div class="small text-muted">{{ __('Visits Today') }}</div>
                        <div class="fs-5 fw-bold">{{ number_format($fieldSummary['visits_today']) }}</div>
                    </div>
                    <div class="border rounded-3 p-3">
                        <div class="small text-muted">{{ __('Total Visits Logged') }}</div>
                        <div class="fs-5 fw-bold">{{ number_format($fieldSummary['visits_total']) }}</div>
                    </div>
                    <div class="border rounded-3 p-3">
                        <div class="small text-muted">{{ __('Visit-Linked Sales') }}</div>
                        <div class="fs-5 fw-bold">{{ $fieldSummary['visit_sales'] }}</div>
                    </div>
                    <div class="border rounded-3 p-3">
                        <div class="small text-muted">{{ __('Stock Adjustments Posted') }}</div>
                        <div class="fs-5 fw-bold">{{ number_format($fieldSummary['adjustments']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Watch List') }}</h6>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    @forelse ($watchList as $item)
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold">{{ $item['title'] }}</div>
                                    <div class="small text-muted">{{ $item['meta'] }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">{{ $item['value'] }}</div>
                                    <div class="small text-muted">{{ $item['status'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No open exceptions right now.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Stock On Hand') }}</h6>
                </div>
                <div class="card-body">
                    <div id="agentStockChart"></div>
                </div>
                <div class="card-body border-top table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th class="text-end">{{ __('Available') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($stockRows as $row)
                                    <tr>
                                        <td>{{ $row['item'] }}</td>
                                        <td class="text-end fw-bold">{{ number_format($row['available'], 2) }} {{ $row['unit'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-4">{{ __('No agent-held stock currently available.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Recent Sales') }}</h6>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    @forelse ($recentSales as $sale)
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold">{{ $sale['buyer'] }}</div>
                                    <div class="small text-muted">{{ $sale['item'] }} · {{ $sale['agent'] }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">₦{{ number_format($sale['amount'], 2) }}</div>
                                    <div class="small text-muted">{{ $sale['payment_method'] }} · {{ $sale['date'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No sales recorded yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">{{ __('Recent Activity') }}</h6>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    @forelse ($recentActivity as $item)
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold">{{ $item['title'] }}</div>
                                    <div class="small text-muted">{{ $item['meta'] }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">{{ $item['value'] }}</div>
                                    <div class="small text-muted">{{ $item['status'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No recent agent activity available.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
<script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const salesTrend = @json($salesTrend);
        const paymentMix = @json($paymentMix);
        const stockRows = @json($stockRows);

        const salesTrendOptions = {
            series: [{
                name: '{{ __('Sales') }}',
                data: salesTrend.map((item) => item.amount),
            }],
            chart: {
                height: 320,
                type: 'area',
                toolbar: { show: false },
            },
            colors: ['#0d6efd'],
            stroke: { curve: 'smooth', width: 3 },
            dataLabels: { enabled: false },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 100],
                },
            },
            xaxis: {
                categories: salesTrend.map((item) => item.label),
            },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return '₦' + Number(value).toLocaleString();
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return '₦' + Number(value).toLocaleString();
                    }
                }
            },
            grid: {
                borderColor: '#e9ecef',
                strokeDashArray: 4,
            },
        };

        const paymentMixOptions = {
            series: paymentMix.map((item) => item.amount),
            chart: {
                height: 320,
                type: 'donut',
            },
            labels: paymentMix.map((item) => item.label),
            colors: ['#198754', '#0d6efd', '#dc3545'],
            legend: {
                position: 'bottom',
            },
            dataLabels: {
                formatter: function (value, opts) {
                    return '₦' + Number(opts.w.config.series[opts.seriesIndex]).toLocaleString();
                }
            },
        };

        const stockOptions = {
            series: [{
                name: '{{ __('Available Stock') }}',
                data: stockRows.map((item) => item.available),
            }],
            chart: {
                height: 320,
                type: 'bar',
                toolbar: { show: false },
            },
            colors: ['#fd7e14'],
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 6,
                    barHeight: '55%',
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: stockRows.map((item) => item.item),
            },
            tooltip: {
                y: {
                    formatter: function (value, { dataPointIndex }) {
                        const row = stockRows[dataPointIndex];
                        return Number(value).toLocaleString() + ' ' + (row ? row.unit : '');
                    }
                }
            },
            grid: {
                borderColor: '#e9ecef',
                strokeDashArray: 4,
            },
        };

        new ApexCharts(document.querySelector('#agentSalesTrendChart'), salesTrendOptions).render();
        new ApexCharts(document.querySelector('#agentPaymentMixChart'), paymentMixOptions).render();
        new ApexCharts(document.querySelector('#agentStockChart'), stockOptions).render();
    });
</script>
@endpush
