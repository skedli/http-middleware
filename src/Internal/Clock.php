<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal;

/**
 * Provides the current time in nanoseconds.
 */
interface Clock
{
    /**
     * Returns the current time in nanoseconds.
     *
     * @return int The current time in nanoseconds.
     */
    public function nanoseconds(): int;
}
