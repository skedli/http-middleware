<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Mocks;

use Skedli\HttpMiddleware\HealthCheck;
use Skedli\HttpMiddleware\HealthCheckResult;
use Throwable;

final readonly class HealthCheckMock implements HealthCheck
{
    private function __construct(private string $name, private HealthCheckResult|Throwable $outcome)
    {
    }

    public static function reporting(string $name, HealthCheckResult $result): HealthCheckMock
    {
        return new HealthCheckMock(name: $name, outcome: $result);
    }

    public static function throwing(string $name, Throwable $exception): HealthCheckMock
    {
        return new HealthCheckMock(name: $name, outcome: $exception);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        if ($this->outcome instanceof Throwable) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}

