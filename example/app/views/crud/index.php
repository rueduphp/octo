@extends('layouts.app')

@section('content')
<!-- begin row -->
<div class="row">
    @granted('crud.' . $table . '.create')
    <p>
        <a href="{{ to('crud.' . $table . '.create') }}" class="btn btn-success btn-upp m-r-5">
            <i class="fa fa-plus"></i> {{ $crud['create_btn'] }}
        </a>
    </p>
    @endgranted
    <!-- begin col-12 -->
    <div class="col-lg-12">
        <!-- begin panel -->
        <div class="panel panel-inverse">
            <div class="panel-heading">
                @panelBtns
                <h4 class="panel-title">{{ $crud['list_title'] }}</h4>
            </div>
            <!-- begin panel-body -->
            <div class="panel-body">
                @if (0 === $items->count())

                <div class="col-md-12">
                    <div class="note note-info fade show">
                        <div class="note-icon"><i class="fa fa-info"></i></div>
                        <div class="note-content">
                            <h4><b>@lng('crud.general.info')</b></h4>
                            <p>@lng('crud.general.empty_list')</p>
                        </div>
                    </div>
                </div>
                @else
                <table class="table table-striped table-bordered">
                    <thead>
                    <tr>
                        @foreach ($crud['listable'] as $field => $infos)
                            <th class="text-nowrap">
                                @if (Crud::sortable($field, $crud['sortable']))
                                @php
                                $sort = $orderBy === $field ? ($order === 'ASC' ? 'DESC' : 'ASC') : 'ASC';
                                @endphp
                                <a class="sortableLink" href="@to('crud.' . $table . '.index')?order_by={{ $field
                                }}&order={{ $sort }}">
                                @endif
                                    <div class="filedList">{{ $infos['label'] }}</div>
                                @if (Crud::sortable($field, $crud['sortable']))
                                </a>
                                @if ($orderBy === $field)
                                @if ($order === 'ASC')
                                <span class="sortByLink"><a href="@to('crud.' . $table . '.index')?order_by={{ $field
                                }}&order={{ $sort }}"><i class="fa fa-arrow-circle-up pink"></i></a></span>
                                @endif
                                @if ($order === 'DESC')
                                <span class="sortByLink"><a href="@to('crud.' . $table . '.index')?order_by={{ $field
                                }}&order={{ $sort }}"><i class="fa fa-arrow-circle-down pink"></i></a></span>
                                @endif
                                @endif
                                @endif
                            </th>
                        @endforeach
                        <th width="12%" class="text-nowrap">@lng('crud.general.actions')</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($items as $item)
                    <tr>
                        @foreach ($crud['listable'] as $field => $infos)
                            <td>{!! Crud::hook('list', $field, $item, $crud) !!}</td>
                        @endforeach
                        <td>
                            @granted('crud.' . $table . '.show')
                                <a href="@to('crud.' . $table . '.show', ['id' => $item->id])" class="btn btn-xs
                            btn-primary"><i class="fa fa-eye"></i></a>
                            @endgranted

                            @granted('crud.' . $table . '.edit')
                                <a href="@to('crud.' . $table . '.edit', ['id' => $item->id])" class="btn btn-xs
                            btn-warning"><i class="fa fa-edit"></i></a>
                            @endgranted

                            @granted('crud.' . $table . '.destroy')
                                {!! Form::open([
                                    'style' => 'display: inline-block;',
                                    'method' => 'DELETE',
                                    'onsubmit' => "return confirm('".__("crud.general.delete_confirm")."');",
                                    'url' => to('crud.' . $table . '.destroy', ['id' => $item->id])
                                ]) !!}
                                {!! Form::button('<i class="fa fa-trash"></i>', ['type' => 'submit', 'class' => 'btn
                                btn-xs btn-danger']) !!}
                                {!! Form::close() !!}
                            @endgranted
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                <!-- begin Pagination -->
                <p>{{ $items->links('components.bootstrap.view.pagination') }}</p>
                <!-- end Pagination -->
                @endif
            </div>
            <!-- end panel-body -->
        </div>
        <!-- end panel -->
    </div>
    <!-- end col-12 -->
</div>
<!-- end row -->
@endsection