<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Skedli\HttpMiddleware\DatabaseHealthCheck;
use Skedli\HttpMiddleware\HealthCheckStatus;

final class DatabaseHealthCheckTest extends TestCase
{
    public function testReturnsUpWhenConnectionIsHealthy(): void
    {
        /** @Given a DBAL connection that responds successfully */
        $connection = $this->createStub(Connection::class);

        /** @And the connection reports the database name as "orders" */
        $connection->method('getDatabase')->willReturn('orders');

        /** @And the connection executes queries without errors */
        $connection->method('executeQuery')->willReturn($this->createStub(Result::class));

        /** @When the health check is built and executed */
        $check = DatabaseHealthCheck::create(connection: $connection)->build();

        /** @And the check is performed */
        $result = $check->check();

        /** @Then it should report UP using the database name */
        self::assertSame('orders', $check->name());

        /** @And the status should be UP */
        self::assertSame(HealthCheckStatus::UP, $result->status);

        /** @And the check should be critical */
        self::assertTrue($result->critical);

        /** @And no message should be present */
        self::assertNull($result->message);
    }

    public function testReturnsUpWithFallbackNameWhenDatabaseNameIsNull(): void
    {
        /** @Given a DBAL connection that returns null for getDatabase */
        $connection = $this->createStub(Connection::class);

        /** @And the connection has no database name */
        $connection->method('getDatabase')->willReturn(null);

        /** @And the connection executes queries without errors */
        $connection->method('executeQuery')->willReturn($this->createStub(Result::class));

        /** @When the health check is built and executed */
        $check = DatabaseHealthCheck::create(connection: $connection)->build();

        /** @And the check is performed */
        $result = $check->check();

        /** @Then it should fallback to "database" as name */
        self::assertSame('database', $check->name());

        /** @And the status should be UP */
        self::assertSame(HealthCheckStatus::UP, $result->status);
    }

    public function testReturnsDownWhenConnectionFails(): void
    {
        /** @Given a DBAL connection that reports the database name as "orders" */
        $connection = $this->createStub(Connection::class);

        /** @And the connection reports the database name */
        $connection->method('getDatabase')->willReturn('orders');

        /** @And the connection throws an exception on query */
        $connection->method('executeQuery')->willThrowException(new RuntimeException('Connection refused'));

        /** @When the health check is built and executed */
        $check = DatabaseHealthCheck::create(connection: $connection)->build();

        /** @And the check is performed */
        $result = $check->check();

        /** @Then the status should be DOWN */
        self::assertSame(HealthCheckStatus::DOWN, $result->status);

        /** @And the check should be critical */
        self::assertTrue($result->critical);

        /** @And the message should describe the failure */
        self::assertSame('Connection refused', $result->message);
    }

    public function testCustomNameAndNonCritical(): void
    {
        /** @Given a DBAL connection that responds successfully */
        $connection = $this->createStub(Connection::class);

        /** @And the connection reports the database name */
        $connection->method('getDatabase')->willReturn('orders');

        /** @And the connection executes queries without errors */
        $connection->method('executeQuery')->willReturn($this->createStub(Result::class));

        /** @When the health check is built with a custom name and non-critical flag */
        $check = DatabaseHealthCheck::create(connection: $connection)
            ->withName(name: 'read-replica')
            ->withCritical(critical: false)
            ->build();

        /** @And the check is performed */
        $result = $check->check();

        /** @Then it should use the custom name */
        self::assertSame('read-replica', $check->name());

        /** @And the status should be UP */
        self::assertSame(HealthCheckStatus::UP, $result->status);

        /** @And the check should not be critical */
        self::assertFalse($result->critical);
    }

    public function testCustomQuery(): void
    {
        /** @Given a DBAL connection that reports the database name */
        $connection = $this->createMock(Connection::class);

        /** @And the connection reports the database name */
        $connection->method('getDatabase')->willReturn('orders');

        /** @And the connection expects a specific query */
        $connection->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT 1 FROM migrations LIMIT 1')
            ->willReturn($this->createStub(Result::class));

        /** @When the health check is built with a custom query */
        $check = DatabaseHealthCheck::create(connection: $connection)
            ->withQuery(query: 'SELECT 1 FROM migrations LIMIT 1')
            ->build();

        /** @And the check is performed */
        $result = $check->check();

        /** @Then the status should be UP */
        self::assertSame(HealthCheckStatus::UP, $result->status);

        /** @And the check should be critical by default */
        self::assertTrue($result->critical);
    }
}
