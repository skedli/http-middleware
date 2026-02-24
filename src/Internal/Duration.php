<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal;

final readonly class Duration
{
    private const int NANOSECONDS_PER_MILLISECOND = 1_000_000;

    private function __construct(private int $nanoseconds, private Clock $clock)
    {
    }

    public static function start(Clock $clock = new SystemClock()): self
    {
        return new Duration(nanoseconds: $clock->nanoseconds(), clock: $clock);
    }

    public function stop(): self
    {
        $elapsed = $this->clock->nanoseconds() - $this->nanoseconds;

        return new Duration(nanoseconds: $elapsed, clock: $this->clock);
    }

    public function toMilliseconds(): float
    {
        return round($this->nanoseconds / self::NANOSECONDS_PER_MILLISECOND, 2);
    }
}
