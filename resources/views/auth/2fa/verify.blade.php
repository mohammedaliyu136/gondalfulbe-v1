@extends('layouts.auth')
@section('page-title')
    {{ __('2FA Verification') }}
@endsection
@section('content')
    <div class="card-body">
        <div class="text-center mb-4">
             @php
                $setting = \App\Models\Utility::settings();
                $logo = \App\Models\Utility::get_file('uploads/logo/');
                $company_logo = \App\Models\Utility::getValByName('company_logo_dark');
            @endphp
            <img src="{{$logo . '/' . (isset($company_logo) && !empty($company_logo) ? $company_logo : 'logo-dark.png')}}" alt="{{ config('app.name', 'Gondal Fulbe') }}" class="logo custom-logo-size mb-4" style="max-height: 72px; width: auto;">
            <h2 class="mb-2 f-w-600">{{ __('2FA Verification') }}</h2>
            <p class="text-muted">{{ __('Please enter the verification code from your authenticator app.') }}</p>
        </div>
        <form method="POST" action="{{ route('2fa.verify.post') }}">
            @csrf
            <div class="form-group mb-4">
                <label class="form-label">{{ __('Verification Code') }}</label>
                <input id="one_time_password" type="text" class="form-control text-center f-w-700" name="one_time_password" placeholder="000 000" required autofocus autocomplete="off" style="font-size: 24px; letter-spacing: 5px;">
                @if (session('error'))
                    <span class="text-danger d-block mt-2 small">{{ session('error') }}</span>
                @endif
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-block">{{ __('Verify Now') }}</button>
            </div>
            <div class="mt-4 text-center">
                <p class="text-muted text-sm">{{ __('Lost your device?') }} <a href="mailto:{{ \App\Models\Utility::getValByName('company_email') }}" class="text-primary">{{ __('Contact Administrator') }}</a></p>
            </div>
        </form>
    </div>
@endsection
