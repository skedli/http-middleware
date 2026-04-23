<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

final readonly class HealthCheckResult
{
    private function __construct(
        public HealthCheckStatus $status,
        public bool $critical,
        public ?string $message = null
    ) {
    }

    public static function up(?string $message = null, bool $critical = true): HealthCheckResult
    {
        return new HealthCheckResult(status: HealthCheckStatus::UP, critical: $critical, message: $message);
    }

    public static function down(?string $message = null, bool $critical = true): HealthCheckResult
    {
        return new HealthCheckResult(status: HealthCheckStatus::DOWN, critical: $critical, message: $message);
    }

    public function isCriticallyDown(): bool
    {
        return $this->critical && $this->status === HealthCheckStatus::DOWN;
    }
}
