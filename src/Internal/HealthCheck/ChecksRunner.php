<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

use Skedli\HttpMiddleware\HealthCheckResult;
use Skedli\HttpMiddleware\Internal\Duration;
use Throwable;

final readonly class ChecksRunner
{
    private function __construct(private array $checks)
    {
    }

    public static function from(array $checks): self
    {
        return new self(checks: $checks);
    }

    public function run(): ChecksReport
    {
        $entries = [];
        $hasCriticalFailure = false;

        foreach ($this->checks as $check) {
            $duration = Duration::start();

            try {
                $result = $check->check();
            } catch (Throwable $exception) {
                $result = HealthCheckResult::down(message: $exception->getMessage());
            }

            $entries[] = new CheckEntry(
                name: $check->name(),
                status: $result->status,
                critical: $result->critical,
                durationInMilliseconds: $duration->stop()->toMilliseconds(),
                message: $result->message
            );

            if ($result->isCriticallyDown()) {
                $hasCriticalFailure = true;
            }
        }

        return new ChecksReport(entries: $entries, hasCriticalFailure: $hasCriticalFailure);
    }
}
