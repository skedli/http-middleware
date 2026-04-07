<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Health;

use Skedli\HttpMiddleware\HealthCheck;
use Skedli\HttpMiddleware\HealthCheckResult;
use Skedli\HttpMiddleware\Internal\Duration;
use Throwable;

final readonly class HealthCheckRunner
{
    /** @param HealthCheck[] $checks */
    private function __construct(private array $checks)
    {
    }

    /** @param HealthCheck[] $checks */
    public static function from(array $checks): self
    {
        return new self(checks: $checks);
    }

    public function run(): HealthCheckReport
    {
        $checks = [];
        $hasCriticalFailure = false;

        foreach ($this->checks as $check) {
            $duration = Duration::start();

            try {
                $result = $check->check();
            } catch (Throwable $exception) {
                $result = HealthCheckResult::down(message: $exception->getMessage());
            }

            $elapsedInMilliseconds = $duration->stop()->toMilliseconds();

            $entry = [
                'status'   => $result->status->value,
                'critical' => $result->critical,
            ];

            if ($result->message !== null) {
                $entry['message'] = $result->message;
            }

            $entry['duration_in_milliseconds'] = $elapsedInMilliseconds;

            $checks[$check->name()] = $entry;

            if ($result->isCriticallyDown()) {
                $hasCriticalFailure = true;
            }
        }

        return new HealthCheckReport(checks: $checks, hasCriticalFailure: $hasCriticalFailure);
    }
}
