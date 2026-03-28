@extends('layouts.admin')

@section('page-title', __('Gondal Milk Collection'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Milk Collection') }}</li>
@endsection

@section('content')
    @include('gondal.partials.nav')
    @include('gondal.partials.alerts')

    <div class="row">
        <div class="col-lg-8">
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
                                        <td>{{ number_format($row['avg_fat_percent'], 2) }}</td>
                                        <td>{{ $row['grade_a_count'] }}/{{ $row['total_records'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $record)
                                    <tr>
                                        <td>{{ optional($record->collection_date)->toDateString() }}</td>
                                        <td>{{ $record->farmer?->name ?: 'N/A' }}</td>
                                        <td>{{ $record->farmer?->cooperative?->location ?: $record->mcc_id }}</td>
                                        <td>{{ number_format($record->quantity, 2) }} L</td>
                                        <td>{{ number_format((float) $record->fat_percentage, 2) }}</td>
                                        <td>{{ $record->quality_grade }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Record Collection') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block mb-2">{{ __('Quick Re-entry') }}</small>
                        @foreach ($recentFarmers as $recentFarmer)
                            <button type="button" class="btn btn-sm btn-outline-primary mb-1" onclick="document.getElementById('farmer_id').value='{{ $recentFarmer->id }}'">
                                {{ $recentFarmer->name }}
                            </button>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('gondal.milk-collection.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('Date') }}</label>
                            <input type="date" class="form-control" name="collection_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Farmer') }}</label>
                            <select class="form-control" name="farmer_id" id="farmer_id" required>
                                @foreach ($farmers as $farmer)
                                    <option value="{{ $farmer->id }}">{{ $farmer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Liters') }}</label>
                                <input type="number" step="0.01" class="form-control" name="liters" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Fat %') }}</label>
                                <input type="number" step="0.01" class="form-control" name="fat_percent" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Temperature') }}</label>
                                <input type="number" step="0.01" class="form-control" name="temperature">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('SNF %') }}</label>
                                <input type="number" step="0.01" class="form-control" name="snf_percent">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Grade') }}</label>
                                <select class="form-control" name="grade" required>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
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
                            <label class="form-label">{{ __('Rejection Reason') }}</label>
                            <input type="text" class="form-control" name="rejection_reason">
                        </div>
                        <button class="btn btn-primary w-100">{{ __('Save Collection') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
