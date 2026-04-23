<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

final readonly class DrainMarker
{
    public function __construct(private string $path)
    {
    }

    public function isDraining(): bool
    {
        clearstatcache(true, $this->path);
        return is_file($this->path);
    }
}
