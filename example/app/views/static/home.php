@extends('layouts.app')

@section('content')

<!-- begin row -->
<div class="row">
    <!-- begin col-12 -->
    <div class="col-lg-12">
        <!-- begin panel -->
        <div class="panel panel-inverse">
            <div class="panel-heading">
                @panelBtns
                <h4 class="panel-title">Register</h4>
            </div>

            <div class="panel-body">
                <form class="form-horizontal" role="form" method="POST" action="{{ url('/register') }}">
                    {!! csrf_field() !!}
                    <input type="hidden" name="redirect_url" value="{{ request('redirect_url', '/') }}">

                    <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                        <label for="name" class="col-md-4 control-label">Name</label>

                        <div class="col-md-6">
                            <input id="name" type="text" class="form-control" name="name" value="{{ old('name') }}" required autofocus>

                            @if ($errors->has('name'))
                            <span class="help-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                        <label for="email" class="col-md-4 control-label">E-Mail Address</label>

                        <div class="col-md-6">
                            <input id="email" type="email" class="form-control input-datepicker" name="email" value="{{ old('email')
                            }}" required>

                            @if ($errors->has('email'))
                            <span class="help-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                        <label for="password" class="col-md-4 control-label">Password</label>
                        <div class="col-md-6">
                            <input id="password" type="password" class="form-control" name="password" required>

                            @if ($errors->has('password'))
                            <span class="help-block">
                                <strong>{{ $errors->first('password') }}</strong>
                            </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password-confirm" class="col-md-4 control-label">Confirm Password</label>

                        <div class="col-md-6">
                            <input
                                    id="password-confirm"
                                    type="password"
                                    class="form-control"
                                    name="password_confirmation"
                                    required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-6 col-md-offset-4">
                            <button type="submit" class="btn btn-primary">
                                Register
                            </button>
                            <a href="{{ route('auth.login') }}">Existing user? Log in here</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- end panel -->
    </div>
    <!-- end col-12 -->

    <div class="col-lg-6">
        <!-- begin panel -->
        <div class="panel panel-inverse">
            <div class="panel-heading">
                @panelBtns
                <h4 class="panel-title">Panel Title here</h4>
            </div>
            <div class="panel-body">
                Panel Content Here
            </div>
        </div>
        <!-- end panel -->
    </div>
    <!-- end col-6 -->

    <!-- begin col-6 -->
    <div class="col-lg-6">
        <!-- begin panel -->
        <div class="panel panel-inverse">
            <div class="panel-heading">
                @panelBtns
                <h4 class="panel-title">Panel Title here</h4>
            </div>
            <div class="panel-body">
                Panel Content Here
                <p><a href="" class="btn btn-primary">dd</a></p>
            </div>
        </div>
        <!-- end col-6 -->
    </div>
    <!-- end panel -->
</div>
@endsection