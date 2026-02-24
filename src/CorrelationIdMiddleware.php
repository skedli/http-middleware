<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\CorrelationId\CorrelationIdMiddlewareBuilder;
use Skedli\HttpMiddleware\Internal\CorrelationId\UuidCorrelationId;

final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    private const string HEADER_NAME = 'Correlation-Id';
    private const string ATTRIBUTE_NAME = 'correlationId';

    private function __construct(private CorrelationIdProvider $provider)
    {
    }

    public static function create(): CorrelationIdMiddlewareBuilder
    {
        return new CorrelationIdMiddlewareBuilder();
    }

    public static function build(CorrelationIdProvider $provider): CorrelationIdMiddleware
    {
        return new CorrelationIdMiddleware(provider: $provider);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $headerValue = trim($request->getHeaderLine(self::HEADER_NAME));
        $correlationId = !empty($headerValue)
            ? UuidCorrelationId::from(value: $headerValue)
            : $this->provider->generate();

        $request = $request->withAttribute(self::ATTRIBUTE_NAME, $correlationId);
        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER_NAME, $correlationId->toString());
    }
}
