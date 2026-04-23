<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

use Skedli\HttpMiddleware\HealthCheck;
use Skedli\HttpMiddleware\ReadinessHandler;

final class ReadinessHandlerBuilder
{
    private array $checks = [];
    private ?DrainMarker $drainMarker = null;

    public function withCheck(HealthCheck $check): ReadinessHandlerBuilder
    {
        $this->checks[] = $check;
        return $this;
    }

    public function withDrainMarker(string $path): ReadinessHandlerBuilder
    {
        $this->drainMarker = new DrainMarker(path: $path);
        return $this;
    }

    public function build(): ReadinessHandler
    {
        if (empty($this->checks)) {
            throw ReadinessMisconfigured::requiresAtLeastOneCheck();
        }

        return ReadinessHandler::build(checks: $this->checks, drainMarker: $this->drainMarker);
    }
}
