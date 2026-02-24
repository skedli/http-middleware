<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\CorrelationId;

use Skedli\HttpMiddleware\CorrelationId;

final readonly class UuidCorrelationId implements CorrelationId
{
    private function __construct(private string $value)
    {
    }

    public static function from(string $value): UuidCorrelationId
    {
        return new UuidCorrelationId(value: $value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
