<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use Exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\ErrorMiddleware;
use Test\Skedli\HttpMiddleware\Mocks\CapturingHandler;
use TinyBlocks\Http\Code;
use TinyBlocks\Logger\Logger;

final class ErrorMiddlewareTest extends TestCase
{
    public function testReturnsResponseFromHandlerOnSuccess(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/users');

        /** @And a middleware without a logger */
        $middleware = ErrorMiddleware::create()->build();

        /** @And a handler that returns a successful response */
        $expectedResponse = new Response(Code::OK->value, [], '{"status":"ok"}');
        $handler = new CapturingHandler(response: $expectedResponse);

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be returned unchanged */
        self::assertSame($expectedResponse, $actual);
        self::assertSame(Code::OK->value, $actual->getStatusCode());
    }

    public function testReturnsInternalServerErrorOnGenericException(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/checkout');

        /** @And a middleware without a logger */
        $middleware = ErrorMiddleware::create()->build();

        /** @And a handler that throws a generic exception */
        $exceptionMessage = 'Unexpected database error';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the status code should be 500 (Internal Server Error) */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());

        /** @And the body should contain the exception message */
        self::assertStringContainsString($exceptionMessage, (string)$actual->getBody());

        /** @And the content type should be application/json */
        self::assertStringContainsString('application/json', $actual->getHeaderLine('Content-Type'));
    }

    public function testReturnsSpecificHttpCodeOnException(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/login');

        /** @And a middleware without a logger */
        $middleware = ErrorMiddleware::create()->build();

        /** @And a handler that throws an exception with a specific HTTP error code (e.g., 401 Unauthorized) */
        $errorCode = Code::UNAUTHORIZED->value;
        $exceptionMessage = 'Invalid credentials';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage, $errorCode));

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the status code should match the exception code */
        self::assertSame($errorCode, $actual->getStatusCode());

        /** @And the body should contain the exception message */
        self::assertStringContainsString($exceptionMessage, (string)$actual->getBody());
    }

    public function testLogsErrorWhenLoggerIsProvided(): void
    {
        /** @Given a request */
        $request = new ServerRequest('DELETE', '/account');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Critical system failure';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects to receive the error message */
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('error')
            ->with($exceptionMessage);

        /** @And the middleware configured with this logger */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
        /** @Then the exception should be logged (verified by the mock expectation) */
    }

    public function testDoesNotLogWhenLoggerIsNotProvided(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception('Silent error'));

        /** @And the middleware explicitly built without a logger */
        $middleware = ErrorMiddleware::create()->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the process should complete returning a 500 response */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
        /** @And no exception should be thrown regarding the missing logger */
    }
}
