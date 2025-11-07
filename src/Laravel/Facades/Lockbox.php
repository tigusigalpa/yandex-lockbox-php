<?php

namespace Tigusigalpa\YandexLockbox\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Tigusigalpa\YandexLockbox\Client;

class Lockbox extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Client::class;
    }
}