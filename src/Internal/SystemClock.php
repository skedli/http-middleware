<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal;

final readonly class SystemClock implements Clock
{
    public function nanoseconds(): int
    {
        return hrtime(true);
    }
}
