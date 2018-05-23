<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->
<head>
    <meta charset="utf-8" />
    <title>
        @lng('global.global_title') @if(isset($pageTitle)) - {{ $pageTitle }}@endif
    </title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />

    <!-- ================== BEGIN BASE CSS STYLE ================== -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Montserrat|Julius+Sans+One" rel="stylesheet" />
    <link href="{{ url('admin/plugins/jquery-ui/jquery-ui.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap/4.0.0/css/bootstrap.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/font-awesome/5.0/css/fontawesome-all.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/animate/animate.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/css/default/style.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/css/default/style-responsive.min.css') }}" rel="stylesheet" />
    <!-- ================== END BASE CSS STYLE ================== -->

    <!-- ================== BEGIN BASE JS ================== -->
    <script src="{{ url('admin/plugins/pace/pace.min.js') }}"></script>
    <!-- ================== END BASE JS ================== -->
    <link href="{{ url('admin/css/default/theme/default.css') }}?{{ time() }}" rel="stylesheet" id="theme" />
</head>
<body class="pace-top">
<!-- begin #page-loader -->
<div id="page-loader" class="fade show"><span class="spinner"></span></div>
<!-- end #page-loader -->

<!-- begin #page-container -->
<div id="page-container" class="fade">
    <!-- begin login -->
    <div class="login bg-black animated fadeInDown">
        <!-- begin brand -->
        <div class="login-header">
            <div class="brand">
                <span class="logo"><img src="{{ url('assets/img/logo.svg') }}" alt=""></span>
                <b>Octo</b> Admin
                <small>Login</small>
            </div>
            <div class="icon">
                <i class="fa fa-lock"></i>
            </div>
        </div>
        <!-- end brand -->
        <!-- begin login-content -->
        <div class="login-content">

            @if (flash()->hasError())
            <!-- begin page-error -->
            <div class="row">
                <div class="col-md-12">
                    <div class="note note-danger fade show limitedShow">
                        <div class="note-icon"><i class="fa fa-info"></i></div>
                        <div class="note-content">
                            <h4><b>Message</b></h4>
                            <p>{{ flash()->getError() }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end page-error -->
            @endif

            <form action="{{ to('log') }}" method="POST" class="margin-bottom-0">
                {!! csrf_field() !!}
                <div class="form-group m-b-20">
                    <input type="email" name="email" class="form-control form-control-lg inverse-mode"
                           placeholder="Email
                    Address"
                           required />
                </div>
                <div class="form-group m-b-20">
                    <input type="password" name="password" class="form-control form-control-lg inverse-mode"
                           placeholder="Password" required />
                </div>
                <div class="checkbox checkbox-css m-b-20">
                    <input type="checkbox" name="remember" id="remember_checkbox" />
                    <label for="remember_checkbox">
                        Remember Me
                    </label>
                </div>
                <div class="login-buttons">
                    <button type="submit" class="btn btn-success btn-block btn-lg">Sign me in</button>
                </div>
            </form>
        </div>
        <!-- end login-content -->
    </div>
    <!-- end login -->
</div>
<!-- end page container -->

<script type="text/javascript">
    var octo = window.octo || {};
    octo._token = '{{ csrf() }}';
    octo.locale = '@locale';
</script>

<!-- ================== BEGIN BASE JS ================== -->
<script src="{{ url('admin/plugins/jquery/jquery-3.2.1.min.js') }}"></script>
<script src="{{ url('admin/plugins/jquery-ui/jquery-ui.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap/4.0.0/js/bootstrap.bundle.min.js') }}"></script>
<!--[if lt IE 9]>
<script src="{{ url('admin/crossbrowserjs/html5shiv.js') }}"></script>
<script src="{{ url('admin/crossbrowserjs/respond.min.js') }}"></script>
<script src="{{ url('admin/crossbrowserjs/excanvas.min.js') }}"></script>
<![endif]-->
<script src="{{ url('admin/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>
<script src="{{ url('admin/plugins/js-cookie/js.cookie.js') }}"></script>
<script src="{{ url('admin/js/theme/default.min.js') }}"></script>
<script src="{{ url('admin/js/apps.min.js') }}"></script>
<!-- ================== END BASE JS ================== -->

<script>
    $(document).ready(function() {
        App.init();

        setTimeout(function () {
            $('.limitedShow').fadeOut();
        }, 5000);
    });
</script>
</body>
</html>

