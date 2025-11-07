<?php

namespace Tigusigalpa\YandexLockbox\Exceptions;

use Throwable;

class LockboxException extends \RuntimeException
{
    private array $context;

    public function __construct(string $message, int $code = 0, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}