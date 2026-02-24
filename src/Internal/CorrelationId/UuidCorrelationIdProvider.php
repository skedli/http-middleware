<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\CorrelationId;

use Ramsey\Uuid\Uuid;
use Skedli\HttpMiddleware\CorrelationId;
use Skedli\HttpMiddleware\CorrelationIdProvider;

final readonly class UuidCorrelationIdProvider implements CorrelationIdProvider
{
    public function generate(): CorrelationId
    {
        return UuidCorrelationId::from(value: Uuid::uuid4()->toString());
    }
}
