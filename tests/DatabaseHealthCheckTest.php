<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Skedli\HttpMiddleware\DatabaseHealthCheck;
use Skedli\HttpMiddleware\HealthCheckStatus;

final class DatabaseHealthCheckTest extends TestCase
{
    public function testSuccessfulCheck(): void
    {
        /** @Given a DBAL connection mock that executes SELECT 1 successfully */
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('executeQuery')->with('SELECT 1');

        $check = new DatabaseHealthCheck(connection: $connection);

        /** @When the health check is executed */
        $result = $check->check();

        /** @Then the result should be UP, critical, and named "database" */
        self::assertSame('database', $check->name());
        self::assertSame(HealthCheckStatus::UP, $result->status);
        self::assertTrue($result->critical);
        self::assertNull($result->message);
    }

    public function testFailedCheck(): void
    {
        /** @Given a DBAL connection mock that throws an exception on query */
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('executeQuery')->with('SELECT 1')->willThrowException(new RuntimeException('Connection refused'));

        $check = new DatabaseHealthCheck(connection: $connection);

        /** @When the health check is executed */
        $result = $check->check();

        /** @Then the result should be DOWN with the exception message */
        self::assertSame(HealthCheckStatus::DOWN, $result->status);
        self::assertTrue($result->critical);
        self::assertSame('Connection refused', $result->message);
    }

    public function testNonCriticalConfiguration(): void
    {
        /** @Given a non-critical DatabaseHealthCheck with a succeeding DBAL connection mock */
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('executeQuery')->with('SELECT 1');

        $check = new DatabaseHealthCheck(connection: $connection, critical: false);

        /** @When the health check is executed */
        $result = $check->check();

        /** @Then the result should be UP and non-critical */
        self::assertSame(HealthCheckStatus::UP, $result->status);
        self::assertFalse($result->critical);
    }

    public function testCustomMessageOnFailure(): void
    {
        /** @Given a DBAL connection mock that throws an exception with a specific message */
        $expectedMessage = 'An exception occurred in the driver: Connection refused';
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('executeQuery')->with('SELECT 1')->willThrowException(new RuntimeException($expectedMessage));

        $check = new DatabaseHealthCheck(connection: $connection);

        /** @When the health check is executed */
        $result = $check->check();

        /** @Then the exception message should be propagated to the result */
        self::assertSame(HealthCheckStatus::DOWN, $result->status);
        self::assertSame($expectedMessage, $result->message);
    }
}
