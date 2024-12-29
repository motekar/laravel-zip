<?php

namespace Motekar\LaravelZip\Support;

use Motekar\LaravelZip\ZipBuilder;

function zip(): ZipBuilder
{
    return app(ZipBuilder::class);
}
