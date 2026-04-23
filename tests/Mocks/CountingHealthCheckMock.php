<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Mocks;

use Skedli\HttpMiddleware\HealthCheck;
use Skedli\HttpMiddleware\HealthCheckResult;

final class CountingHealthCheckMock implements HealthCheck
{
    private int $count = 0;

    public function __construct(private readonly string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function invocationCount(): int
    {
        return $this->count;
    }

    public function check(): HealthCheckResult
    {
        $this->count++;
        return HealthCheckResult::up();
    }
}
