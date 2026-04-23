<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

use JsonSerializable;

final readonly class ChecksReport implements JsonSerializable
{
    public function __construct(public array $entries, public bool $hasCriticalFailure)
    {
    }

    public function jsonSerialize(): array
    {
        $map = [];

        foreach ($this->entries as $entry) {
            $map[$entry->name] = $entry;
        }

        return $map;
    }
}
