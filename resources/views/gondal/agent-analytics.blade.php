@extends('layouts.admin')

@section('page-title', $dashboardTitle)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ $dashboardTitle }}</li>
@endsection

@section('action-btn')
    <div class="d-flex gap-2">
        <a href="{{ route('gondal.agents.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-layout-dashboard"></i> {{ __('Dashboard') }}
        </a>
        <a href="{{ route('gondal.agents') }}" class="btn btn-sm btn-primary">
            <i class="ti ti-users"></i> {{ __('Agents') }}
        </a>
    </div>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="text-uppercase small text-muted fw-bold">{{ __('Analytics') }}</div>
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

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Sales 30D') }}</div><div class="fs-4 fw-bold mt-2">₦{{ number_format($summary['sales_30d'], 2) }}</div></div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Units 30D') }}</div><div class="fs-4 fw-bold mt-2">{{ number_format($summary['units_30d'], 2) }}</div></div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Open Credit') }}</div><div class="fs-4 fw-bold mt-2">₦{{ number_format($summary['open_credit'], 2) }}</div></div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Visits 14D') }}</div><div class="fs-4 fw-bold mt-2">{{ number_format($summary['visit_count']) }}</div></div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Farmers Served 30D') }}</div><div class="fs-4 fw-bold mt-2">{{ number_format($summary['farmers_served_30d']) }}</div><div class="small text-muted mt-1">{{ __('Unique farmers reached through visits or sales') }}</div></div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Communities Reached') }}</div><div class="fs-4 fw-bold mt-2">{{ number_format($summary['communities_reached_30d']) }}</div><div class="small text-muted mt-1">{{ __('Distinct farmer communities served in the last 30 days') }}</div></div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Repeat Farmers') }}</div><div class="fs-4 fw-bold mt-2">{{ number_format($summary['repeat_farmers_30d']) }}</div><div class="small text-muted mt-1">{{ __('Farmers engaged more than once in the last 30 days') }}</div></div></div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-uppercase small text-muted fw-bold">{{ __('Visit + Sale Farmers') }}</div><div class="fs-4 fw-bold mt-2">{{ number_format($summary['full_service_farmers_30d']) }}</div><div class="small text-muted mt-1">{{ __('Farmers who received both extension support and product service') }}</div></div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('30-Day Sales Trend') }}</h6>
                    <div class="small text-muted">{{ __('Sales Value shows revenue per day. Units shows total quantity sold per day.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsSalesTrend"></div></div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Credit Aging') }}</h6>
                    <div class="small text-muted">{{ __('Open credit balances are grouped by how many days they have remained unpaid.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsCreditAging"></div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Weekly Cash vs Remitted') }}</h6>
                    <div class="small text-muted">{{ __('Expected Cash is what cash and transfer sales should produce. Remitted is what agents actually paid in.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsCashVsRemit"></div></div>
            </div>
        </div>
        <div class="col-xl-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Visit Trend') }}</h6>
                    <div class="small text-muted">{{ __('Shows how many extension visits were logged on each day in the recent period.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsVisitTrend"></div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Farmer Reach Trend') }}</h6>
                    <div class="small text-muted">{{ __('Farmers Served = unique farmers reached that day. Touches = total visits and sales recorded that day.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsFarmerImpactTrend"></div></div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Service Mix by Farmer') }}</h6>
                    <div class="small text-muted">{{ __('Compares farmers reached by visit only, sale only, or by both visit and sale.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsServiceMix"></div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-5 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Community Impact') }}</h6>
                    <div class="small text-muted">{{ __('Ranks communities by the number of unique farmers served within the analysis window.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsCommunityImpact"></div></div>
            </div>
        </div>
        <div class="col-xl-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0"><h6 class="mb-0 fw-bold">{{ __('Most Served Farmers') }}</h6></div>
                <div class="card-body border-top-0 table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Farmer') }}</th>
                                    <th>{{ __('Community') }}</th>
                                    <th class="text-end">{{ __('Touches') }}</th>
                                    <th class="text-end">{{ __('Last Served') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topFarmers as $farmer)
                                    <tr>
                                        <td>{{ $farmer['farmer_name'] }}</td>
                                        <td>{{ $farmer['community'] ?: __('Unknown community') }}</td>
                                        <td class="text-end fw-bold">{{ number_format($farmer['interactions']) }}</td>
                                        <td class="text-end">{{ $farmer['last_served'] ? \Carbon\Carbon::parse($farmer['last_served'])->format('M j, Y') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No farmer impact records available.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-5 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Top Items by Sales Value') }}</h6>
                    <div class="small text-muted">{{ __('Ranks items by sales value, with the table showing both quantity sold and total value.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsTopItems"></div></div>
                <div class="card-body border-top table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th class="text-end">{{ __('Qty') }}</th>
                                    <th class="text-end">{{ __('Value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topItems as $item)
                                    <tr>
                                        <td>{{ $item['item'] }}</td>
                                        <td class="text-end">{{ number_format($item['quantity'], 2) }}</td>
                                        <td class="text-end fw-bold">₦{{ number_format($item['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No item analytics available.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-1 fw-bold">{{ __('Reconciliation Status Mix') }}</h6>
                    <div class="small text-muted">{{ __('Shows how many reconciliation records fall into each workflow status.') }}</div>
                </div>
                <div class="card-body"><div id="agentAnalyticsStatusMix"></div></div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
<script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const salesTrend30 = @json($salesTrend30);
        const weeklyCashVsSales = @json($weeklyCashVsSales);
        const creditAging = @json($creditAging);
        const visitTrend14 = @json($visitTrend14);
        const farmerImpactTrend30 = @json($farmerImpactTrend30);
        const communityImpact = @json($communityImpact);
        const serviceMix = @json($serviceMix);
        const topItems = @json($topItems);
        const statusMix = @json($statusMix);

        new ApexCharts(document.querySelector('#agentAnalyticsSalesTrend'), {
            series: [
                { name: '{{ __('Sales Value') }}', data: salesTrend30.map((item) => item.amount) },
                { name: '{{ __('Units') }}', data: salesTrend30.map((item) => item.volume) },
            ],
            chart: { height: 320, type: 'line', toolbar: { show: false } },
            stroke: { curve: 'smooth', width: [3, 2] },
            colors: ['#0d6efd', '#20c997'],
            xaxis: { categories: salesTrend30.map((item) => item.label) },
            yaxis: [
                { labels: { formatter: (value) => '₦' + Number(value).toLocaleString() } },
                { opposite: true, labels: { formatter: (value) => Number(value).toLocaleString() } },
            ],
            grid: { borderColor: '#e9ecef', strokeDashArray: 4 },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsCreditAging'), {
            series: creditAging.map((item) => item.amount),
            chart: { height: 320, type: 'donut' },
            labels: creditAging.map((item) => item.label),
            colors: ['#198754', '#ffc107', '#fd7e14', '#dc3545'],
            legend: { position: 'bottom' },
            dataLabels: { formatter: (value, opts) => '₦' + Number(opts.w.config.series[opts.seriesIndex]).toLocaleString() },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsCashVsRemit'), {
            series: [
                { name: '{{ __('Expected Cash') }}', data: weeklyCashVsSales.map((item) => item.expected) },
                { name: '{{ __('Remitted') }}', data: weeklyCashVsSales.map((item) => item.remitted) },
            ],
            chart: { height: 320, type: 'bar', toolbar: { show: false } },
            colors: ['#6f42c1', '#0d6efd'],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            xaxis: { categories: weeklyCashVsSales.map((item) => item.label) },
            yaxis: { labels: { formatter: (value) => '₦' + Number(value).toLocaleString() } },
            grid: { borderColor: '#e9ecef', strokeDashArray: 4 },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsVisitTrend'), {
            series: [{ name: '{{ __('Visits') }}', data: visitTrend14.map((item) => item.visits) }],
            chart: { height: 320, type: 'area', toolbar: { show: false } },
            colors: ['#198754'],
            stroke: { curve: 'smooth', width: 3 },
            dataLabels: { enabled: false },
            xaxis: { categories: visitTrend14.map((item) => item.label) },
            grid: { borderColor: '#e9ecef', strokeDashArray: 4 },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsFarmerImpactTrend'), {
            series: [
                { name: '{{ __('Farmers Served') }}', data: farmerImpactTrend30.map((item) => item.farmers) },
                { name: '{{ __('Touches') }}', data: farmerImpactTrend30.map((item) => item.interactions) },
            ],
            chart: { height: 320, type: 'line', toolbar: { show: false } },
            stroke: { curve: 'smooth', width: [3, 2] },
            colors: ['#198754', '#0dcaf0'],
            xaxis: { categories: farmerImpactTrend30.map((item) => item.label) },
            yaxis: [
                { labels: { formatter: (value) => Number(value).toLocaleString() } },
                { opposite: true, labels: { formatter: (value) => Number(value).toLocaleString() } },
            ],
            grid: { borderColor: '#e9ecef', strokeDashArray: 4 },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsServiceMix'), {
            series: serviceMix.map((item) => item.count),
            chart: { height: 320, type: 'donut' },
            labels: serviceMix.map((item) => item.label),
            colors: ['#20c997', '#ffc107', '#0d6efd'],
            legend: { position: 'bottom' },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsCommunityImpact'), {
            series: [{ name: '{{ __('Farmers Served') }}', data: communityImpact.map((item) => item.farmers) }],
            chart: { height: 320, type: 'bar', toolbar: { show: false } },
            colors: ['#6610f2'],
            plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: communityImpact.map((item) => item.community) },
            grid: { borderColor: '#e9ecef', strokeDashArray: 4 },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsTopItems'), {
            series: [{ name: '{{ __('Sales Value') }}', data: topItems.map((item) => item.amount) }],
            chart: { height: 320, type: 'bar', toolbar: { show: false } },
            colors: ['#fd7e14'],
            plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: topItems.map((item) => item.item) },
            yaxis: { labels: { maxWidth: 220 } },
            tooltip: { y: { formatter: (value) => '₦' + Number(value).toLocaleString() } },
            grid: { borderColor: '#e9ecef', strokeDashArray: 4 },
        }).render();

        new ApexCharts(document.querySelector('#agentAnalyticsStatusMix'), {
            series: [{ name: '{{ __('Count') }}', data: statusMix.map((item) => item.count) }],
            chart: { height: 320, type: 'bar', toolbar: { show: false } },
            colors: ['#0dcaf0'],
            plotOptions: { bar: { borderRadius: 6, distributed: true } },
            dataLabels: { enabled: false },
            xaxis: { categories: statusMix.map((item) => item.label) },
            grid: { borderColor: '#e9ecef', strokeDashArray: 4 },
        }).render();
    });
</script>
@endpush
