<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal;

final readonly class HttpTimeout
{
    private const int DEFAULT_SECONDS = 5;

    private function __construct(public int $seconds)
    {
    }

    public static function inSeconds(int $seconds): HttpTimeout
    {
        return new HttpTimeout(seconds: $seconds);
    }

    public static function default(): HttpTimeout
    {
        return new HttpTimeout(seconds: self::DEFAULT_SECONDS);
    }
}
