<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Verifies database connectivity by executing a lightweight query.
 *
 * Accepts a Doctrine DBAL connection and checks whether the database is
 * reachable and responsive. Returns a DOWN result if the query fails for
 * any reason.
 */
final readonly class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(
        private Connection $connection,
        private bool $critical = true
    ) {
    }

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            return HealthCheckResult::up(critical: $this->critical);
        } catch (Throwable $exception) {
            return HealthCheckResult::down(critical: $this->critical, message: $exception->getMessage());
        }
    }
}
