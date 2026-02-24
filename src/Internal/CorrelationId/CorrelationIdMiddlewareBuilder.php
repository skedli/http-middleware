<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\CorrelationId;

use Skedli\HttpMiddleware\CorrelationIdMiddleware;
use Skedli\HttpMiddleware\CorrelationIdProvider;

final class CorrelationIdMiddlewareBuilder
{
    private ?CorrelationIdProvider $provider = null;

    public function withProvider(CorrelationIdProvider $provider): CorrelationIdMiddlewareBuilder
    {
        $this->provider = $provider;
        return $this;
    }

    public function build(): CorrelationIdMiddleware
    {
        return CorrelationIdMiddleware::build(
            provider: $this->provider ?? new UuidCorrelationIdProvider()
        );
    }
}
