<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal\HealthCheck;

use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\HealthCheckStatus;
use Skedli\HttpMiddleware\Internal\HealthCheck\CheckEntry;

final class CheckEntryTest extends TestCase
{
    public function testJsonSerializeWithMessageIncludesAllFourKeysInOrder(): void
    {
        /** @Given a check entry with status UP and a message */
        $entry = new CheckEntry(
            name: 'database',
            status: HealthCheckStatus::UP,
            critical: true,
            durationInMilliseconds: 12.5,
            message: 'All good'
        );

        /** @When serialized to JSON-compatible array */
        $serialized = $entry->jsonSerialize();

        /** @Then all four keys are present in the correct order */
        self::assertSame(['status', 'critical', 'message', 'duration_in_milliseconds'], array_keys($serialized));

        /** @And the message value is preserved */
        self::assertSame('All good', $serialized['message']);
    }

    public function testJsonSerializeWithoutMessageOmitsMessageKey(): void
    {
        /** @Given a check entry without a message */
        $entry = new CheckEntry(
            name: 'database',
            status: HealthCheckStatus::UP,
            critical: true,
            durationInMilliseconds: 12.5
        );

        /** @When serialized to JSON-compatible array */
        $serialized = $entry->jsonSerialize();

        /** @Then only three keys are present and message is absent */
        self::assertSame(['status', 'critical', 'duration_in_milliseconds'], array_keys($serialized));
    }

    public function testJsonSerializeWithStatusUpReturnsUpString(): void
    {
        /** @Given a check entry with UP status */
        $entry = new CheckEntry(
            name: 'cache',
            status: HealthCheckStatus::UP,
            critical: false,
            durationInMilliseconds: 5.0
        );

        /** @When serialized to JSON-compatible array */
        $serialized = $entry->jsonSerialize();

        /** @Then the status field contains the string UP */
        self::assertSame('UP', $serialized['status']);
    }

    public function testJsonSerializeWithStatusDownReturnsDownString(): void
    {
        /** @Given a check entry with DOWN status */
        $entry = new CheckEntry(
            name: 'database',
            status: HealthCheckStatus::DOWN,
            critical: true,
            durationInMilliseconds: 3.0
        );

        /** @When serialized to JSON-compatible array */
        $serialized = $entry->jsonSerialize();

        /** @Then the status field contains the string DOWN */
        self::assertSame('DOWN', $serialized['status']);
    }
}
