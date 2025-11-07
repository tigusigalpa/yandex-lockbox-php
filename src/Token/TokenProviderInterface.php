<?php

namespace Tigusigalpa\YandexLockbox\Token;

interface TokenProviderInterface
{
    /**
     * Returns the bearer token used for authentication with Yandex Cloud APIs.
     */
    public function getToken(): string;
}