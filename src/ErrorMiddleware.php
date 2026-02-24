<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\ErrorMiddlewareBuilder;
use Throwable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ContentType;
use TinyBlocks\Http\Response;
use TinyBlocks\Logger\Logger;

final readonly class ErrorMiddleware implements MiddlewareInterface
{
    private function __construct(private ?Logger $logger)
    {
    }

    public static function create(): ErrorMiddlewareBuilder
    {
        return new ErrorMiddlewareBuilder();
    }

    public static function build(?Logger $logger): ErrorMiddleware
    {
        return new ErrorMiddleware(logger: $logger);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $this->logger?->error(message: $exception->getMessage());

            $exceptionCode = $exception->getCode();
            $code = Code::isErrorCode(code: $exceptionCode) ? Code::from($exceptionCode) : Code::INTERNAL_SERVER_ERROR;

            return Response::from($code, $exception->getMessage(), ContentType::applicationJson());
        }
    }
}
