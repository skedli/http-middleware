<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\HealthCheck\HealthPayload;
use Skedli\HttpMiddleware\Internal\HealthCheck\LivenessHandlerBuilder;
use TinyBlocks\EnvironmentVariable\EnvironmentVariable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\Response;

final readonly class LivenessHandler implements RequestHandlerInterface
{
    private function __construct()
    {
    }

    public static function create(): LivenessHandlerBuilder
    {
        return new LivenessHandlerBuilder();
    }

    public static function build(): LivenessHandler
    {
        return new LivenessHandler();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $serviceName = EnvironmentVariable::fromOrDefault(
            name: 'APP_NAME',
            defaultValueIfNotFound: 'app'
        )->toString();

        return Response::from(
            code: Code::OK,
            body: HealthPayload::alive(service: $serviceName)->jsonSerialize()
        );
    }
}
