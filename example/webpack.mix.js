let mix = require('laravel-mix');

mix
    .disableNotifications()
    .less('resources/assets/less/app.less', 'public/assets/css')
    .copy('node_modules/sweetalert/dist/sweetalert.min.js', 'public/assets/js/sweetalert.min.js')
    .copy('node_modules/sweetalert/dist/sweetalert.css', 'public/assets/css/sweetalert.css')
    .js('resources/assets/js/app.js', 'public/assets/js');

Config.publicPath = 'public'
