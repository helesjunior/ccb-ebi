<?php

use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    ApiServiceProvider::class,
    AppServiceProvider::class,
    DomainServiceProvider::class,
    HorizonServiceProvider::class,
];
