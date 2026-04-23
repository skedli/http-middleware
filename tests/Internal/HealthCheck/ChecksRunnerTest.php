<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal\HealthCheck;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Skedli\HttpMiddleware\HealthCheckResult;
use Skedli\HttpMiddleware\HealthCheckStatus;
use Skedli\HttpMiddleware\Internal\HealthCheck\ChecksRunner;
use Test\Skedli\HttpMiddleware\Mocks\HealthCheckMock;

final class ChecksRunnerTest extends TestCase
{
    public function testRunWithCheckUpProducesEntryUpWithoutMessage(): void
    {
        /** @Given a runner with a check reporting UP */
        $runner = ChecksRunner::from(checks: [
            HealthCheckMock::reporting(name: 'database', result: HealthCheckResult::up())
        ]);

        /** @When run */
        $report = $runner->run();

        /** @Then the report contains one entry with UP status */
        self::assertCount(1, $report->entries);
        self::assertSame(HealthCheckStatus::UP, $report->entries[0]->status);

        /** @And the entry has no message */
        self::assertNull($report->entries[0]->message);

        /** @And no critical failure is flagged */
        self::assertFalse($report->hasCriticalFailure);
    }

    public function testRunWithCriticalCheckDownSetsHasCriticalFailure(): void
    {
        /** @Given a runner with a critical check reporting DOWN with a message */
        $runner = ChecksRunner::from(checks: [
            HealthCheckMock::reporting(
                name: 'database',
                result: HealthCheckResult::down(message: 'Connection refused')
            )
        ]);

        /** @When run */
        $report = $runner->run();

        /** @Then the entry status is DOWN */
        self::assertSame(HealthCheckStatus::DOWN, $report->entries[0]->status);

        /** @And the entry carries the failure message */
        self::assertSame('Connection refused', $report->entries[0]->message);

        /** @And hasCriticalFailure is true */
        self::assertTrue($report->hasCriticalFailure);
    }

    public function testRunWithCheckThrowingExceptionProducesCriticalEntryWithExceptionMessage(): void
    {
        /** @Given a runner with a check that throws a runtime exception */
        $runner = ChecksRunner::from(checks: [
            HealthCheckMock::throwing(
                name: 'database',
                exception: new RuntimeException('Connection timed out')
            )
        ]);

        /** @When run */
        $report = $runner->run();

        /** @Then the entry status is DOWN */
        self::assertSame(HealthCheckStatus::DOWN, $report->entries[0]->status);

        /** @And the entry message contains the exception message */
        self::assertSame('Connection timed out', $report->entries[0]->message);

        /** @And hasCriticalFailure is true because the thrown check defaults to critical */
        self::assertTrue($report->hasCriticalFailure);
    }

    public function testRunWithNonCriticalCheckDownDoesNotSetHasCriticalFailure(): void
    {
        /** @Given a runner with a non-critical check reporting DOWN */
        $runner = ChecksRunner::from(checks: [
            HealthCheckMock::reporting(
                name: 'cache',
                result: HealthCheckResult::down(message: 'Cache miss', critical: false)
            )
        ]);

        /** @When run */
        $report = $runner->run();

        /** @Then the entry status is DOWN */
        self::assertSame(HealthCheckStatus::DOWN, $report->entries[0]->status);

        /** @And hasCriticalFailure remains false */
        self::assertFalse($report->hasCriticalFailure);
    }
}
