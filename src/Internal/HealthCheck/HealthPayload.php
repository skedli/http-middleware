<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\HealthCheck;

use JsonSerializable;
use TinyBlocks\Http\Code;

final readonly class HealthPayload implements JsonSerializable
{
    private function __construct(
        private string $status,
        private string $service,
        private ?ChecksReport $checks = null,
        private ?string $reason = null
    ) {
    }

    public static function alive(string $service): self
    {
        return new self(status: Code::OK->message(), service: $service);
    }

    public static function draining(string $service): self
    {
        return new self(status: Code::SERVICE_UNAVAILABLE->message(), service: $service, reason: 'draining');
    }

    public static function ready(string $service, ChecksReport $checks): self
    {
        return new self(status: Code::OK->message(), service: $service, checks: $checks);
    }

    public static function unready(string $service, ChecksReport $checks): self
    {
        return new self(status: Code::SERVICE_UNAVAILABLE->message(), service: $service, checks: $checks);
    }

    public function jsonSerialize(): array
    {
        $payload = [
            'status' => $this->status,
            'service' => $this->service
        ];

        if (!is_null($this->reason)) {
            $payload['reason'] = $this->reason;
        }

        if (!is_null($this->checks)) {
            $payload['checks'] = $this->checks;
        }

        return $payload;
    }
}
