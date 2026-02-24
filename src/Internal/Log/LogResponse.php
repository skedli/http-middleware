<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Log;

use Psr\Http\Message\ResponseInterface;
use Skedli\HttpMiddleware\Internal\Duration;
use TinyBlocks\Http\Code;

final readonly class LogResponse
{
    private function __construct(
        private string $uri,
        private ?array $body,
        private string $method,
        private Duration $duration,
        private int $statusCode
    ) {
    }

    public static function from(
        LogRequest $request,
        ResponseInterface $response,
        Duration $duration
    ): LogResponse {
        $context = $request->toContext();

        return new LogResponse(
            uri: $context['uri'],
            body: json_decode($response->getBody()->__toString(), true),
            method: $context['method'],
            duration: $duration,
            statusCode: $response->getStatusCode()
        );
    }

    public function isError(): bool
    {
        return Code::isErrorCode(code: $this->statusCode);
    }

    public function toContext(): array
    {
        $context = [
            'method'      => $this->method,
            'uri'         => $this->uri,
            'status_code' => $this->statusCode,
            'duration_ms' => $this->duration->toMilliseconds(),
        ];

        if (!empty($this->body)) {
            $context['body'] = $this->body;
        }

        return $context;
    }
}
