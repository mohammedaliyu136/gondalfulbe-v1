{{Form::open(array('route'=>'milkcollection.store','method'=>'post', 'class'=>'needs-validation', 'novalidate'))}}
<div class="modal-body">
    <h5 class="sub-title mb-3">{{__('Collection Details')}}</h5>
    
    @if(count($recentFarmers) > 0)
    <div class="row mb-3">
        <div class="col-12">
            <label class="form-label">{{__('Quick Entry (Recent Farmers)')}}</label>
            <div>
                @foreach($recentFarmers as $id => $name)
                    <button type="button" class="btn btn-sm btn-outline-primary mb-1 me-1" onclick="document.getElementById('farmer_id').value = '{{$id}}'; $('#farmer_id').trigger('change');">{{$name}}</button>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <div class="d-flex justify-content-between align-items-center">
                    {{Form::label('mcc_id',__('Milk Collection Center (MCC)'),['class'=>'form-label'])}}<x-required></x-required>
                    @can('create mcc')
                        <a href="#" data-url="{{ route('mcc.create') }}" data-ajax-popup="true" data-title="{{__('Create MCC')}}" data-bs-toggle="tooltip" title="{{__('Create MCC')}}" class="btn btn-sm btn-primary mb-1">
                            <i class="ti ti-plus"></i>
                        </a>
                    @endcan
                </div>
                {{Form::select('mcc_id', ['' => 'Select MCC'] + $mccs, null, array('class'=>'form-control select','required'=>'required'))}}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{Form::label('farmer_id',__('Farmer'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::select('farmer_id', $farmers, null, array('class'=>'form-control select', 'id'=>'farmer_id','required'=>'required'))}}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{Form::label('collection_date',__('Date & Time'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::datetimeLocal('collection_date', now()->format('Y-m-d\TH:i'), array('class'=>'form-control','required'=>'required'))}}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{Form::label('quantity',__('Quantity (Litres)'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::number('quantity',null,array('class'=>'form-control','required'=>'required','step'=>'0.01','min'=>'0.01', 'placeholder' => '0.00'))}}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{Form::label('fat_percentage',__('Fat %'),['class'=>'form-label'])}}
                {{Form::number('fat_percentage',null,array('class'=>'form-control','step'=>'0.01', 'placeholder' => '4.20'))}}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {{Form::label('temperature',__('Temperature (°C)'),['class'=>'form-label'])}}
                {{Form::number('temperature',null,array('class'=>'form-control','step'=>'0.01', 'placeholder' => '18.5'))}}
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{__('Record Milk')}}" class="btn btn-primary">
</div>
{{Form::close()}}
