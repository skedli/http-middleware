<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal\HealthCheck;

use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\HealthCheckStatus;
use Skedli\HttpMiddleware\Internal\HealthCheck\CheckEntry;
use Skedli\HttpMiddleware\Internal\HealthCheck\ChecksReport;
use Skedli\HttpMiddleware\Internal\HealthCheck\HealthPayload;

final class HealthPayloadTest extends TestCase
{
    public function testAlivePayloadContainsOnlyStatusAndService(): void
    {
        /** @Given an alive payload for service app */
        $payload = HealthPayload::alive(service: 'app');

        /** @When serialized to JSON-compatible array */
        $serialized = $payload->jsonSerialize();

        /** @Then only status and service keys are present */
        self::assertSame(['status', 'service'], array_keys($serialized));

        /** @And status is OK */
        self::assertSame('OK', $serialized['status']);

        /** @And service matches the given name */
        self::assertSame('app', $serialized['service']);
    }

    public function testDrainingPayloadContainsStatusServiceAndReason(): void
    {
        /** @Given a draining payload for service identity */
        $payload = HealthPayload::draining(service: 'identity');

        /** @When serialized to JSON-compatible array */
        $serialized = $payload->jsonSerialize();

        /** @Then status service and reason are present without checks */
        self::assertSame(['status', 'service', 'reason'], array_keys($serialized));

        /** @And status is Service Unavailable */
        self::assertSame('Service Unavailable', $serialized['status']);

        /** @And reason is draining */
        self::assertSame('draining', $serialized['reason']);
    }

    public function testReadyPayloadWithEmptyReportContainsEmptyChecks(): void
    {
        /** @Given a ready payload with an empty checks report */
        $report = new ChecksReport(entries: [], hasCriticalFailure: false);

        /** @And a ready payload wrapping that report */
        $payload = HealthPayload::ready(service: 'app', checks: $report);

        /** @When serialized to JSON-compatible array */
        $serialized = $payload->jsonSerialize();

        /** @Then status service and checks are present without reason */
        self::assertSame(['status', 'service', 'checks'], array_keys($serialized));

        /** @And status is OK */
        self::assertSame('OK', $serialized['status']);

        /** @And checks serializes to an empty map */
        self::assertSame([], $serialized['checks']->jsonSerialize());
    }

    public function testReadyPayloadWithEntriesSerializesChecksAsNameKeyedMap(): void
    {
        /** @Given a check entry for database */
        $entry = new CheckEntry(
            name: 'database',
            status: HealthCheckStatus::UP,
            critical: true,
            durationInMilliseconds: 10.0
        );

        /** @And a checks report containing that entry */
        $report = new ChecksReport(entries: [$entry], hasCriticalFailure: false);

        /** @And a ready payload wrapping that report */
        $payload = HealthPayload::ready(service: 'app', checks: $report);

        /** @When serialized to JSON string */
        $json = json_encode($payload->jsonSerialize());

        /** @Then the JSON contains the database entry keyed by name */
        $decoded = json_decode($json, true);
        self::assertArrayHasKey('database', $decoded['checks']);
        self::assertSame('UP', $decoded['checks']['database']['status']);
    }

    public function testUnreadyPayloadContainsStatusServiceAndChecksWithoutReason(): void
    {
        /** @Given an unready payload with a checks report */
        $report = new ChecksReport(entries: [], hasCriticalFailure: true);

        /** @And an unready payload wrapping that report */
        $payload = HealthPayload::unready(service: 'app', checks: $report);

        /** @When serialized to JSON-compatible array */
        $serialized = $payload->jsonSerialize();

        /** @Then status service and checks are present without reason */
        self::assertSame(['status', 'service', 'checks'], array_keys($serialized));

        /** @And status is Service Unavailable */
        self::assertSame('Service Unavailable', $serialized['status']);
    }
}
