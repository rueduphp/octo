@extends('layouts.app')

@section('content')
@granted('crud.' . $table . '.index')
<p>
    <a href="{{ to('crud.' . $table . '.index') }}" class="btn btn-success btn-upp m-r-5">
        <i class="fa fa-list"></i> @lng('crud.general.back_to_list')
    </a>
</p>
@endgranted
<!-- begin row -->
<div class="row">
    <!-- begin col-12 -->
    <div class="col-lg-12">
        <!-- begin panel -->
        <div class="panel panel-inverse">
            <div class="panel-heading">
                @panelBtns
                <h4 class="panel-title">{{ $crud['edit_title'] }}</h4>
            </div>
            <!-- begin panel-body -->
            <div class="panel-body">
                @include('crud.form');
            </div>
        </div>
    </div>
</div>
@endsection