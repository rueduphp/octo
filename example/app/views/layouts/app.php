@inject('request', 'Octo\FastRequest')
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
<body>
<!-- begin #page-loader -->
<div id="page-loader" class="fade show"><span class="spinner"></span></div>
<!-- end #page-loader -->

<!-- begin #page-container -->
<div id="page-container" class="page-container fade page-without-sidebar page-header-fixed page-with-top-menu">
    <!-- begin #header -->
    <div id="header" class="header navbar-default">
        <!-- begin navbar-header -->
        <div class="navbar-header">
            <a href="{{ to('home') }}" class="navbar-brand">
                <span class="navbar-logo">
                    <img src="{{ url('assets/img/logo.svg') }}" alt="">
                </span>
                <span class="navbar-title"><b>Octo</b> Admin</span>
            </a>
            <button type="button" class="navbar-toggle" data-click="top-menu-toggled">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
        </div>
        <!-- end navbar-header -->

        <!-- begin header navigation right -->
        <ul class="navbar-nav navbar-right">
            <li>
                <form class="navbar-form full-width">
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Enter keyword" />
                        <button type="submit" class="btn btn-search"><i class="fa fa-search"></i></button>
                    </div>
                </form>
            </li>
            <li class="dropdown navbar-user">
                <a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown">
                    <img src="{{ url('admin/img/user/user-13.jpg') }}" alt="" />
                    <span class="hidden-xs">Username</span> <b class="caret"></b>
                </a>
                <ul class="dropdown-menu">
                    <li class="arrow"></li>
                    <li>
                        <a href="#" onclick="$('#logout').submit(); return false;">
                            {{ __('global.app_logout') }}
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
        <!-- end header navigation right -->
    </div>
    <!-- end #header -->

    <!-- begin #top-menu -->
    <div id="top-menu" class="top-menu">
        <!-- begin top-menu nav -->
        <ul class="nav">
            <li class="active">
                <a href="{{ to('home') }}">
                    <i class="fa fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-control menu-control-left">
                <a href="javascript:;" data-click="prev-menu"><i class="fa fa-angle-left"></i></a>
            </li>
            <li class="menu-control menu-control-right">
                <a href="javascript:;" data-click="next-menu"><i class="fa fa-angle-right"></i></a>
            </li>
        </ul>
        <!-- end top-menu nav -->
    </div>
    <!-- end #top-menu -->

    <!-- begin #content -->
    <div id="content" class="content">
        @if(isset($breadcrumb))
        <!-- begin breadcrumb -->
        <ol class="breadcrumb pull-right">
            <li class="breadcrumb-item"><a href="javascript:;">Home</a></li>
            <li class="breadcrumb-item"><a href="javascript:;">Page Options</a></li>
            <li class="breadcrumb-item active">Page with Top Menu</li>
        </ol>
        <!-- end breadcrumb -->
        @endif

        @if(isset($pageTitle))
        <!-- begin page-header -->
        <h1 class="page-header">
            {{ $pageTitle }}
            @if(isset($subPageTitle))
            <small>{{ $subPageTitle }}</small>
            @endif
        </h1>
        <!-- end page-header -->
        @endif

        @if (Session::has('success'))
        <!-- begin page-success -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-success fade show">
                    <div class="note-icon"><i class="fa fa-info"></i></div>
                    <div class="note-content">
                        <h4><b>Message</b></h4>
                        <p>{{ Session::pull('success') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- end page-success -->
        @endif

        @if (Session::has('error'))
        <!-- begin page-error -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-danger fade show">
                    <div class="note-icon"><i class="fa fa-info"></i></div>
                    <div class="note-content">
                        <h4><b>Message</b></h4>
                        <p>{{ Session::pull('error') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- end page-success -->
        @endif

        @if ($errors->count() > 0)
        <!-- begin page-errors -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-danger">
                    <ul class="list-unstyled">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <!-- end page-errors -->
        @endif

        <!-- begin page-content -->
        @yield('content')
        <!-- end page-content -->
    </div>
    <!-- end #content -->

    <!-- begin scroll to top btn -->
    <a href="javascript:;" class="btn btn-icon btn-circle btn-success btn-scroll-to-top fade" data-click="scroll-top"><i class="fa fa-angle-up"></i></a>
    <!-- end scroll to top btn -->
</div>
<!-- end page container -->

<form style="display:none;" id="logout" action="{{ route('auth.logout') }}" method="post">
    {!! csrf_field() !!}
    <button type="submit">{{ __('global.logout') }}</button>
</form>

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