<?php

namespace Tigusigalpa\YandexLockbox\Exceptions;

/**
 * Thrown when rate limit is exceeded (429 Too Many Requests).
 */
class RateLimitException extends LockboxException
{
}
