<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

use JsonSerializable;
use Skedli\HttpMiddleware\HealthCheckStatus;

final readonly class CheckEntry implements JsonSerializable
{
    public function __construct(
        public string $name,
        public HealthCheckStatus $status,
        public bool $critical,
        public float $durationInMilliseconds,
        public ?string $message = null
    ) {
    }

    public function jsonSerialize(): array
    {
        $payload = [
            'status' => $this->status->value,
            'critical' => $this->critical
        ];

        if (!is_null($this->message)) {
            $payload['message'] = $this->message;
        }

        $payload['duration_in_milliseconds'] = $this->durationInMilliseconds;

        return $payload;
    }
}
