<?php
namespace App\Commands;

use Octo\Cli;

class Cache
{
    public function cache_clean_queries($table)
    {
        Cli::show("Flush cache starts for '$table'");
        model($table)->flushCache();
        Cli::show("Flush cache ends successfully for '$table'");
    }
}
