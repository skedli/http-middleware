<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

use LogicException;

final class ReadinessMisconfigured extends LogicException
{
    public static function requiresAtLeastOneCheck(): ReadinessMisconfigured
    {
        return new ReadinessMisconfigured(
            message: 'ReadinessHandler requires at least one health check to be registered.'
        );
    }
}
