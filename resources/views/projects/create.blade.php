{{ Form::open(['url' => 'projects', 'method' => 'post','enctype' => 'multipart/form-data', 'class'=>'needs-validation', 'novalidate']) }}
<div class="modal-body">
    {{-- start for ai module--}}
    @php
        $plan= \App\Models\Utility::getChatGPTSettings();
    @endphp
    @if($plan->chatgpt == 1)
    <div class="text-end">
        <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true" data-url="{{ route('generate',['project']) }}"
           data-bs-placement="top" data-title="{{ __('Generate content with AI') }}">
            <i class="fas fa-robot"></i> <span>{{__('Generate with AI')}}</span>
        </a>
    </div>
    @endif
    {{-- end for ai module--}}
    <div class="alert alert-info py-2">
        <div class="fw-semibold mb-1">{{ __('Gondal agent flow') }}</div>
        <div class="small mb-0">
            {{ __('Create a project to group farmer and independent reseller agents under one program. Set the partner or NGO in the Partner / NGO field so they can later see only the agents assigned to this project.') }}
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="form-group">
                {{ Form::label('project_name', __('Project / Program Name'), ['class' => 'form-label']) }}<x-required></x-required>
                {{ Form::text('project_name', null, ['class' => 'form-control','required'=>'required', 'placeholder'=>__('Enter project or program name')]) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-6 col-md-6">
            <div class="form-group">
                {{ Form::label('start_date', __('Start Date'), ['class' => 'form-label']) }}
                {{ Form::date('start_date', null, ['class' => 'form-control']) }}
            </div>
        </div>
        <div class="col-sm-6 col-md-6">
            <div class="form-group">
                {{ Form::label('end_date', __('End Date'), ['class' => 'form-label']) }}
                {{ Form::date('end_date', null, ['class' => 'form-control']) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-sm-12 col-md-12">
            {{ Form::label('project_image', __('Project Image'), ['class' => 'form-label']) }}
            <div class="form-file mb-3">
                <input type="file" class="form-control file-validate" name="project_image">
                <p id="" class="file-error text-danger"></p>
            </div>
            <div class="text-xs text-muted">
                {{ __('Optional. If you skip this, the system will use the default project image.') }}
            </div>

        </div>
        <div class="col-sm-6 col-md-6">
            <div class="form-group">
                {{ Form::label('client', __('Partner / NGO'),['class'=>'form-label']) }}
                {!! Form::select('client', $clients, null,array('class' => 'form-control select2')) !!}
                <div class="text-xs mt-1">
                    {{ __('Choose the partner login that should see agents and performance under this project.') }} <a href="{{ route('clients.index') }}"><b>{{ __('Create partner login') }}</b></a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-6">
            <div class="form-group">
                {{ Form::label('user', __('Internal Project Team'),['class'=>'form-label']) }}
                {!! Form::select('user[]', $users, null,array('class' => 'form-control select2')) !!}
                <div class="text-xs mt-1">
                    {{ __('Optional. Add your internal staff users if this project also needs task tracking in the general project module.') }} <a href="{{ route('users.index') }}"><b>{{ __('Create user') }}</b></a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-6">
            <div class="form-group">
                {{ Form::label('budget', __('Budget'), ['class' => 'form-label']) }}
                {{ Form::number('budget', null, ['class' => 'form-control', 'placeholder'=>__('Enter Project Budget')]) }}
            </div>
        </div>
        <div class="col-6 col-md-6">
            <div class="form-group">
                {{ Form::label('estimated_hrs', __('Estimated Hours'),['class' => 'form-label']) }}
                {{ Form::number('estimated_hrs', null, ['class' => 'form-control','min'=>'0','maxlength' => '8', 'placeholder'=>__('Enter Project Estimated Hours')]) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="form-group">
                {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => '4', 'cols' => '50', 'placeholder'=>__('Describe the project scope, sponsor, area, or agent cohort')]) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="form-group">
                {{ Form::label('tag', __('Tag'), ['class' => 'form-label']) }}
                {{ Form::text('tag', null, ['class' => 'form-control', 'data-toggle' => 'tags', 'placeholder'=>__('Examples: NGO, Yola, Livestock, Women Farmers')]) }}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="form-group">
                {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                <select name="status" id="status" class="form-control main-element">
                    @foreach(\App\Models\Project::$project_status as $k => $v)
                        <option value="{{$k}}">{{__($v)}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Create')}}" class="btn  btn-primary">
</div>
{{Form::close()}}
