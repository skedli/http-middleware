<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Health;

final readonly class HealthCheckReport
{
    public function __construct(
        public array $checks,
        public bool $hasCriticalFailure
    ) {
    }
}
