<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Health;

use Skedli\HttpMiddleware\HealthCheck;
use Skedli\HttpMiddleware\HealthCheckHandler;

final class HealthCheckHandlerBuilder
{
    /** @var HealthCheck[] */
    private array $checks = [];

    public function withCheck(HealthCheck $check): HealthCheckHandlerBuilder
    {
        $this->checks[] = $check;
        return $this;
    }

    public function build(): HealthCheckHandler
    {
        return HealthCheckHandler::build(checks: $this->checks);
    }
}
