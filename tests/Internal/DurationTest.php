<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal;

use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\Internal\Clock;
use Skedli\HttpMiddleware\Internal\Duration;

final class DurationTest extends TestCase
{
    public function testToMillisecondsConvertsDividingByOneMillion(): void
    {
        /** @Given a clock that returns 50ms then 150ms */
        $clock = self::clockFrom(nanoseconds: [50_000_000, 150_000_000]);

        /** @And a duration started from the clock */
        $duration = Duration::start(clock: $clock);

        /** @When the duration is stopped */
        $stopped = $duration->stop();

        /** @Then the elapsed time should be exactly 100.0 milliseconds */
        self::assertSame(100.0, $stopped->toMilliseconds());
    }

    public function testToMillisecondsRoundsToTwoDecimalPlaces(): void
    {
        /** @Given a clock that returns 0ns then a fractional nanosecond value */
        $clock = self::clockFrom(nanoseconds: [0, 1_234_567]);

        /** @And a duration started from the clock */
        $duration = Duration::start(clock: $clock);

        /** @When the duration is stopped */
        $stopped = $duration->stop();

        /** @Then the elapsed time should be rounded to 2 decimal places */
        self::assertSame(1.23, $stopped->toMilliseconds());
    }

    public function testToMillisecondsRoundsUpCorrectly(): void
    {
        /** @Given a clock that produces a value requiring rounding up */
        $clock = self::clockFrom(nanoseconds: [0, 1_235_000]);

        /** @And a duration started from the clock */
        $duration = Duration::start(clock: $clock);

        /** @When the duration is stopped */
        $stopped = $duration->stop();

        /** @Then the elapsed time should round up to 1.24 */
        self::assertSame(1.24, $stopped->toMilliseconds());
    }

    public function testElapsedIsSubtractionOfStopMinusStart(): void
    {
        /** @Given a clock that returns 10ms then 35.5ms */
        $clock = self::clockFrom(nanoseconds: [10_000_000, 35_500_000]);

        /** @And a duration started from the clock */
        $duration = Duration::start(clock: $clock);

        /** @When the duration is stopped */
        $stopped = $duration->stop();

        /** @Then the elapsed time should be 25.5ms */
        self::assertSame(25.5, $stopped->toMilliseconds());
    }

    public function testZeroElapsedWhenStartAndStopAreEqual(): void
    {
        /** @Given a clock that returns the same value twice */
        $clock = self::clockFrom(nanoseconds: [99_000_000, 99_000_000]);

        /** @And a duration started from the clock */
        $duration = Duration::start(clock: $clock);

        /** @When the duration is stopped */
        $stopped = $duration->stop();

        /** @Then the elapsed time should be zero */
        self::assertSame(0.0, $stopped->toMilliseconds());
    }

    public function testSmallSubMillisecondDuration(): void
    {
        /** @Given a clock that returns a 500 microsecond difference */
        $clock = self::clockFrom(nanoseconds: [0, 500_000]);

        /** @And a duration started from the clock */
        $duration = Duration::start(clock: $clock);

        /** @When the duration is stopped */
        $stopped = $duration->stop();

        /** @Then the elapsed time should be 0.5ms */
        self::assertSame(0.5, $stopped->toMilliseconds());
    }

    public function testStartReturnsPositiveValue(): void
    {
        /** @Given a duration started from the real clock */
        $duration = Duration::start();

        /** @Then the millisecond value should be positive */
        self::assertGreaterThan(0.0, $duration->toMilliseconds());
    }

    public function testStopReturnsDurationGreaterThanZero(): void
    {
        /** @Given a started duration */
        $duration = Duration::start();

        /** @And some time passes */
        usleep(5000);

        /** @When the duration is stopped */
        $stopped = $duration->stop();

        /** @Then the elapsed time should be greater than zero */
        self::assertGreaterThan(0.0, $stopped->toMilliseconds());
    }

    public function testLongerSleepProducesLargerDuration(): void
    {
        /** @Given a short duration */
        $short = Duration::start();
        usleep(5000);
        $shortStopped = $short->stop();

        /** @And a longer duration */
        $long = Duration::start();
        usleep(50000);
        $longStopped = $long->stop();

        /** @Then the longer duration should be greater than the short one */
        self::assertGreaterThan(
            $shortStopped->toMilliseconds(),
            $longStopped->toMilliseconds()
        );
    }

    /**
     * @param int[] $nanoseconds
     */
    private static function clockFrom(array $nanoseconds): Clock
    {
        return new class ($nanoseconds) implements Clock {
            private int $index;

            public function __construct(private readonly array $nanoseconds)
            {
                $this->index = 0;
            }

            public function nanoseconds(): int
            {
                return $this->nanoseconds[$this->index++];
            }
        };
    }
}
