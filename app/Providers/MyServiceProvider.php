<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MyServiceProvider extends ServiceProvider
{
    public function __construct($app)
    {
        echo "NEW - " . __METHOD__ . PHP_EOL;
        parent::__construct($app);
    }

    public function register(): void
    {
        echo "REGISTER - " . __METHOD__ . PHP_EOL;
    }

    public function boot(): void
    {
        echo "BOOT - " . __METHOD__ . PHP_EOL;
    }
}
