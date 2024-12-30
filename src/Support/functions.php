<?php

namespace Motekar\LaravelZip\Support;

use Motekar\LaravelZip\ZipManager;

function zip(): ZipManager
{
    return app(ZipManager::class);
}
