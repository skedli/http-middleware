<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal;

final readonly class HttpStreamContext
{
    /** @var resource */
    private mixed $context;

    private function __construct(HttpTimeout $timeout)
    {
        $this->context = stream_context_create([
            'http' => ['timeout' => $timeout->seconds]
        ]);
    }

    public static function from(HttpTimeout $timeout): HttpStreamContext
    {
        return new HttpStreamContext(timeout: $timeout);
    }

    /** @return resource */
    public function toResource(): mixed
    {
        return $this->context;
    }
}
