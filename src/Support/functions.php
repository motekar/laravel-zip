<?php

namespace Motekar\LaravelZip\Support;

use Illuminate\Filesystem\Filesystem;
use Motekar\LaravelZip\ZipBuilder;

function zip(?Filesystem $fs = null): ZipBuilder
{
    return new ZipBuilder($fs);
}
