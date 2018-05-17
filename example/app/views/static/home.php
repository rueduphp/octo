@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-10">
        <div class="panel panel-default">
            <div class="panel-heading">Dashboard</div>

            <div class="panel-body">
                You are logged in!
                <form>
                    @csrf_form()
                    {{ method_field('PUT') }}
                    <!-- ... -->
                </form>
            </div>
        </div>
    </div>
</div>
@endsection