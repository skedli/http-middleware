<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use Exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\ErrorMiddleware;
use Skedli\HttpMiddleware\Internal\Error\ErrorHandlingSettings;
use Test\Skedli\HttpMiddleware\Mocks\CapturingHandler;
use TinyBlocks\Http\Code;
use TinyBlocks\Logger\Logger;

final class ErrorMiddlewareTest extends TestCase
{
    public function testReturnsResponseFromHandlerOnSuccess(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/users');

        /** @And a middleware with default settings */
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

        /** @And a middleware with default settings */
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

        /** @And a middleware with default settings */
        $middleware = ErrorMiddleware::create()->build();

        /** @And a handler that throws an exception with a specific HTTP error code */
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

    public function testDoesNotLogWhenLoggerIsNotProvided(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception('Silent error'));

        /** @And a middleware built without a logger */
        $middleware = ErrorMiddleware::create()->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the process should complete returning a 500 response */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testDoesNotLogWhenLogErrorsIsDisabled(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/users');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception('Should not be logged'));

        /** @And a logger that should never be called */
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('error');

        /** @And a middleware with logErrors disabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: false,
                    logErrorDetails: false,
                    displayErrorDetails: false
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should still be 500 */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testLogsErrorMessageWhenLogErrorsIsEnabled(): void
    {
        /** @Given a request */
        $request = new ServerRequest('DELETE', '/account');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Critical system failure';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects to receive only the error message */
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('error')
            ->with($exceptionMessage, self::isEmpty());

        /** @And a middleware with logErrors enabled but logErrorDetails disabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: true,
                    logErrorDetails: false,
                    displayErrorDetails: false
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
        /** @Then the error message should be logged */
    }

    public function testLogsErrorDetailsWhenLogErrorDetailsIsEnabled(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/payments');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Payment gateway timeout';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects to receive the error message with detailed context */
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                $exceptionMessage,
                self::callback(function (array $context): bool {
                    return isset($context['exception'], $context['file'], $context['line'], $context['trace'])
                        && $context['exception'] === Exception::class
                        && is_string($context['file'])
                        && is_int($context['line'])
                        && is_string($context['trace']);
                })
            );

        /** @And a middleware with logErrors and logErrorDetails enabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: true,
                    logErrorDetails: true,
                    displayErrorDetails: false
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
        /** @Then the error details should be logged */
    }

    public function testResponseBodyContainsOnlyErrorMessageByDefault(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/data');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Something went wrong';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @And a middleware with default settings */
        $middleware = ErrorMiddleware::create()->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the body should contain the error message */
        $body = json_decode((string)$actual->getBody(), true);

        self::assertSame($exceptionMessage, $body['error']);

        /** @And the body should not contain exception details */
        self::assertArrayNotHasKey('exception', $body);
        self::assertArrayNotHasKey('file', $body);
        self::assertArrayNotHasKey('line', $body);
        self::assertArrayNotHasKey('trace', $body);
    }

    public function testResponseBodyContainsDetailsWhenDisplayErrorDetailsIsEnabled(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/orders');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Order processing failed';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @And a middleware with displayErrorDetails enabled */
        $middleware = ErrorMiddleware::create()
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: false,
                    logErrorDetails: false,
                    displayErrorDetails: true
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the body should contain error details */
        $body = json_decode((string)$actual->getBody(), true);

        self::assertSame($exceptionMessage, $body['error']);
        self::assertSame(Exception::class, $body['exception']);
        self::assertArrayHasKey('file', $body);
        self::assertArrayHasKey('line', $body);
        self::assertIsArray($body['trace']);
        self::assertNotEmpty($body['trace']);
    }

    public function testDisplayErrorDetailsDoesNotAffectLogging(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/reports');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Report generation failed';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects only the error message without context */
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('error')
            ->with($exceptionMessage, self::isEmpty());

        /** @And a middleware with displayErrorDetails enabled but logErrorDetails disabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: true,
                    logErrorDetails: false,
                    displayErrorDetails: true
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response body should contain details */
        $body = json_decode((string)$actual->getBody(), true);

        self::assertArrayHasKey('exception', $body);
    }

    public function testLogErrorDetailsDoesNotAffectResponseBody(): void
    {
        /** @Given a request */
        $request = new ServerRequest('PUT', '/config');

        /** @And a handler that throws an exception */
        $exceptionMessage = 'Config update failed';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @And a logger that expects error details in context */
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                $exceptionMessage,
                self::callback(function (array $context): bool {
                    return isset($context['exception']);
                })
            );

        /** @And a middleware with logErrorDetails enabled but displayErrorDetails disabled */
        $middleware = ErrorMiddleware::create()
            ->withLogger(logger: $logger)
            ->withSettings(
                settings: ErrorHandlingSettings::from(
                    logErrors: true,
                    logErrorDetails: true,
                    displayErrorDetails: false
                )
            )
            ->build();

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response body should not contain details */
        $body = json_decode((string)$actual->getBody(), true);

        self::assertSame($exceptionMessage, $body['error']);
        self::assertArrayNotHasKey('exception', $body);
        self::assertArrayNotHasKey('trace', $body);
    }

    public function testReturnsJsonWithoutEscapingSlashesOrUnicode(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/unicode');

        /** @And a middleware with default settings */
        $middleware = ErrorMiddleware::create()->build();

        /** @And a handler that throws an exception with slashes and Unicode characters */
        $exceptionMessage = 'Erro no caminho /var/www/html: ⚠️';
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception($exceptionMessage));

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the body should contain the message exactly as is, without escaping */
        $bodyString = (string)$actual->getBody();

        self::assertStringContainsString($exceptionMessage, $bodyString);

        /** @And the body should not contain escaped slashes */
        self::assertStringNotContainsString('\/', $bodyString);

        /** @And the body should not contain escaped Unicode */
        self::assertStringNotContainsString('\u26a0', $bodyString);
    }

    public function testReturnsInternalServerErrorWhenExceptionCodeIsNotAnErrorCode(): void
    {
        /** @Given a request */
        $request = new ServerRequest('POST', '/transaction');

        /** @And a middleware with default settings */
        $middleware = ErrorMiddleware::create()->build();

        /** @And a handler that throws an exception with a success HTTP code (e.g. 200) */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception('Logic error with success code', Code::OK->value));

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the status code should be forced to 500 (Internal Server Error) */
        self::assertSame(Code::INTERNAL_SERVER_ERROR->value, $actual->getStatusCode());
    }

    public function testDefaultSettingsAreSecureAndSilent(): void
    {
        /** @Given the default error handling settings */
        $settings = ErrorHandlingSettings::default();

        /** @Then logging should be disabled by default */
        self::assertFalse($settings->logErrors);

        /** @And logging details should be disabled by default */
        self::assertFalse($settings->logErrorDetails);

        /** @And display error details should be disabled by default */
        self::assertFalse($settings->displayErrorDetails);
    }

    public function testDoesNotLogByDefaultEvenIfLoggerIsProvided(): void
    {
        /** @Given a request */
        $request = new ServerRequest('GET', '/defaults');

        /** @And a handler that throws an exception */
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new Exception('Should be silent'));

        /** @And a logger that expects ZERO calls */
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('error');

        /** @And a middleware built with a logger but NO custom settings (relying on defaults) */
        $middleware = ErrorMiddleware::create()
            ->withLogger($logger)
            ->build();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
        /** @Then nothing should be logged */
    }
}
