@extends('layouts.admin')

@php
    use App\Support\GondalPermissionRegistry;
@endphp

@section('page-title')
    {{ __('Manage Operations') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Operations') }}</li>
@endsection

@section('action-btn')
    @php
        $addLabel = match ($tab) {
            'summary' => __('Add Cost Entry (Weekly Summary)'),
            'ranking' => __('Add Cost Entry (Center Ranking)'),
            default => __('Add Cost Entry (Costs)'),
        };
    @endphp
    <div class="float-end d-flex">
        @if (GondalPermissionRegistry::can(auth()->user(), 'operations', $tab, 'import'))
            <button type="button" class="btn btn-sm btn-secondary me-2" data-bs-toggle="modal"
                data-bs-target="#importOperationsModal" title="{{ __('Import Operations CSV') }}">
                <i class="ti ti-file-import"></i>
            </button>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'operations', $tab, 'export'))
            <a href="{{ route('gondal.operations.export', array_merge(request()->query(), ['tab' => $tab])) }}"
                class="btn btn-sm btn-secondary me-2" title="{{ __('Export') }}">
                <i class="ti ti-file-export"></i>
            </a>
        @endif
        @if (GondalPermissionRegistry::can(auth()->user(), 'operations', $tab, 'create'))
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCostModal"
                title="{{ $addLabel }}">
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
                const modalElement = document.getElementById('addCostModal');

                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif
    @if ($errors->hasBag('import') && $errors->import->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('importOperationsModal');

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
        @foreach ($cards as $card)
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
            <a href="{{ route('gondal.operations', ['tab' => $visibleTab['key']]) }}"
                class="btn btn-sm {{ $tab === $visibleTab['key'] ? 'btn-primary' : 'btn-light' }}">
                {{ __($visibleTab['label']) }}
            </a>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-12">
            @if ($tab === 'costs')
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Cooperative') }}</th>
                                        <th>{{ __('Category') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Approval Status') }}</th>
                                        <th>{{ __('Description') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($costs as $cost)
                                        <tr>
                                            <td>{{ optional($cost->cost_date)->toDateString() }}</td>
                                            <td>{{ $cost->cooperative?->name ?: 'N/A' }}</td>
                                            <td>{{ $cost->category }}</td>
                                            <td>₦{{ number_format($cost->amount, 2) }}</td>
                                            <td>{{ ucfirst($cost->status) }}</td>
                                            <td>{{ ucfirst($cost->approval_status) }}</td>
                                            <td>{{ $cost->description ?: 'N/A' }}</td>
                                            <td>
                                                @if (GondalPermissionRegistry::can(auth()->user(), 'operations', 'costs', 'edit'))
                                                    @php
                                                        $userType = strtolower(auth()->user()->type);
                                                        $isLead = in_array($userType, ['component lead', 'executive director', 'company', 'super admin']);
                                                        $isFinance = in_array($userType, ['finance officer', 'finance', 'executive director', 'company', 'super admin']);
                                                    @endphp
                                                    @if ($cost->approval_status === 'pending' && $isLead)
                                                        <form method="POST" action="{{ route('gondal.operations.approve', $cost->id) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success" title="{{ __('Component Lead Approve') }}">
                                                                <i class="ti ti-check"></i> {{ __('Review') }}
                                                            </button>
                                                        </form>
                                                    @elseif ($cost->approval_status === 'reviewed' && $isFinance)
                                                        <form method="POST" action="{{ route('gondal.operations.approve', $cost->id) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success" title="{{ __('Finance Approve') }}">
                                                                <i class="ti ti-check"></i> {{ __('Approve') }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif ($tab === 'summary')
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Week') }}</th>
                                        <th>{{ __('Entries') }}</th>
                                        <th>{{ __('Total') }}</th>
                                        <th>{{ __('Average') }}</th>
                                        <th>{{ __('Top Category') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($weeklySummary as $row)
                                        <tr>
                                            <td>{{ $row['week'] }}</td>
                                            <td>{{ $row['entries'] }}</td>
                                            <td>₦{{ number_format($row['total'], 2) }}</td>
                                            <td>₦{{ number_format($row['average'], 2) }}</td>
                                            <td>{{ $row['top_category'] ?: 'N/A' }}</td>
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
                                        <th>{{ __('Center') }}</th>
                                        <th>{{ __('Entries') }}</th>
                                        <th>{{ __('Total') }}</th>
                                        <th>{{ __('Average') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($centerRanking as $row)
                                        <tr>
                                            <td>{{ $row['name'] }}</td>
                                            <td>{{ $row['entries'] }}</td>
                                            <td>₦{{ number_format($row['total'], 2) }}</td>
                                            <td>₦{{ number_format($row['average'], 2) }}</td>
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

    <div class="modal fade" id="importOperationsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.operations.import') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Import Operations CSV') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">{{ __('Expected columns: cost_date, cooperative_code, category, amount, description, status.') }}</p>
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

    <div class="modal fade" id="addCostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.operations.store') }}">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Add Cost Entry') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Date') }}</label>
                            <input type="date" class="form-control" name="cost_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Cooperative') }}</label>
                            <select class="form-control" name="cooperative_id" required>
                                @foreach ($cooperatives as $cooperative)
                                    <option value="{{ $cooperative->id }}">{{ $cooperative->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Category') }}</label>
                            <input type="text" class="form-control" name="category" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select class="form-control" name="status" required>
                                @foreach (['pending', 'approved', 'paid'] as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Save Cost') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
