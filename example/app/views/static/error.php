<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->
<head>
    <meta charset="utf-8" />
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf() }}">
    <title>
        {{ __('global.global_title') }}
        @if(isset($pageTitle))
        - {{ $pageTitle }}
        @endif
    </title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
    <meta content="" name="description" />
    <meta content="" name="author" />

    <link rel="shortcut icon" type="image/png" href="{{ url('assets/img/logo.ico') }}" />

    <!-- ================== BEGIN BASE CSS STYLE ================== -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Montserrat|Julius+Sans+One" rel="stylesheet" />
    <link href="{{ url('admin/plugins/jquery-ui/jquery-ui.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap/4.0.0/css/bootstrap.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/font-awesome/5.0/css/fontawesome-all.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/animate/animate.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/css/default/style.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/css/default/style-responsive.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/css/default/theme/default.css') }}?{{ time() }}" rel="stylesheet" id="theme" />
    <!-- ================== END BASE CSS STYLE ================== -->

    <!-- ================== BEGIN BASE JS ================== -->
    <script src="{{ url('admin/plugins/pace/pace.min.js') }}"></script>
    <!-- ================== END BASE JS ================== -->
</head>
<body class="pace-top">
<!-- begin #page-loader -->
<div id="page-loader" class="fade show"><span class="spinner"></span></div>
<div id="page-container" class="fade">
    <!-- begin error -->
    <div class="error">
        <div class="error-code m-b-10">Error</div>
        <div class="error-content">
            <div class="error-message">Something went wrong...</div>
            <div class="error-desc m-b-30">
                Our team will work about it. <br />
                Please, be back soon.
            </div>
            <div>
                <a href="{{ to('home') }}" class="btn btn-success p-l-20 p-r-20">Go Home</a>
            </div>
        </div>
    </div>
    <!-- end error -->
</div>
<!-- end #content -->

<!-- begin scroll to top btn -->
<a href="javascript:;" class="btn btn-icon btn-circle btn-success btn-scroll-to-top fade" data-click="scroll-top"><i class="fa fa-angle-up"></i></a>
<!-- end scroll to top btn -->
<!-- end page container -->

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
    window._token = '{{ csrf() }}';

    $(document).ready(function() {
        App.init();
    });
</script>
</body>
</html>