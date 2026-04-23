<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

use Skedli\HttpMiddleware\LivenessHandler;

final readonly class LivenessHandlerBuilder
{
    public function build(): LivenessHandler
    {
        return LivenessHandler::build();
    }
}
