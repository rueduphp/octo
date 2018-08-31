<?php

use App\Services\Directives;
use App\Services\ViewCache;

return [

    /*
    |---------------------------------------------------------------------
    | @istrue / @isfalse
    |---------------------------------------------------------------------
    */

    'istrue' => function ($expression) {
        if (str_contains($expression, ',')) {
            $expression = Directives::parseMultipleArgs($expression);

            return  "<?php if (isset({$expression->get(0)}) && (bool) {$expression->get(0)} === true) : ?>".
                "<?php echo {$expression->get(1)}; ?>".
                '<?php endif; ?>';
        }

        return "<?php if (isset({$expression}) && (bool) {$expression} === true) : ?>";
    },

    'endistrue' => function () {
        return '<?php endif; ?>';
    },

    'isfalse' => function ($expression) {
        if (str_contains($expression, ',')) {
            $expression = Directives::parseMultipleArgs($expression);

            return  "<?php if (isset({$expression->get(0)}) && (bool) {$expression->get(0)} === false) : ?>".
                "<?php echo {$expression->get(1)}; ?>".
                '<?php endif; ?>';
        }

        return "<?php if (isset({$expression}) && (bool) {$expression} === false) : ?>";
    },

    'endisfalse' => function () {
        return '<?php endif; ?>';
    },

    /*
    |---------------------------------------------------------------------
    | @isnull / @isnotnull
    |---------------------------------------------------------------------
    */

    'isnull' => function ($expression) {
        return "<?php if (is_null({$expression})) : ?>";
    },

    'endisnull' => function () {
        return '<?php endif; ?>';
    },

    'isnotnull' => function ($expression) {
        return "<?php if (! is_null({$expression})) : ?>";
    },

    'endisnotnull' => function () {
        return '<?php endif; ?>';
    },

    /*
    |---------------------------------------------------------------------
    | @mix
    |---------------------------------------------------------------------
    */

    'mix' => function ($expression) {
        if (ends_with($expression, ".css'")) {
            return '<link rel="stylesheet" href="<?php echo mix('.$expression.') ?>">';
        }

        if (ends_with($expression, ".js'")) {
            return '<script src="<?php echo mix('.$expression.') ?>"></script>';
        }

        return "<?php echo mix({$expression}); ?>";
    },

    /*
    |---------------------------------------------------------------------
    | @style
    |---------------------------------------------------------------------
    */

    'style' => function ($expression) {
        if (!empty($expression)) {
            return '<link rel="stylesheet" href="'.Directives::stripQuotes($expression).'">';
        }

        return '<style>';
    },

    'endstyle' => function () {
        return '</style>';
    },

    /*
    |---------------------------------------------------------------------
    | @script
    |---------------------------------------------------------------------
    */

    'script' => function ($expression) {
        if (! empty($expression)) {
            return '<script src="'.Directives::stripQuotes($expression).'"></script>';
        }

        return '<script>';
    },

    'endscript' => function () {
        return '</script>';
    },

    /*
    |---------------------------------------------------------------------
    | @js
    |---------------------------------------------------------------------
    */

    'js' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        $variable = Directives::stripQuotes($expression->get(0));

        return  "<script>\n".
            "window.{$variable} = <?php echo is_array({$expression->get(1)}) ? json_encode({$expression->get(1)}) : '\''.{$expression->get(1)}.'\''; ?>;\n".
            '</script>';
    },

    /*
    |---------------------------------------------------------------------
    | @inline
    |---------------------------------------------------------------------
    */

    'inline' => function ($expression) {
        $include = "//  {$expression}\n".
            "<?php include public_path({$expression}) ?>\n";

        if (ends_with($expression, ".html'")) {
            return $include;
        }

        if (ends_with($expression, ".css'")) {
            return "<style>\n".$include.'</style>';
        }

        if (ends_with($expression, ".js'")) {
            return "<script>\n".$include.'</script>';
        }
    },

    /*
    |---------------------------------------------------------------------
    | @routeis
    |---------------------------------------------------------------------
    */

    'routeis' => function ($expression) {
        return "<?php if (fnmatch({$expression}, Octo\Facades\Route::currentName())) : ?>";
    },

    'endrouteis' => function () {
        return '<?php endif; ?>';
    },

    'routeisnot' => function ($expression) {
        return "<?php if (! fnmatch({$expression}, Octo\Facades\Route::currentName())) : ?>";
    },

    'endrouteisnot' => function ($expression) {
        return '<?php endif; ?>';
    },

    /*
    |---------------------------------------------------------------------
    | @instanceof
    |---------------------------------------------------------------------
    */

    'instanceof' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        return  "<?php if ({$expression->get(0)} instanceof {$expression->get(1)}) : ?>";
    },

    'endinstanceof' => function () {
        return '<?php endif; ?>';
    },

    /*
    |---------------------------------------------------------------------
    | @typeof
    |---------------------------------------------------------------------
    */

    'typeof' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        return  "<?php if (gettype({$expression->get(0)}) === {$expression->get(1)}) : ?>";
    },

    'endtypeof' => function () {
        return '<?php endif; ?>';
    },

    /*
    |---------------------------------------------------------------------
    | @dump, @dd
    |---------------------------------------------------------------------
    */

    'dump' => function ($expression) {
        return "<?php dump({$expression}); ?>";
    },

    'dd' => function ($expression) {
        return "<?php dd({$expression}); ?>";
    },

    /*
    |---------------------------------------------------------------------
    | @pushonce
    |---------------------------------------------------------------------
    */

    'pushonce' => function ($expression) {
        list($pushName, $pushSub) = explode(':', trim(substr($expression, 1, -1)));

        $key = '__pushonce_'.str_replace('-', '_', $pushName).'_'.str_replace('-', '_', $pushSub);

        return "<?php if(! isset(\$__env->{$key})): \$__env->{$key} = 1; \$__env->startPush('{$pushName}'); ?>";
    },

    'endpushonce' => function () {
        return '<?php $__env->stopPush(); endif; ?>';
    },

    /*
    |---------------------------------------------------------------------
    | @repeat
    |---------------------------------------------------------------------
    */

    'repeat' => function ($expression) {
        return "<?php for (\$iteration = 0 ; \$iteration < (int) {$expression}; \$iteration++): ?>";
    },

    'endrepeat' => function () {
        return '<?php endfor; ?>';
    },

    /*
     |---------------------------------------------------------------------
     | @data
     |---------------------------------------------------------------------
     */

    'data' => function ($expression) {
        $output = 'Octo\coll((array) '.$expression.')
            ->map(function($value, $key) {
                return "data-{$key}=\"{$value}\"";
            })
            ->implode(" ")';

        return "<?php echo $output; ?>";
    },

    /*
    |---------------------------------------------------------------------
    | @fa, @fas, @far, @fal, @fab
    |---------------------------------------------------------------------
    */

    'fa' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        return '<i class="fa fa-'.Directives::stripQuotes($expression->get(0)).' '.Directives::stripQuotes($expression->get(1)).'"></i>';
    },

    'fas' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        return '<i class="fas fa-'.Directives::stripQuotes($expression->get(0)).' '.Directives::stripQuotes($expression->get(1)).'"></i>';
    },

    'far' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        return '<i class="far fa-'.Directives::stripQuotes($expression->get(0)).' '.Directives::stripQuotes($expression->get(1)).'"></i>';
    },

    'fal' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        return '<i class="fal fa-'.Directives::stripQuotes($expression->get(0)).' '.Directives::stripQuotes($expression->get(1)).'"></i>';
    },

    'fab' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);

        return '<i class="fab fa-'.Directives::stripQuotes($expression->get(0)).' '.DirectivesRepository::stripQuotes($expression->get(1)).'"></i>';
    },

    /*
    |---------------------------------------------------------------------
    | Others
    |---------------------------------------------------------------------
    */

    'locale' => function () {
        return Octo\echoInDirective(locale());
    },

    'isLogged' => function () {
        return '<?php if (auth()->logged()): ?>';
    },

    'isNotLogged' => function () {
        return '<?php else: ?>';
    },

    'endIsLogged' => function () {
        return '<?php endif; ?>';
    },

    'form_csrf' => function () {
        return Octo\echoInDirective(csrf_field());
    },

    '_csrf' => function () {
        return Octo\echoInDirective(csrf());
    },

    'user' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);
        $key        = $expression->get(0);
        $default    = $expression->get(1) ?? 'null';

        return "<?php echo user({$key}, {$default}); ?>";
    },

    'set' => function ($expression) {
        list($variable, $value) = explode('\',', $expression, 2);
        $variable = trim(str_replace('\'', '', $variable));

        if (!\Octo\startsWith($variable, '$')) {
            $variable = '$' . $variable;
        }

        $value = trim($value);

        return "<?php {$variable} = {$value}; ?>";
    },

    'asset' => function ($expression) {
        return "<?php echo url({$expression}) . '?id=' . md5(filemtime(Octo\public_path({$expression}))); ?>";
    },

    'to' => function ($expression) {
        return "<?php echo to({$expression}); ?>";
    },

    'url' => function ($expression) {
        return "<?php echo url({$expression}); ?>";
    },

    'cache' => function ($expression) {
        $class = ViewCache::class;

        return "<?php if (false === l('{$class}')->has({$expression})): ?>";
    },

    'endcache' => function () {
        $class = ViewCache::class;

        return "<?php endif; echo l('{$class}')->put(); ?>";
    },

    'old' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);
        $key        = $expression->get(0);
        $default    = $expression->get(1) ?? 'null';

        return "<?php echo old({$key}, {$default}); ?>";
    },

    'cut' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);
        $start      = $expression->get(0);
        $end        = $expression->get(1);
        $concern    = $expression->get(2);
        $default    = $expression->get(3) ?? 'null';

        return "<?php echo findIn({$start}, {$end}, {$concern}, {$default}); ?>";
    },

    'explode' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);
        $delimiter  = $expression->get(0);
        $string     = $expression->get(1);

        return "<?php echo '<pre>' . print_r(explode({$delimiter}, {$string})) . '</pre>'; ?>";
    },

    'granted' => function ($expression) {
        $args       = Directives::parseMultipleArgs($expression);
        $role       = $args->get(0);
        $context    = $args->get(1) ?? '';

        return "<?php if(trust({$context})->can({$role})): ?>";
    },

    'notgranted' => function () {
        return '<?php else: ?>';
    },

    'endgranted' => function () {
        return '<?php endif; ?>';
    },

    'lng' => function ($expression) {
        return "<?php echo __({$expression}); ?>";
    },

    'method' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);
        $method     = $expression->get(0);
        $method     = trim($method, "'");
        $html       = "<input name=\"_method\" type=\"hidden\" value=\"{$method}\">";

        return Octo\echoInDirective($html);
    },

    'partial' => function ($expression) {
        $expression = stripParentheses($expression);

        return "<?php if (\$__env->exists({$expression})) echo \$__env->make({$expression}, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";
    },

    'redirect_url' => function ($expression) {
        $expression = Directives::parseMultipleArgs($expression);
        $method  = $expression->get(0);

        if (\Octo\startsWith($method, '\'')) {
            $method = substr($method, 1, -1);
        }

        $html = "<input name=\"redirect_url\" type=\"hidden\" value=\"{$method}\">";

        return Octo\echoInDirective($html);
    },

    'panelBtns' => function ($expression) {
        $class = empty($expression) ? 'hide' : '';

        $btns = '<div class="panel-heading-btn"><a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-default" data-click="panel-expand"><i class="fa fa-expand"></i></a><a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning" data-click="panel-collapse"><i class="fa fa-minus"></i></a><a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger ' . $class . '" data-click="panel-remove"><i class="fa fa-times"></i></a></div>';

        return Octo\echoInDirective($btns);
    },

    'comment' => function ($expression) {
        $args = Directives::cleanArgs($expression);
        $comment  = $args->get(0);

        return "<?php echo '<!-- ' . {$comment} . ' -->'; ?>";
    },

];
