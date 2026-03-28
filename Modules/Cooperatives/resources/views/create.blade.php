{{ Form::open(array('route' => 'cooperatives.store', 'method'=>'post', 'class'=>'needs-validation', 'novalidate')) }}
<div class="modal-body">
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="form-group">
                {{Form::label('name',__('Name'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::text('name',null,array('class'=>'form-control','required'=>'required', 'placeholder'=>__('Enter Cooperative Name')))}}
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('location',__('Location'),['class'=>'form-label'])}}
                {{Form::text('location',null,array('class'=>'form-control', 'placeholder'=>__('Enter Location')))}}
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('leader_name',__('Leader Name'),['class'=>'form-label'])}}
                {{Form::text('leader_name',null,array('class'=>'form-control', 'placeholder'=>__('Enter Leader Name')))}}
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('leader_phone',__('Leader Phone'),['class'=>'form-label'])}}
                {{Form::text('leader_phone',null,array('class'=>'form-control', 'placeholder'=>__('Enter Leader Phone')))}}
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('formation_date',__('Formation Date'),['class'=>'form-label'])}}
                {{Form::date('formation_date',null,array('class'=>'form-control'))}}
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('average_daily_supply',__('Average Daily Supply'),['class'=>'form-label'])}}
                {{Form::number('average_daily_supply',null,array('class'=>'form-control', 'placeholder'=>__('Enter Average Daily Supply'), 'step' => '0.01'))}}
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Create')}}" class="btn btn-primary">
</div>
{{ Form::close() }}
