<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Doctrine\DBAL\Connection;
use Skedli\HttpMiddleware\Internal\HealthCheck\DatabaseHealthCheckBuilder;
use Throwable;

final readonly class DatabaseHealthCheck implements HealthCheck
{
    private function __construct(
        private string $name,
        private string $query,
        private bool $critical,
        private Connection $connection
    ) {
    }

    public static function create(Connection $connection): DatabaseHealthCheckBuilder
    {
        return new DatabaseHealthCheckBuilder(connection: $connection);
    }

    public static function build(
        string $name,
        string $query,
        bool $critical,
        Connection $connection
    ): DatabaseHealthCheck {
        return new DatabaseHealthCheck(name: $name, query: $query, critical: $critical, connection: $connection);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        try {
            $this->connection->executeQuery($this->query);
            return HealthCheckResult::up(critical: $this->critical);
        } catch (Throwable $exception) {
            return HealthCheckResult::down(message: $exception->getMessage(), critical: $this->critical);
        }
    }
}
