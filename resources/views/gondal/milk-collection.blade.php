@extends('layouts.admin')

@section('page-title', __('Gondal Milk Collection'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Milk Collection') }}</li>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $view === 'records' ? 'active' : '' }}" href="{{ route('gondal.milk-collection', array_merge(request()->query(), ['view' => 'records'])) }}" role="tab">{{ __('Collection Records') }}</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $view === 'summary' ? 'active' : '' }}" href="{{ route('gondal.milk-collection', array_merge(request()->query(), ['view' => 'summary'])) }}" role="tab">{{ __('Daily MCC Summary') }}</a>
                </li>
            </ul>
        </div>
    </div>

    @if($view === 'records')
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-2 overflow-auto pb-2">
                @foreach ($statusTabs as $key => $label)
                    <a href="{{ route('gondal.milk-collection', array_merge(request()->query(), ['tab' => $key])) }}"
                        class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-light' }} text-nowrap">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordMilkModal">
                <i class="ti ti-plus me-1"></i> {{ __('Record Collection') }}
            </button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('gondal.milk-collection') }}">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Summary Date') }}</label>
                                <input type="date" class="form-control" name="date" value="{{ $selectedDate }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('From') }}</label>
                                <input type="date" class="form-control" name="from" value="{{ $from }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('To') }}</label>
                                <input type="date" class="form-control" name="to" value="{{ $to }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('Grade') }}</label>
                                <select class="form-control" name="grade">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach (['A', 'B', 'C'] as $grade)
                                        <option value="{{ $grade }}" @selected($selectedGrade === $grade)>{{ $grade }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button class="btn btn-primary w-100">{{ __('Go') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

@if($view === 'summary')
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Daily MCC Summary') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Cooperative') }}</th>
                                    <th>{{ __('MCC') }}</th>
                                    <th>{{ __('Farmers') }}</th>
                                    <th>{{ __('Liters') }}</th>
                                    <th>{{ __('Accepted') }}</th>
                                    <th>{{ __('Rejected') }}</th>
                                    <th>{{ __('Value') }}</th>
                                    <th>{{ __('Avg Fat %') }}</th>
                                    <th>{{ __('Grade A') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($summaryRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $row['mcc'] }}</td>
                                        <td>{{ $row['farmers_count'] }}</td>
                                        <td>{{ number_format($row['liters'], 2) }} L</td>
                                        <td>{{ $row['accepted_collections'] }}</td>
                                        <td>{{ $row['rejected_collections'] }}</td>
                                        <td>₦{{ number_format($row['accepted_value'], 2) }}</td>
                                        <td>{{ number_format($row['avg_fat_percent'], 2) }}</td>
                                        <td>{{ $row['grade_a_count'] }}/{{ $row['total_records'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
@endif

@if($view === 'records')

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Collection Records') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Farmer') }}</th>
                                    <th>{{ __('MCC') }}</th>
                                    <th>{{ __('Liters') }}</th>
                                    <th>{{ __('Fat %') }}</th>
                                    <th>{{ __('Grade') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $record)
                                    <tr>
                                        <td>{{ optional($record->collection_date)->toDateString() }}</td>
                                        <td>{{ $record->farmer?->name ?: 'N/A' }}</td>
                                        <td>{{ $record->collectionCenter?->location ?: $record->collectionCenter?->name ?: ($record->farmer?->cooperative?->location ?: $record->mcc_id) }}</td>
                                        <td>{{ number_format($record->quantity, 2) }} L</td>
                                        <td>{{ number_format((float) $record->fat_percentage, 2) }}</td>
                                        <td>{{ $record->quality_grade ?: '-' }}</td>
                                        <td>
                                            @if ($record->status === 'pending')
                                                <span class="badge bg-warning">{{ __('Pending') }}</span>
                                            @elseif ($record->status === 'validated')
                                                <span class="badge bg-success">{{ __('Validated') }}</span>
                                            @elseif ($record->status === 'rejected')
                                                <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($record->status) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('gondal.milk-collection.show', $record->id) }}" class="btn btn-sm btn-outline-info" title="{{ __('View Details') }}">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                @if ($record->status === 'pending')
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#validateMilkModal"
                                                        data-id="{{ $record->id }}"
                                                        data-farmer="{{ $record->farmer?->name }}"
                                                        data-qty="{{ $record->quantity }}">
                                                        <i class="ti ti-check"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                </div>
            </div>
        @endif
        </div>
    </div>
    <div class="modal fade" id="recordMilkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.milk-collection.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Record New Collection') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-2">{{ __('Quick Re-entry') }}</small>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($recentFarmers as $recentFarmer)
                                    <button type="button" class="btn btn-xs btn-outline-primary" onclick="document.getElementById('modal_farmer_id').value='{{ $recentFarmer->id }}'">
                                        {{ $recentFarmer->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Date') }}</label>
                            <input type="date" class="form-control" name="collection_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Farmer') }}</label>
                            <select class="form-control" name="farmer_id" id="modal_farmer_id" required>
                                @foreach ($farmers as $farmer)
                                    <option value="{{ $farmer->id }}">{{ $farmer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Liters') }}</label>
                            <input type="number" step="0.01" class="form-control" name="liters" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Temperature (Optional)') }}</label>
                            <input type="number" step="0.01" class="form-control" name="temperature">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <input type="text" class="form-control" name="rejection_reason">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Record Collection') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="validateMilkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="validateForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Validate Milk Collection') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            <ul class="list-unstyled mb-0 small">
                                <li><strong>{{ __('Farmer') }}:</strong> <span id="modal-farmer"></span></li>
                                <li><strong>{{ __('Quantity') }}:</strong> <span id="modal-qty"></span> L</li>
                            </ul>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Fat %') }}</label>
                                <input type="number" step="0.01" class="form-control" name="fat_percentage">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('SNF %') }}</label>
                                <input type="number" step="0.01" class="form-control" name="snf_percentage">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Grade') }}</label>
                                <select class="form-control" name="quality_grade" required>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C ({{ __('Reject') }})</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Adulteration Test') }}</label>
                                <select class="form-control" name="adulteration_test" required>
                                    <option value="passed">Passed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes / Rejection Reason') }}</label>
                            <textarea class="form-control" name="rejection_reason" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Complete Validation') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const validateModal = document.getElementById('validateMilkModal');
            if (validateModal) {
                validateModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const farmer = button.getAttribute('data-farmer');
                    const qty = button.getAttribute('data-qty');
                    
                    const form = document.getElementById('validateForm');
                    form.action = `{{ route('gondal.milk-collection.validate', ':id') }}`.replace(':id', id);
                    
                    document.getElementById('modal-farmer').textContent = farmer;
                    document.getElementById('modal-qty').textContent = qty;
                });
            }
        });
    </script>
    @endpush
@endsection
