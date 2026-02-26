<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TinyBlocks\EnvironmentVariable\EnvironmentVariable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\Response;

final readonly class HealthCheckHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return Response::ok(body: [
            'status'  => Code::OK->message(),
            'service' => EnvironmentVariable::fromOrDefault(name: 'APP_NAME', defaultValueIfNotFound: 'app')->toString()
        ]);
    }
}
