<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication;

use RuntimeException;
use Throwable;

final class TokenValidationFailed extends RuntimeException
{
    private function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct(message: $reason, previous: $previous);
    }

    public static function withReason(string $reason, ?Throwable $previous = null): TokenValidationFailed
    {
        return new TokenValidationFailed(reason: $reason, previous: $previous);
    }
}
