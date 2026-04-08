@php
    $mainLinks = [
        ['label' => 'Dashboard', 'route' => 'gondal.dashboard'],
        ['label' => 'Farmers', 'route' => 'gondal.farmers'],
        ['label' => 'Agents', 'route' => 'gondal.agents'],
        ['label' => 'Communities', 'route' => 'gondal.communities'],
        ['label' => 'Cooperatives', 'route' => 'gondal.cooperatives'],
        ['label' => 'Milk Collection', 'route' => 'gondal.milk-collection'],
        ['label' => 'Logistics', 'route' => 'gondal.logistics'],
        ['label' => 'Operations', 'route' => 'gondal.operations'],
        ['label' => 'Requisitions', 'route' => 'gondal.requisitions'],
        ['label' => 'Payments', 'route' => 'gondal.payments'],
        ['label' => 'Inventory', 'route' => 'gondal.inventory'],
        ['label' => 'Extension', 'route' => 'gondal.extension'],
        ['label' => 'Reports', 'route' => 'gondal.reports'],
    ];

    $adminLinks = [
        ['label' => 'Audit Log', 'route' => 'gondal.admin.audit-log'],
        ['label' => 'Approval Rules', 'route' => 'gondal.admin.approval-rules'],
    ];
@endphp

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($mainLinks as $link)
                <a href="{{ route($link['route']) }}" class="btn btn-sm {{ request()->routeIs($link['route']) ? 'btn-primary' : 'btn-light' }}">
                    {{ __($link['label']) }}
                </a>
            @endforeach
        </div>

        <hr class="my-3">

        <div class="d-flex flex-wrap gap-2">
            @foreach ($adminLinks as $link)
                <a href="{{ route($link['route']) }}" class="btn btn-sm {{ request()->routeIs($link['route']) ? 'btn-secondary' : 'btn-light' }}">
                    {{ __($link['label']) }}
                </a>
            @endforeach
        </div>
    </div>
</div>
