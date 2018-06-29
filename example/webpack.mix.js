let mix = require('laravel-mix');

mix
    .disableNotifications()
    .sass('resources/assets/css/app.scss', 'public/dist/css')
    .js('resources/assets/js/app.js', 'public/dist/js')
    // .browserSync({
    //     proxy: '0.0.0.0:8888',
    //     files: [
    //         'public/dist/css/app.css',
    //         'public/dist/js/app.js',
    //         'public/**/*.+(html|php)',
    //         'app/Modules/*.php',
    //         'app/views/*.php',
    //         'app/views/**/*.+(html|php|twig)'
    //     ]
    // });

Config.publicPath = 'public';
