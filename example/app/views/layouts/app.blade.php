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
        @lng('global.global_title') @if(isset($pageTitle)) - {{ $pageTitle }}@endif
    </title>

    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />

    <link rel="shortcut icon" type="image/png" href="@asset('assets/img/logo.ico')" />

    <!-- ================== BEGIN BASE CSS STYLE ================== -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Montserrat|Julius+Sans+One" rel="stylesheet" />
    <link href="{{ url('admin/plugins/jquery-ui/jquery-ui.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap/4.0.0/css/bootstrap.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/font-awesome/5.0/css/fontawesome-all.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/animate/animate.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/css/default/style.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/css/default/style-responsive.min.css') }}" rel="stylesheet" />
    <!-- ================== END BASE CSS STYLE ================== -->

    <!-- ================== BEGIN PAGE LEVEL STYLE ================== -->
    <link href="{{ url('admin/plugins/bootstrap-datepicker/css/bootstrap-datepicker.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.css') }}"
          rel="stylesheet" />
    <link href="{{ url('admin/plugins/ionRangeSlider/css/ion.rangeSlider.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/ionRangeSlider/css/ion.rangeSlider.skinNice.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/password-indicator/css/password-indicator.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-combobox/css/bootstrap-combobox.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-select/bootstrap-select.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/jquery-tag-it/css/jquery.tagit.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/select2/dist/css/select2.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-eonasdan-datetimepicker/build/css/bootstrap-datetimepicker.min.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/bootstrap-colorpalette/css/bootstrap-colorpalette.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/jquery-simplecolorpicker/jquery.simplecolorpicker.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/jquery-simplecolorpicker/jquery.simplecolorpicker-fontawesome.css') }}" rel="stylesheet" />
    <link href="{{ url('admin/plugins/jquery-simplecolorpicker/jquery.simplecolorpicker-glyphicons.css') }}" rel="stylesheet" />
    @yield('css')
    <!-- ================== END PAGE LEVEL STYLE ================== -->

    <!-- ================== BEGIN BASE JS ================== -->
    <script src="{{ url('admin/plugins/pace/pace.min.js') }}"></script>
    <!-- ================== END BASE JS ================== -->
    <link href="@asset('admin/css/default/theme/default.css')" rel="stylesheet" id="theme" />
    <link href="{{ mix('/dist/css/app.css') }}" rel="stylesheet" id="app">
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
            <a href="@to('home')" class="navbar-brand">
                <span class="navbar-logo">
                    <img src="@asset('assets/img/logo.svg')" alt="">
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
            @if(isset($searchable))
            <li>
                <form class="navbar-form full-width">
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Enter keyword" />
                        <button type="submit" class="btn btn-search"><i class="fa fa-search"></i></button>
                    </div>
                </form>
            </li>
            @endif

            @isLogged
            <li class="dropdown navbar-user">
                <a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown">
                    <img src="@user('photo')" alt="" />
                    <span class="hidden-xs">@user('username')</span> <b class="caret"></b>
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
            @isNotLogged
            <li>
                <a href="{{ to('login') }}">@lng('global.app_connect')</a>
            </li>
            @endIsLogged
        </ul>
        <!-- end header navigation right -->
    </div>
    <!-- end #header -->

    <!-- begin #top-menu -->
    <div id="top-menu" class="top-menu">
        <!-- begin top-menu nav -->
        <ul class="nav">
            <li class="active">
                <a href="@to('home')">
                    <i class="fa fa-th-large"></i>
                    <span>@lng('global.app_dashboard')</span>
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

        @if (flash()->hasSuccess())
        <!-- begin page-success -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-success fade show limitedShow">
                    <div class="note-icon"><i class="fa fa-trophy"></i></div>
                    <div class="note-content">
                        <h4><b>Message</b></h4>
                        <p>{{ flash()->getSuccess() }}</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- end page-success -->
        @endif

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

        @if (flash()->hasWarning())
        <!-- begin page-warning -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-warning fade show limitedShow">
                    <div class="note-icon"><i class="fa fa-exclamation-triangle"></i></div>
                    <div class="note-content">
                        <h4><b>Message</b></h4>
                        <p>{{ flash()->getWarning() }}</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- end page-warning -->
        @endif

        @if (flash()->hasInfo())
        <!-- begin page-info -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-info fade show limitedShow">
                    <div class="note-icon"><i class="fa fa-info"></i></div>
                    <div class="note-content">
                        <h4><b>Message</b></h4>
                        <p>{{ flash()->getInfo() }}</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- end page-info -->
        @endif

        @if ($errors->count() > 0 && !isset($no_flash))
        <!-- begin page-errors -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-danger fade show limitedShow">
                    <div class="note-icon"><i class="fa fa-info"></i></div>
                    <div class="note-content">
                        <h4><b>Message</b></h4>
                        <ul class="list-unstyled">
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
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

<form style="display:none;" id="logout" action="{{ to('logout') }}" method="post">
    {!! csrf_field() !!}
    <button type="submit">{{ __('global.logout') }}</button>
</form>

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
<!-- ================== END BASE JS ================== -->

<!-- ================== BEGIN PAGE LEVEL JS ================== -->
<script src="{{ url('admin/plugins/highlight/highlight.common.js') }}"></script>
<script src="{{ url('admin/js/demo/render.highlight.js') }}"></script>

<script src="{{ url('admin/plugins/bootstrap-datepicker/js/bootstrap-datepicker.js') }}"></script>
<script src="{{ url('admin/plugins/ionRangeSlider/js/ion-rangeSlider/ion.rangeSlider.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js') }}"></script>
<script src="{{ url('admin/plugins/masked-input/masked-input.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}"></script>
<script src="{{ url('admin/plugins/password-indicator/js/password-indicator.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-combobox/js/bootstrap-combobox.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-select/bootstrap-select.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-tagsinput/bootstrap-tagsinput.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-tagsinput/bootstrap-tagsinput-typeahead.js') }}"></script>
<script src="{{ url('admin/plugins/jquery-tag-it/js/tag-it.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-daterangepicker/moment.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
<script src="{{ url('admin/plugins/select2/dist/js/select2.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-eonasdan-datetimepicker/build/js/bootstrap-datetimepicker.min.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-show-password/bootstrap-show-password.js') }}"></script>
<script src="{{ url('admin/plugins/bootstrap-colorpalette/js/bootstrap-colorpalette.js') }}"></script>
<script src="{{ url('admin/plugins/jquery-simplecolorpicker/jquery.simplecolorpicker.js') }}"></script>
<script src="{{ url('admin/plugins/clipboard/clipboard.min.js') }}"></script>
<script src="{{ url('admin/js/demo/form-plugins.demo.min.js') }}"></script>
@yield('js')
<!-- ================== END PAGE LEVEL JS ================== -->
<script src="@asset('admin/js/apps.min.js')"></script>
<script src="{{ mix('/dist/js/app.js') }}"></script>

<script type="text/javascript">
    $(document).ready(function() {
        App.init();

        $(".input-datepicker").datepicker({autoclose:true});

        setTimeout(function () {
            $('.limitedShow').fadeOut();
        }, 5000);
    });
</script>
</body>
</html>