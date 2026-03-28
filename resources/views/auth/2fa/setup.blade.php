@extends('layouts.admin')
@section('page-title')
    {{ __('2FA Setting') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('2FA Setting') }}</li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Google 2FA Security') }}</h5>
                </div>
                <div class="card-body text-center">
                    @if(auth()->user()->google2fa_enabled)
                        <div class="mb-4">
                            <span class="theme-avtar bg-success mb-3" style="width: 60px; height: 60px;">
                                <i class="ti ti-shield-check text-white" style="font-size: 30px;"></i>
                            </span>
                            <h4 class="text-success">{{ __('2FA is Enabled') }}</h4>
                            <p class="text-muted">{{ __('Your account is protected with an extra layer of security.') }}</p>
                        </div>
                        <hr>
                        <form method="POST" action="{{ route('2fa.disable') }}">
                            @csrf
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to disable 2FA?')">{{ __('Disable 2FA') }}</button>
                            </div>
                        </form>
                    @else
                        <p>{{ __('Scan the QR code below with your Google Authenticator app.') }}</p>
                        <div class="mb-4">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrCodeUrl) }}" alt="QR Code" class="img-fluid border p-2 bg-white shadow-sm rounded">
                        </div>
                        <div class="mb-3">
                            <p class="mb-1 text-muted small">{{ __('Or enter this secret key manually:') }}</p>
                            <code class="d-block p-2 bg-light border rounded f-w-600 text-primary">{{ $secret }}</code>
                        </div>
                        <hr>
                        <form method="POST" action="{{ route('2fa.activate') }}">
                            @csrf
                            <div class="form-group text-start">
                                <label class="form-label">{{ __('Enter 6-digit Verification Code') }}</label>
                                <input type="text" name="one_time_password" class="form-control" placeholder="123456" required maxlength="6" autocomplete="off">
                                @if (session('error'))
                                    <span class="text-danger small">{{ session('error') }}</span>
                                @endif
                            </div>
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-primary">{{ __('Enable 2FA Now') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Why use 2FA?') }}</h5>
                </div>
                <div class="card-body">
                    <p>{{ __('Two-Factor Authentication (2FA) significantly improves the security of your ERP account by requiring two forms of identification.') }}</p>
                    <div class="d-flex align-items-start mb-3">
                        <div class="theme-avtar bg-primary me-3">
                            <i class="ti ti-lock text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">{{ __('Protect your login') }}</h6>
                            <p class="text-muted text-sm mb-0">{{ __('Even if someone steals your password, they can\'t access your account without your phone.') }}</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start mb-3">
                        <div class="theme-avtar bg-info me-3">
                            <i class="ti ti-device-mobile text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">{{ __('Industry Standard') }}</h6>
                            <p class="text-muted text-sm mb-0">{{ __('Works with Google Authenticator, Authy, Microsoft Authenticator, and more.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
