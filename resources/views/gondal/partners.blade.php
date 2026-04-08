@extends('layouts.admin')

@php
    use Illuminate\Support\Str;
@endphp

@push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const partnerCreateModal = document.getElementById('createPartnerModal');
            const partnerLoginDetailsModal = document.getElementById('partnerLoginDetailsModal');
            const loginDetails = @json(session('partner_login_details'));
            const copyFeedback = document.getElementById('partnerLoginCopyFeedback');

            @if ($errors->any())
                if (partnerCreateModal && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(partnerCreateModal).show();
                }
            @endif

            if (loginDetails && partnerLoginDetailsModal && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(partnerLoginDetailsModal).show();
            }

            document.querySelectorAll('[data-copy-text]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const value = button.getAttribute('data-copy-text') || '';
                    const label = button.getAttribute('data-copy-label') || '{{ __('Copied.') }}';

                    try {
                        await navigator.clipboard.writeText(value);
                        if (copyFeedback) {
                            copyFeedback.textContent = label;
                        }
                    } catch (error) {
                        if (copyFeedback) {
                            copyFeedback.textContent = '{{ __('Copy failed. Please copy manually.') }}';
                        }
                    }
                });
            });
        });
    </script>
@endpush

@section('page-title', __('Gondal Partners'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('gondal.dashboard') }}">{{ __('Gondal') }}</a></li>
    <li class="breadcrumb-item">{{ __('Partners') }}</li>
@endsection

@section('action-btn')
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createPartnerModal">
        <i class="ti ti-user-plus"></i> {{ __('Create Partner Login') }}
    </button>
@endsection

@section('content')
    @include('gondal.partials.alerts')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="text-uppercase small text-muted fw-bold">{{ __('Partner Access') }}</div>
                    <h3 class="mb-1">{{ __('Partners') }}</h3>
                    <p class="text-muted mb-0">{{ __('Create NGO or partner logins here. Partner users can sign in and see only the agents and performance linked to their own projects.') }}</p>
                </div>
                <div class="col-lg-4">
                    <div class="alert alert-light border mb-0">
                        <div class="fw-semibold mb-1">{{ __('Dashboard Scope') }}</div>
                        <div class="small text-muted">{{ __('Partners are given access to the Gondal agent dashboard and analytics for their assigned projects only.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Partners') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($partnerKpis['partners']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Active Logins') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($partnerKpis['active_logins']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Projects') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($partnerKpis['projects']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-4 border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted fw-bold text-uppercase">{{ __('Sponsored Agents') }}</small>
                    <h4 class="mb-0 mt-2 text-dark">{{ number_format($partnerKpis['agents']) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pb-0">
            <h5 class="mb-1">{{ __('Partner Logins') }}</h5>
            <p class="text-muted mb-0">{{ __('Each partner login is linked to project-owned agents and uses the same Gondal dashboard routes with scoped visibility.') }}</p>
        </div>
        <div class="card-body table-border-style">
            <div class="table-responsive">
                <table class="table datatable table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Partner / NGO') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th class="text-end">{{ __('Projects') }}</th>
                            <th class="text-end">{{ __('Agents') }}</th>
                            <th>{{ __('Access') }}</th>
                            <th>{{ __('Login') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partnerRows as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['partner']->name }}</div>
                                    <div class="small text-muted">
                                        {{ $row['projects']->pluck('project_name')->take(2)->implode(', ') ?: __('No linked projects yet') }}
                                        @if ($row['projects']->count() > 2)
                                            {{ __(' +:count more', ['count' => $row['projects']->count() - 2]) }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $row['partner']->email }}</td>
                                <td class="text-end">{{ number_format($row['project_count']) }}</td>
                                <td class="text-end">{{ number_format($row['agent_count']) }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-light text-dark border">{{ __('Agent Dashboard') }}</span>
                                        <span class="badge bg-light text-dark border">{{ __('Agent Analytics') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $row['partner']->is_enable_login ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $row['partner']->is_enable_login ? __('Enabled') : __('Disabled') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">{{ __('No partner logins have been created yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createPartnerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('gondal.partners.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create Partner Login') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border">
                            <div class="small text-muted">{{ __('This creates a partner client account with access to the Gondal agent dashboard and analytics for only their own project agents.') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Partner / NGO Name') }}</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Email Address') }}</label>
                            <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Password') }}</label>
                                <input type="text" class="form-control" name="password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Confirm Password') }}</label>
                                <input type="text" class="form-control" name="password_confirmation" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button class="btn btn-primary">{{ __('Create Partner Login') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if (session('partner_login_details'))
        @php
            $details = session('partner_login_details');
            $partnerName = $details['name'] ?? __('Partner');
            $partnerEmail = $details['email'] ?? '';
            $partnerPassword = $details['password'] ?? '';
            $loginUrl = $details['login_url'] ?? url('/login');
            $dashboardUrl = $details['dashboard_url'] ?? route('gondal.agents.dashboard');
            $analyticsUrl = $details['analytics_url'] ?? route('gondal.agents.analytics');
            $loginMessage = trim(
                __('Hello :name, your Gondal partner login has been created.', ['name' => $partnerName])."\n\n".
                __('Login URL: :url', ['url' => $loginUrl])."\n".
                __('Email: :email', ['email' => $partnerEmail])."\n".
                __('Password: :password', ['password' => $partnerPassword])."\n\n".
                __('After login, use the agent dashboard and analytics to view only your sponsored project agents.')."\n".
                __('Dashboard: :url', ['url' => $dashboardUrl])."\n".
                __('Analytics: :url', ['url' => $analyticsUrl])
            );
            $mailTo = 'mailto:'.rawurlencode($partnerEmail)
                .'?subject='.rawurlencode(__('Your Gondal Partner Login'))
                .'&body='.rawurlencode($loginMessage);
        @endphp

        <div class="modal fade" id="partnerLoginDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">{{ __('Partner Login Created') }}</h5>
                            <div class="small text-muted">{{ __('Share these credentials with the partner now.') }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('Partner / NGO') }}</div>
                                    <div class="fw-semibold">{{ $partnerName }}</div>
                                    <div class="text-muted">{{ $partnerEmail }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('Login URL') }}</div>
                                    <div class="fw-semibold small">{{ $loginUrl }}</div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-copy-text="{{ $loginUrl }}" data-copy-label="{{ __('Login URL copied.') }}">
                                        <i class="ti ti-copy"></i> {{ __('Copy Login URL') }}
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="border rounded-3 p-3">
                                    <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('Password') }}</div>
                                    <div class="fw-semibold font-monospace">{{ $partnerPassword }}</div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-copy-text="{{ $partnerPassword }}" data-copy-label="{{ __('Password copied.') }}">
                                        <i class="ti ti-copy"></i> {{ __('Copy Password') }}
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="border rounded-3 p-3 bg-light">
                                    <div class="small text-muted text-uppercase fw-semibold mb-2">{{ __('Full Message') }}</div>
                                    <pre class="mb-3 small text-dark" style="white-space: pre-wrap;">{{ $loginMessage }}</pre>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-primary" data-copy-text="{{ $loginMessage }}" data-copy-label="{{ __('Full login details copied.') }}">
                                            <i class="ti ti-copy"></i> {{ __('Copy Full Details') }}
                                        </button>
                                        <a href="{{ $mailTo }}" class="btn btn-outline-secondary">
                                            <i class="ti ti-mail"></i> {{ __('Send by Email') }}
                                        </a>
                                    </div>
                                    <div class="small text-success mt-3" id="partnerLoginCopyFeedback"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
