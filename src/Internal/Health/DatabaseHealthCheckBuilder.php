<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Health;

use Doctrine\DBAL\Connection;
use Skedli\HttpMiddleware\DatabaseHealthCheck;

final class DatabaseHealthCheckBuilder
{
    private string $name;
    private string $query = 'SELECT 1';
    private bool $critical = true;

    public function __construct(private readonly Connection $connection)
    {
        $this->name = $connection->getDatabase() ?? 'database';
    }

    public function withName(string $name): DatabaseHealthCheckBuilder
    {
        $this->name = $name;
        return $this;
    }

    public function withQuery(string $query): DatabaseHealthCheckBuilder
    {
        $this->query = $query;
        return $this;
    }

    public function withCritical(bool $critical): DatabaseHealthCheckBuilder
    {
        $this->critical = $critical;
        return $this;
    }

    public function build(): DatabaseHealthCheck
    {
        return DatabaseHealthCheck::build(
            name: $this->name,
            query: $this->query,
            critical: $this->critical,
            connection: $this->connection
        );
    }
}

