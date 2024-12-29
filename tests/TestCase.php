<?php

namespace Motekar\LaravelZip\Tests;

use Motekar\LaravelZip\ZipServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ZipServiceProvider::class,
        ];
    }
}
