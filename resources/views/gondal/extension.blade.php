@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
@endphp

@section('page-title')
    {{ __('Manage Extension') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Extension') }}</li>
@endsection

@section('action-btn')
    @php
        $addLabel = match ($tab) {
            'training' => __('Create Training (Training)'),
            'agents' => __('Log Visit (Agents)'),
            'performance' => __('Log Visit (Performance)'),
            default => __('Log Visit (Visits)'),
        };
    @endphp
    <div class="float-end d-flex">
        @if (GondalPermissionRegistry::can(auth()->user(), 'extension', $tab, 'import'))
            <button type="button" class="btn btn-sm bg-brown-subtitle me-2" data-bs-toggle="modal"
                title="{{ $tab === 'training' ? __('Import Training CSV') : __('Import Visits CSV') }}"
                data-bs-target="#importExtensionModal">
                <i class="ti ti-file-import"></i>
            </button>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'extension', $tab, 'export'))
            <a href="{{ route('gondal.extension.export', array_merge(request()->query(), ['tab' => $tab])) }}"
                class="btn btn-sm btn-secondary me-2" title="{{ __('Export') }}">
                <i class="ti ti-file-export"></i>
            </a>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'extension', $tab, 'create'))
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                title="{{ $addLabel }}"
                data-bs-target="#{{ $tab === 'training' ? 'createTrainingModal' : 'logVisitModal' }}">
                <i class="ti ti-plus"></i>
                <span class="ms-1">{{ $addLabel }}</span>
            </button>
        @endif
    </div>
@endsection

@push('script-page')
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('{{ $tab === 'training' ? 'createTrainingModal' : 'logVisitModal' }}');

                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
    @if ($errors->hasBag('import') && $errors->import->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('importExtensionModal');

                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
@endpush

@section('content')
    @include('gondal.partials.alerts')

    <div class="row">
        @foreach ($summaryCards as $card)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ __($card['label']) }}</small>
                        <h4 class="mb-0 mt-2">{{ $card['value'] }}</h4>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex gap-2 mb-4">
        @foreach ($visibleTabs as $visibleTab)
            <a href="{{ route('gondal.extension', ['tab' => $visibleTab['key']]) }}"
                class="btn btn-sm {{ $tab === $visibleTab['key'] ? 'btn-primary' : 'btn-light' }}">
                {{ __($visibleTab['label']) }}
            </a>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-12">
            @if ($tab === 'agents')
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Officer') }}</th>
                                        <th>{{ __('Visits') }}</th>
                                        <th>{{ __('Farmers') }}</th>
                                        <th>{{ __('Avg Score') }}</th>
                                        <th>{{ __('Latest Visit') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($agents as $agent)
                                        <tr>
                                            <td>{{ $agent['officer'] }}</td>
                                            <td>{{ $agent['visits'] }}</td>
                                            <td>{{ $agent['farmers'] }}</td>
                                            <td>{{ number_format($agent['avg_score'], 1) }}</td>
                                            <td>{{ $agent['latest_visit'] ?: 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'visits')
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Agent') }}</th>
                                        <th>{{ __('Farmer') }}</th>
                                        <th>{{ __('Topic') }}</th>
                                        <th>{{ __('Linked Sale') }}</th>
                                        <th>{{ __('Score') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($visits as $visit)
                                        <tr>
                                            <td>{{ optional($visit->visit_date)->toDateString() }}</td>
                                            <td>{{ $visit->agentProfile?->agent_code ?: '-' }}</td>
                                            <td>{{ $visit->farmer?->name ?: 'N/A' }}</td>
                                            <td>{{ $visit->topic }}</td>
                                            <td>
                                                @if ($visit->sale)
                                                    {{ $visit->sale->item?->name ?: __('Drug') }} · ₦{{ number_format($visit->sale->total_amount, 2) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $visit->performance_score }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'training')
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Location') }}</th>
                                        <th>{{ __('Attendees') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trainings as $training)
                                        <tr>
                                            <td>{{ optional($training->training_date)->toDateString() }}</td>
                                            <td>{{ $training->title }}</td>
                                            <td>{{ $training->location }}</td>
                                            <td>{{ $training->attendees }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Farmer') }}</th>
                                        <th>{{ __('Visits') }}</th>
                                        <th>{{ __('Avg Score') }}</th>
                                        <th>{{ __('Last Topic') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($performanceRows as $row)
                                        <tr>
                                            <td>{{ $row['farmer'] }}</td>
                                            <td>{{ $row['visits'] }}</td>
                                            <td>{{ number_format($row['avg_score'], 1) }}</td>
                                            <td>{{ $row['last_topic'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="importExtensionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.extension.import') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $tab === 'training' ? __('Import Training CSV') : __('Import Visits CSV') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            {{ $tab === 'training'
                                ? __('Expected columns: training_date, title, location, attendees.')
                                : __('Expected columns: visit_date, farmer_code, officer_name, topic, performance_score.') }}
                        </p>
                        <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Import CSV') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($tab === 'training')
        <div class="modal fade" id="createTrainingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                <form method="POST" action="{{ route('gondal.extension.trainings.store') }}">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="modal-header">
                            <h5 class="modal-title">{{ __('Create Training') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Date') }}</label>
                                <input type="date" class="form-control" name="training_date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Title') }}</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Location') }}</label>
                                <input type="text" class="form-control" name="location" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Attendees') }}</label>
                                <input type="number" class="form-control" name="attendees" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button class="btn btn-primary">{{ __('Save Training') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="modal fade" id="logVisitModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('gondal.extension.visits.store') }}">
                        @csrf
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Log Visit') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Date') }}</label>
                                <input type="date" class="form-control" name="visit_date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Agent') }}</label>
                                <select class="form-control" name="agent_profile_id">
                                    @foreach ($agentProfiles as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->agent_code }} - {{ $agent->full_name ?: ($agent->outlet_name ?: $agent->user?->name ?: $agent->vender?->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Farmer') }}</label>
                                <select class="form-control" name="farmer_id" required>
                                    @foreach ($farmers as $farmer)
                                        <option value="{{ $farmer->id }}">{{ $farmer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Officer Name') }}</label>
                                <input type="text" class="form-control" name="officer_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Topic') }}</label>
                                <input type="text" class="form-control" name="topic" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Performance Score') }}</label>
                                <input type="number" class="form-control" name="performance_score" min="0" max="100" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Visit Notes') }}</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="{{ __('Treatment details, animal condition, follow-up actions.') }}"></textarea>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="record_sale" value="1" id="recordVisitSale">
                                <label class="form-check-label" for="recordVisitSale">{{ __('Bill drugs from agent sub-store during this visit') }}</label>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Drug / Supply') }}</label>
                                    <select class="form-control" name="inventory_item_id">
                                        <option value="">{{ __('Select product') }}</option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item->id }}">{{ $item->name }} (₦{{ number_format($item->unit_price, 2) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">{{ __('Qty') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="quantity">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">{{ __('Unit Price') }}</label>
                                    <input type="number" step="0.01" class="form-control" name="unit_price">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Payment Method') }}</label>
                                    <select class="form-control" name="payment_method">
                                        <option value="Cash">{{ __('Cash') }}</option>
                                        <option value="Credit">{{ __('Credit') }}</option>
                                        <option value="Transfer">{{ __('Transfer') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Credit Due Date') }}</label>
                                    <input type="date" class="form-control" name="due_date">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button class="btn btn-primary">{{ __('Save Visit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
