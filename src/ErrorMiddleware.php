<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\Error\ErrorHandlingSettings;
use Skedli\HttpMiddleware\Internal\Error\ErrorLogger;
use Skedli\HttpMiddleware\Internal\Error\ErrorMiddlewareBuilder;
use Skedli\HttpMiddleware\Internal\Error\ErrorResponseBody;
use Throwable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\ContentType;
use TinyBlocks\Http\Response;
use TinyBlocks\Logger\Logger;

final readonly class ErrorMiddleware implements MiddlewareInterface
{
    private function __construct(private ErrorLogger $errorLogger, private ErrorResponseBody $errorResponseBody)
    {
    }

    public static function create(): ErrorMiddlewareBuilder
    {
        return new ErrorMiddlewareBuilder();
    }

    public static function build(?Logger $logger, ErrorHandlingSettings $settings): ErrorMiddleware
    {
        return new ErrorMiddleware(
            errorLogger: ErrorLogger::from(logger: $logger, settings: $settings),
            errorResponseBody: ErrorResponseBody::from(settings: $settings)
        );
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $this->errorLogger->log(exception: $exception);

            $exceptionCode = $exception->getCode();
            $code = Code::isErrorCode(code: $exceptionCode)
                ? Code::from($exceptionCode)
                : Code::INTERNAL_SERVER_ERROR;

            $body = $this->errorResponseBody->build(exception: $exception);

            return Response::from($code, $body, ContentType::applicationJson());
        }
    }
}
