<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal\HealthCheck;

use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\HealthCheckStatus;
use Skedli\HttpMiddleware\Internal\HealthCheck\CheckEntry;
use Skedli\HttpMiddleware\Internal\HealthCheck\ChecksReport;

final class ChecksReportTest extends TestCase
{
    public function testJsonSerializeWithEmptyEntriesReturnsEmptyArray(): void
    {
        /** @Given a checks report with no entries */
        $report = new ChecksReport(entries: [], hasCriticalFailure: false);

        /** @When serialized to JSON-compatible array */
        $serialized = $report->jsonSerialize();

        /** @Then the result is an empty array */
        self::assertSame([], $serialized);
    }

    public function testJsonSerializeWithTwoEntriesReturnsNameKeyedMap(): void
    {
        /** @Given a check entry for database */
        $database = new CheckEntry(
            name: 'database',
            status: HealthCheckStatus::UP,
            critical: true,
            durationInMilliseconds: 8.0
        );

        /** @And a check entry for cache */
        $cache = new CheckEntry(
            name: 'cache',
            status: HealthCheckStatus::DOWN,
            critical: false,
            durationInMilliseconds: 2.0
        );

        /** @And a checks report containing both entries */
        $report = new ChecksReport(entries: [$database, $cache], hasCriticalFailure: false);

        /** @When serialized to JSON-compatible array */
        $serialized = $report->jsonSerialize();

        /** @Then both entries are keyed by their name */
        self::assertArrayHasKey('database', $serialized);
        self::assertArrayHasKey('cache', $serialized);

        /** @And the database entry is the correct object */
        self::assertSame($database, $serialized['database']);

        /** @And the cache entry is the correct object */
        self::assertSame($cache, $serialized['cache']);
    }
}
