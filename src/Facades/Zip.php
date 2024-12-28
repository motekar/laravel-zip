<?php

namespace Motekar\LaravelZip\Facades;

use Illuminate\Support\Facades\Facade;
use Motekar\LaravelZip\ZipBuilder;

/**
 * @mixin \Motekar\LaravelZip\ZipBuilder
 */
class Zip extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ZipBuilder::class;
    }
}
