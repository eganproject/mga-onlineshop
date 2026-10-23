<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HardeningServiceProvider;

return [
    AppServiceProvider::class,
    HardeningServiceProvider::class,
    AccessServiceProvider::class,
];
