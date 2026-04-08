@extends('layouts.admin')

@section('page-title', __('Milk Collection Detail'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('gondal.milk-collection') }}">{{ __('Milk Collections') }}</a></li>
    <li class="breadcrumb-item">{{ __('Detail') }}</li>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="row">
        <!-- Collection Info -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Farmer Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-sm bg-primary text-white rounded p-2 me-3">
                            <i class="ti ti-user fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $collection->farmer?->name ?: 'N/A' }}</h6>
                            <small class="text-muted">{{ $collection->farmer?->phone_number ?: __('No phone') }}</small>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">{{ __('Cooperative') }}</span>
                            <span class="fw-bold">{{ $collection->farmer?->cooperative?->name ?: 'N/A' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">{{ __('Quantity') }}</span>
                            <span class="fw-bold text-primary">{{ number_format($collection->quantity, 2) }} L</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">{{ __('Status') }}</span>
                            @if ($collection->status === 'pending')
                                <span class="badge bg-warning">{{ __('Pending Validation') }}</span>
                            @elseif ($collection->status === 'validated')
                                <span class="badge bg-success">{{ __('Validated') }}</span>
                            @else
                                <span class="badge bg-danger">{{ __('Rejected') }}</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">{{ __('Recorded At') }}</span>
                            <span>{{ $collection->collection_date->format('M d, Y') }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Validation Form or Results -->
            @if($collection->status === 'pending')
                <div class="card border-primary shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0 text-white">{{ __('Grading & Validation') }}</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('gondal.milk-collection.validate', $collection->id) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">{{ __('Fat Percentage (%)') }}</label>
                                <input type="number" step="0.01" class="form-control" name="fat_percentage">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('SNF Percentage (%)') }}</label>
                                <input type="number" step="0.01" class="form-control" name="snf_percentage">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Temperature (°C)') }}</label>
                                <input type="number" step="0.1" class="form-control" name="temperature" value="{{ $collection->temperature }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Quality Grade') }}</label>
                                <select class="form-control" name="quality_grade" required>
                                    <option value="A">Grade A</option>
                                    <option value="B">Grade B</option>
                                    <option value="C">Grade C (Reject)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Adulteration Test') }}</label>
                                <select class="form-control" name="adulteration_test" required>
                                    <option value="passed">Passed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Notes / Rejection Reason') }}</label>
                                <textarea class="form-control" name="rejection_reason" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti ti-check me-2"></i>{{ __('Approve & Credit Farmer') }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="text-uppercase small fw-bold text-muted mb-3">{{ __('Validation Result') }}</h6>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">{{ __('Fat %') }}</small>
                                <span class="fw-bold">{{ number_format($collection->fat_percentage, 2) }}%</span>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">{{ __('SNF %') }}</small>
                                <span class="fw-bold">{{ number_format($collection->snf_percentage, 2) }}%</span>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">{{ __('Grade') }}</small>
                                <span class="badge {{ $collection->quality_grade === 'A' ? 'bg-success' : ($collection->quality_grade === 'B' ? 'bg-info' : 'bg-danger') }}">
                                    {{ __('Grade') }} {{ $collection->quality_grade }}
                                </span>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">{{ __('Price Applied') }}</small>
                                <span class="fw-bold text-success">₦{{ number_format($collection->total_price, 2) }}</span>
                            </div>
                        </div>
                        @if($collection->rejection_reason)
                            <div class="mt-2 text-danger small">
                                <strong>{{ __('Reason') }}:</strong> {{ $collection->rejection_reason }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- History & Metrics -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Farmer Collection History (Recent)') }}</h5>
                    <a href="{{ route('vender.show', $collection->farmer_id) }}" class="btn btn-sm btn-outline-primary">{{ __('View Full Profile') }}</a>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th>{{ __('Grade') }}</th>
                                    <th>{{ __('Total Price') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentHistory as $history)
                                    <tr>
                                        <td>{{ $history->collection_date->format('Y-m-d') }}</td>
                                        <td>{{ number_format($history->quantity, 2) }} L</td>
                                        <td>{{ $history->quality_grade }}</td>
                                        <td>₦{{ number_format($history->total_price, 2) }}</td>
                                        <td>
                                            <span class="badge {{ $history->status === 'validated' ? 'bg-success' : ($history->status === 'pending' ? 'bg-warning' : 'bg-danger') }}">
                                                {{ ucfirst($history->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">{{ __('No previous collections found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Analysis Section -->
            <div class="card shadow-sm">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 text-dark">{{ __('Quality Analysis Guide') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center h-100 bg-light-success">
                                <h6 class="fw-bold text-success mb-1">{{ __('Grade A') }}</h6>
                                <p class="small text-muted mb-0">{{ __('Fat > 3.5%') }}<br>{{ __('SNF > 8.0%') }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center h-100 bg-light-info">
                                <h6 class="fw-bold text-info mb-1">{{ __('Grade B') }}</h6>
                                <p class="small text-muted mb-0">{{ __('Fat 2.8% - 3.5%') }}<br>{{ __('SNF 7.5% - 8.0%') }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center h-100 bg-light-danger">
                                <h6 class="fw-bold text-danger mb-1">{{ __('Grade C / Reject') }}</h6>
                                <p class="small text-muted mb-0">{{ __('Below standards or Adulterated') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        <strong>{{ __('SNF') }}</strong>: {{ __('Solids-Not-Fat (Proteins, Lactose, Minerals).') }} |
                        <strong>{{ __('Fat') }}</strong>: {{ __('Total Lipid content (Butterfat).') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
