<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\Duration;
use Skedli\HttpMiddleware\Internal\Log\CorrelatedLogger;
use Skedli\HttpMiddleware\Internal\Log\LogRequest;
use Skedli\HttpMiddleware\Internal\Log\LogResponse;
use TinyBlocks\Logger\Logger;

final readonly class LogMiddleware implements MiddlewareInterface
{
    private function __construct(private CorrelatedLogger $correlatedLogger)
    {
    }

    public static function create(Logger $logger): LogMiddleware
    {
        return new LogMiddleware(correlatedLogger: CorrelatedLogger::from(logger: $logger));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $logger = $this->correlatedLogger->resolve(request: $request);
        $requestLog = LogRequest::from(request: $request);

        $logger->info(message: 'request', context: $requestLog->toContext());

        $duration = Duration::start();
        $response = $handler->handle($request);
        $duration = $duration->stop();

        $responseLog = LogResponse::from(
            request: $requestLog,
            response: $response,
            duration: $duration
        );

        if ($responseLog->isError()) {
            $logger->error(message: 'response', context: $responseLog->toContext());
            return $response;
        }

        $logger->info(message: 'response', context: $responseLog->toContext());

        return $response;
    }
}
