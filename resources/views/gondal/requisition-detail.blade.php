@extends('layouts.admin')

@section('page-title', $requisition->reference)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.requisitions') }}">{{ __('Requisitions') }}</a></li>
    <li class="breadcrumb-item">{{ $requisition->reference }}</li>
@endsection

@section('content')
    @include('gondal.partials.nav')
    @include('gondal.partials.alerts')

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">{{ __('Title') }}</small>
                    <h5>{{ $requisition->title }}</h5>
                    <small class="text-muted">{{ __('Requester') }}</small>
                    <h5>{{ $requisition->requester?->name ?: 'N/A' }}</h5>
                    <small class="text-muted">{{ __('Cooperative') }}</small>
                    <h5>{{ $requisition->cooperative?->name ?: 'N/A' }}</h5>
                    <small class="text-muted">{{ __('Priority') }}</small>
                    <h5>{{ ucfirst($requisition->priority) }}</h5>
                    <small class="text-muted">{{ __('Status') }}</small>
                    <h5>{{ ucfirst($requisition->status) }}</h5>
                    <small class="text-muted">{{ __('Amount') }}</small>
                    <h5>₦{{ number_format($requisition->total_amount, 2) }}</h5>
                    <small class="text-muted">{{ __('Description') }}</small>
                    <p class="mb-0">{{ $requisition->description ?: 'N/A' }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Items') }}</h5></div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Unit') }}</th>
                                    <th>{{ __('Unit Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requisition->items as $item)
                                    <tr>
                                        <td>{{ $item->item_name }}</td>
                                        <td>{{ number_format($item->quantity, 2) }}</td>
                                        <td>{{ $item->unit }}</td>
                                        <td>₦{{ number_format($item->unit_cost, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Approval Timeline') }}</h5></div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Actor') }}</th>
                                    <th>{{ __('Action') }}</th>
                                    <th>{{ __('Notes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requisition->events as $event)
                                    <tr>
                                        <td>{{ $event->created_at }}</td>
                                        <td>{{ $event->actor?->name ?: 'N/A' }}</td>
                                        <td>{{ ucfirst($event->action) }}</td>
                                        <td>{{ $event->notes ?: 'N/A' }}</td>
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
