@extends('layouts.admin')

@section('page-title')
    {{ __('Create Farmer') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('vender.index') }}">{{ __('Farmer') }}</a></li>
    <li class="breadcrumb-item">{{ __('Create') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
{{Form::open(array('route'=>'vender.store','method'=>'post', 'class'=>'needs-validation', 'novalidate', 'enctype'=>'multipart/form-data'))}}
    <style>
        .gs-farmer-shell {
            max-width: 1080px;
            margin: 0 auto;
        }

        .gs-farmer-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .gs-farmer-kicker {
            display: inline-block;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #51459d;
            font-size: 0.78rem;
            font-weight: 700;
            margin-bottom: 0.65rem;
        }

        .gs-farmer-heading {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
        }

        .gs-farmer-subheading {
            margin: 0.45rem 0 0;
            color: #6b7280;
            max-width: 620px;
        }

        .gs-farmer-progress {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
            margin: 0 0 1.5rem;
        }

        .gs-farmer-progress-bar {
            height: 100%;
            width: 33.3333%;
            border-radius: 999px;
            background: linear-gradient(90deg, #51459d 0%, #7c3aed 100%);
            transition: width 0.2s ease;
        }

        .gs-farmer-steps {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .gs-farmer-step-pill {
            min-width: 140px;
            padding: 0.75rem 0.9rem;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }

        .gs-farmer-step-pill:hover {
            transform: translateY(-2px);
            border-color: #c7d2fe;
        }

        .gs-farmer-step-pill.is-active {
            background: #eef2ff;
            border-color: #51459d;
        }

        .gs-farmer-step-pill.is-done {
            background: #ecfdf3;
            border-color: #16a34a;
        }

        .gs-farmer-step-index {
            display: inline-flex;
            width: 26px;
            height: 26px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #e5e7eb;
            color: #111827;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .gs-farmer-step-pill.is-active .gs-farmer-step-index {
            background: #51459d;
            color: #fff;
        }

        .gs-farmer-step-pill.is-done .gs-farmer-step-index {
            background: #16a34a;
            color: #fff;
        }

        .gs-farmer-step-title {
            display: block;
            font-size: 0.92rem;
            font-weight: 700;
            color: #1f2937;
        }

        .gs-farmer-step-copy {
            display: block;
            font-size: 0.78rem;
            color: #6b7280;
            margin-top: 0.15rem;
        }

        .gs-farmer-image-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .gs-farmer-image-preview {
            width: 160px;
            height: 160px;
            border: 1px dashed #c7ced9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8f9fb;
            color: #8a94a6;
            text-align: center;
            font-size: 0.875rem;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .gs-farmer-image-preview:hover {
            border-color: #51459d;
            background: #f2f4ff;
        }

        .gs-farmer-image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gs-farmer-image-input {
            display: none;
        }

        .gs-farmer-form-step {
            display: none;
        }

        .gs-farmer-form-step.is-active {
            display: block;
        }

        .gs-farmer-step-panel {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #fcfdff;
            padding: 1.25rem;
        }

        .gs-farmer-step-panel + .gs-farmer-step-panel {
            margin-top: 1rem;
        }

        .gs-farmer-step-heading {
            margin: 0 0 0.25rem;
            font-size: 1.15rem;
            font-weight: 700;
            color: #111827;
        }

        .gs-farmer-step-text {
            margin: 0 0 1.25rem;
            color: #6b7280;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.querySelector('input[name="profile_photo"]');
            const preview = document.getElementById('farmerProfilePhotoPreview');
            const form = document.querySelector('form[action="{{ route('vender.store') }}"]');
            const steps = Array.from(document.querySelectorAll('[data-farmer-step]'));
            const pills = Array.from(document.querySelectorAll('[data-farmer-step-pill]'));
            const progressBar = document.querySelector('[data-farmer-progress-bar]');
            let currentStep = 0;

            if (!input || !preview || input.dataset.previewBound === 'true') {
                return;
            }

            input.dataset.previewBound = 'true';

            preview.addEventListener('click', function () {
                input.click();
            });

            preview.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    input.click();
                }
            });

            input.addEventListener('change', function (event) {
                const file = event.target.files && event.target.files[0];

                if (!file) {
                    preview.innerHTML = '<span>{{ __('Square preview') }}</span>';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (loadEvent) {
                    preview.innerHTML = '<img src="' + loadEvent.target.result + '" alt="{{ __('Farmer Profile Photo Preview') }}">';
                };
                reader.readAsDataURL(file);
            });

            if (form && steps.length) {
                const validateStep = function (index) {
                    const step = steps[index];
                    const fields = Array.from(step.querySelectorAll('input, select, textarea'))
                        .filter(function (field) {
                            return !field.disabled && field.type !== 'hidden' && field.offsetParent !== null;
                        });

                    for (const field of fields) {
                        if (!field.checkValidity()) {
                            field.reportValidity();
                            field.focus();
                            return false;
                        }
                    }

                    return true;
                };

                const updateSteps = function () {
                    steps.forEach(function (step, index) {
                        step.classList.toggle('is-active', index === currentStep);
                    });

                    pills.forEach(function (pill, index) {
                        pill.classList.toggle('is-active', index === currentStep);
                        pill.classList.toggle('is-done', index < currentStep);
                    });

                    if (progressBar) {
                        progressBar.style.width = (((currentStep + 1) / steps.length) * 100) + '%';
                    }
                };

                form.querySelectorAll('[data-step-next]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (!validateStep(currentStep)) {
                            return;
                        }

                        if (currentStep < steps.length - 1) {
                            currentStep += 1;
                            updateSteps();
                        }
                    });
                });

                form.querySelectorAll('[data-step-prev]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (currentStep > 0) {
                            currentStep -= 1;
                            updateSteps();
                        }
                    });
                });

                pills.forEach(function (pill, index) {
                    pill.addEventListener('click', function () {
                        if (index <= currentStep || validateStep(currentStep)) {
                            currentStep = index;
                            updateSteps();
                        }
                    });
                });

                updateSteps();
            }
        });
    </script>

    <div class="gs-farmer-shell">
    <div class="gs-farmer-header">
        <div>
            <span class="gs-farmer-kicker">{{ __('Farmer Onboarding') }}</span>
            <h2 class="gs-farmer-heading">{{ __('Create Farmer Profile') }}</h2>
            <p class="gs-farmer-subheading">{{ __('Use the guided steps below to capture the farmer profile, billing address, and shipping details without scrolling through one long form.') }}</p>
        </div>
    </div>

    <div class="gs-farmer-progress">
        <div class="gs-farmer-progress-bar" data-farmer-progress-bar></div>
    </div>

    <div class="gs-farmer-steps">
        <div class="gs-farmer-step-pill is-active" data-farmer-step-pill>
            <span class="gs-farmer-step-index">1</span>
            <span class="gs-farmer-step-title">{{ __('Basic Info') }}</span>
            <span class="gs-farmer-step-copy">{{ __('Profile and farmer details') }}</span>
        </div>
        <div class="gs-farmer-step-pill" data-farmer-step-pill>
            <span class="gs-farmer-step-index">2</span>
            <span class="gs-farmer-step-title">{{ __('Billing Address') }}</span>
            <span class="gs-farmer-step-copy">{{ __('Billing contact and address') }}</span>
        </div>
        <div class="gs-farmer-step-pill" data-farmer-step-pill>
            <span class="gs-farmer-step-index">3</span>
            <span class="gs-farmer-step-title">{{ __('Shipping Address') }}</span>
            <span class="gs-farmer-step-copy">{{ __('Delivery details and finish') }}</span>
        </div>
    </div>

    <div class="gs-farmer-form-step is-active" data-farmer-step>
    <div class="gs-farmer-step-panel">
    <h5 class="gs-farmer-step-heading">{{__('Basic Info')}}</h5>
    <p class="gs-farmer-step-text">{{ __('Start with the farmer identity, cooperative assignment, payment readiness, and supporting files.') }}</p>
    <div class="gs-farmer-image-upload">
        <label class="form-label mb-0">{{__('Profile Photo')}}</label>
        <div class="gs-farmer-image-preview" id="farmerProfilePhotoPreview" role="button" tabindex="0">
            <span>{{ __('Square preview') }}</span>
        </div>
        <input type="file" name="profile_photo" class="gs-farmer-image-input" accept="image/*">
    </div>
    <div class="row">
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('name',__('Name'),array('class'=>'form-label')) }}<x-required></x-required>
                {{Form::text('name',null,array('class'=>'form-control','required'=>'required' , 'placeholder'=>__('Enter Name')))}}

            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                <x-mobile label="{{__('Contact')}}" name="contact" value="{{old('contact')}}" required placeholder="Enter Contact"></x-mobile>

            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('email',__('Email'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::email('email',null,array('class'=>'form-control','required'=>'required' , 'placeholder' => __('Enter email')))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('tax_number',__('Tax Number'),['class'=>'form-label'])}}
                {{Form::text('tax_number',null,array('class'=>'form-control' , 'placeholder'=>__('Enter Tax Number')))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('balance',__('Balance'),['class'=>'form-label'])}}
                {{Form::number('balance',null,array('class'=>'form-control' , 'placeholder' => __('Enter Balance')))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('cooperative_id',__('Cooperative'),['class'=>'form-label'])}}
                {{Form::select('cooperative_id', ['' => 'Select Cooperative'] + $cooperatives, null, array('class'=>'form-control select'))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('gender',__('Gender'),['class'=>'form-label'])}}
                {{Form::select('gender', ['Male'=>'Male', 'Female'=>'Female', 'Other'=>'Other'], null, array('class'=>'form-control select'))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('status',__('Status'),['class'=>'form-label'])}}
                {{Form::select('status', ['Active'=>'Active', 'Inactive'=>'Inactive', 'Suspended'=>'Suspended'], 'Active', array('class'=>'form-control select'))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('registration_date',__('Registration Date'),['class'=>'form-label'])}}
                {{Form::date('registration_date', null, array('class'=>'form-control', 'required'=>'required'))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('dob',__('Date of Birth'),['class'=>'form-label'])}}
                {{Form::date('dob', null, array('class'=>'form-control'))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('bank_name',__('Bank Name'),['class'=>'form-label'])}}
                {{Form::text('bank_name', null, array('class'=>'form-control', 'placeholder' => __('Enter Bank Name')))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('account_number',__('Account Number'),['class'=>'form-label'])}}
                {{Form::text('account_number', null, array('class'=>'form-control', 'placeholder' => __('Enter Account Number')))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('bvn',__('BVN (Optional)'),['class'=>'form-label'])}}
                {{Form::text('bvn', null, array('class'=>'form-control', 'placeholder' => __('Enter BVN')))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('gps_coordinates',__('GPS Coordinates'),['class'=>'form-label'])}}
                {{Form::text('gps_coordinates', null, array('class'=>'form-control', 'placeholder' => __('e.g., 9.0765, 7.3986')))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('digital_payment_enabled',__('Digital Payment Enabled'),['class'=>'form-label'])}}
                {{Form::select('digital_payment_enabled', ['1' => 'Yes', '0' => 'No'], '0', array('class'=>'form-control select'))}}
            </div>
        </div>
        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{Form::label('documents',__('Documents (ID/Registration)'),['class'=>'form-label'])}}
                <input type="file" name="documents[]" class="form-control" multiple>
            </div>
        </div>
        @if(!$customFields->isEmpty())
                    @include('customFields.formBuilder')
        @endif
    </div>
    <div class="d-flex justify-content-end mt-4">
        <button type="button" class="btn btn-primary" data-step-next>{{ __('Next') }}</button>
    </div>
    </div>
    </div>

    <div class="gs-farmer-form-step" data-farmer-step>
    <div class="gs-farmer-step-panel">
    <h5 class="gs-farmer-step-heading">{{__('Billing Address')}}</h5>
    <p class="gs-farmer-step-text">{{ __('Enter the billing contact and official billing address used for farmer records and statements.') }}</p>
    <div class="row">
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_name',__('Name'),array('class'=>'form-label')) }}
                {{Form::text('billing_name',null,array('class'=>'form-control' , 'placeholder'=>__('Enter Name')))}}

            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_phone',__('Phone'),array('class'=>'form-label')) }}
                {{Form::text('billing_phone',null,array('class'=>'form-control' , 'placeholder' => __('Enter Phone')))}}

            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{Form::label('billing_address',__('Address'),array('class'=>'form-label')) }}
                {{Form::textarea('billing_address',null,array('class'=>'form-control','rows'=>3 , 'placeholder' => __('Enter Address')))}}
            </div>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_city',__('City'),array('class'=>'form-label')) }}
                {{Form::text('billing_city',null,array('class'=>'form-control' , 'placeholder' => __('Enter City')))}}
            </div>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_state',__('State'),array('class'=>'form-label')) }}
                {{Form::text('billing_state',null,array('class'=>'form-control' , 'placeholder'=>__('Enter State')))}}
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_country',__('Country'),array('class'=>'form-label')) }}
                {{Form::text('billing_country',null,array('class'=>'form-control' , 'placeholder' => __('Enter Country')))}}

            </div>
        </div>


        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_zip',__('Zip Code'),array('class'=>'form-label')) }}
                {{Form::text('billing_zip',null,array('class'=>'form-control' , 'placeholder' => __('Enter Zip Code')))}}

            </div>
        </div>

    </div>
    <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-light" data-step-prev>{{ __('Back') }}</button>
        <button type="button" class="btn btn-primary" data-step-next>{{ __('Next') }}</button>
    </div>
    </div>
    </div>

    <div class="gs-farmer-form-step" data-farmer-step>
    <div class="gs-farmer-step-panel">
    @if(App\Models\Utility::getValByName('shipping_display')=='on')
        <div class="col-md-12 text-end mb-3">
            <input type="button" id="billing_data" value="{{__('Shipping Same As Billing')}}" class="btn btn-primary">
        </div>
        <h5 class="gs-farmer-step-heading">{{__('Shipping Address')}}</h5>
        <p class="gs-farmer-step-text">{{ __('Complete the shipping address or copy from billing before submitting the farmer profile.') }}</p>
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_name',__('Name'),array('class'=>'form-label')) }}
                    {{Form::text('shipping_name',null,array('class'=>'form-control' , 'placeholder'=> __('Enter Name')))}}

                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_phone',__('Phone'),array('class'=>'form-label')) }}
                    {{Form::text('shipping_phone',null,array('class'=>'form-control' , 'placeholder'=>__('Enter Phone')))}}

                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('shipping_address',__('Address'),array('class'=>'form-label')) }}
                    {{Form::textarea('shipping_address',null,array('class'=>'form-control','rows'=>3 , 'placeholder'=>__('Address')))}}
                </div>
            </div>


            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_city',__('City'),array('class'=>'form-label')) }}
                    {{Form::text('shipping_city',null,array('class'=>'form-control' , 'placeholder'=>__('Enter City')))}}

                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_state',__('State'),array('class'=>'form-label')) }}
                    {{Form::text('shipping_state',null,array('class'=>'form-control' , 'placeholder'=>__('Enter State')))}}

                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_country',__('Country'),array('class'=>'form-label')) }}
                    {{Form::text('shipping_country',null,array('class'=>'form-control' , 'placeholder'=>__('Enter Country')))}}

                </div>
            </div>


            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_zip',__('Zip Code'),array('class'=>'form-label')) }}
                    {{Form::text('shipping_zip',null,array('class'=>'form-control' , 'placeholder'=>__('Enter Zip Code')))}}
                </div>
            </div>

        </div>
    @else
        <h5 class="gs-farmer-step-heading">{{__('Review & Submit')}}</h5>
        <p class="gs-farmer-step-text">{{ __('Shipping details are disabled for this workspace. Review the earlier steps and submit the farmer profile when ready.') }}</p>
    @endif

    <div class="d-flex justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-light" data-step-prev>{{ __('Back') }}</button>
        <div class="d-flex gap-2">
            <a href="{{ route('vender.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <input type="submit" value="{{__('Create')}}" class="btn btn-primary">
        </div>
    </div>
    </div>
    </div>
{{Form::close()}}
    </div>
            </div>
        </div>
    </div>
</div>
@endsection
