<?php

namespace Tigusigalpa\YandexLockbox\Token;

class StaticTokenProvider implements TokenProviderInterface
{
    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}