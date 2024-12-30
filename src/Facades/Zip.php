<?php

namespace Motekar\LaravelZip\Facades;

use Illuminate\Support\Facades\Facade;
use Motekar\LaravelZip\ZipManager;

/**
 * @mixin \Motekar\LaravelZip\ZipManager
 */
class Zip extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ZipManager::class;
    }
}
