@extends('layouts.app')

@section('content')
<!-- begin row -->
<div class="row">
    @granted('crud.' . $table . '.index')
    <p>
        <a href="{{ to('crud.' . $table . '.index') }}" class="btn btn-success btn-upp m-r-5">
            <i class="fa fa-list"></i> @lng('crud.general.back_to_list')
        </a>
    </p>
    @endgranted
    <!-- begin col-12 -->
    <div class="col-lg-12">
        <!-- begin panel -->
        <div class="panel panel-inverse">
            <div class="panel-heading">
                @panelBtns
                <h4 class="panel-title">{{ $crud['show_title'] }}</h4>
            </div>
            <!-- begin panel-body -->
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered table-striped">
                            @foreach ($crud['viewable'] as $field => $infos)
                            <tr>
                                <th>{{ $infos['label'] }}</th>
                                <td>{!! Crud::hook('show', $field, $item, $crud) !!}</td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
            <!-- end panel-body -->
        </div>
        <!-- end panel -->
    </div>
    <!-- end col-12 -->
</div>
@endsection