@extends('layouts.admin')

@push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const importModal = document.getElementById('importCommunityModal');

            @if ($errors->import->any())
                if (importModal && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(importModal).show();
                }
            @endif
        });
    </script>
@endpush

@section('page-title', __('Gondal Communities'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Communities') }}</li>
@endsection

@section('action-btn')
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importCommunityModal">
            <i class="ti ti-file-import"></i> {{ __('Import Communities') }}
        </button>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createCommunityModal">
            <i class="ti ti-plus"></i> {{ __('Create Community') }}
        </button>
    </div>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="text-uppercase small text-muted fw-bold">{{ __('Location Setup') }}</div>
                    <h3 class="mb-1">{{ __('Communities') }}</h3>
                    <p class="text-muted mb-0">{{ __('Manage the master list of states, LGAs, and communities used by farmers and agents. You can create one community at a time or import them in bulk from CSV.') }}</p>
                </div>
                <div class="col-lg-4">
                    <div class="alert alert-light border mb-0">
                        <div class="fw-semibold mb-1">{{ __('Import Format') }}</div>
                        <div class="small text-muted">{{ __('Required columns: state, lga, community. Optional: status.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Communities') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($communityKpis['communities']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('States') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($communityKpis['states']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('LGAs') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($communityKpis['lgas']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Active') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($communityKpis['active']) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('gondal.communities') }}">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Search') }}</label>
                        <input type="text" class="form-control" name="search" value="{{ $search }}" placeholder="{{ __('Name, code, LGA, state') }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('State') }}</label>
                        <select class="form-select" name="state">
                            <option value="all">{{ __('All states') }}</option>
                            @foreach ($stateOptions as $state)
                                <option value="{{ $state }}" @selected($selectedState === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">{{ __('Apply') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pb-0">
            <h5 class="mb-1">{{ __('Community Register') }}</h5>
            <p class="text-muted mb-0">{{ __('These records drive the location dropdowns for agents and farmers.') }}</p>
        </div>
        <div class="card-body table-border-style">
            <div class="table-responsive">
                <table class="table datatable table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Community') }}</th>
                            <th>{{ __('State') }}</th>
                            <th>{{ __('LGA') }}</th>
                            <th class="text-end">{{ __('Farmers') }}</th>
                            <th class="text-end">{{ __('Agents') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($communities as $community)
                            <tr>
                                <td class="fw-semibold">{{ $community->code }}</td>
                                <td>{{ $community->name }}</td>
                                <td>{{ $community->state ?: '-' }}</td>
                                <td>{{ $community->lga ?: '-' }}</td>
                                <td class="text-end">{{ number_format($community->farmers_count) }}</td>
                                <td class="text-end">{{ number_format($community->agents_count) }}</td>
                                <td>
                                    <span class="badge {{ $community->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ __(ucfirst($community->status)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">{{ __('No communities found for the current filter.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importCommunityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.communities.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Import Communities') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        @if ($errors->import->any())
                            <div class="alert alert-danger">
                                {{ $errors->import->first('import_file') ?: $errors->import->first() }}
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">{{ __('CSV File') }}</label>
                            <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div class="small text-muted">
                                {{ __('Use the sample file if you want the exact import structure.') }}
                            </div>
                            <a href="{{ route('gondal.communities.sample') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-download"></i> {{ __('Download Sample CSV') }}
                            </a>
                        </div>

                        <div class="alert alert-light border mb-0">
                            <div class="fw-semibold mb-2">{{ __('Supported columns') }}</div>
                            <code class="d-block small text-wrap mb-2">state,lga,community,status</code>
                            <div class="small text-muted">{{ __('Only state, lga, and community are required. Status defaults to active.') }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Import CSV') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createCommunityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.communities.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Community') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Community Name') }}</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('State') }}</label>
                            <input type="text" class="form-control" name="state" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('LGA') }}</label>
                            <input type="text" class="form-control" name="lga" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select class="form-select" name="status" required>
                                <option value="active">{{ __('Active') }}</option>
                                <option value="inactive">{{ __('Inactive') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Save Community') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
