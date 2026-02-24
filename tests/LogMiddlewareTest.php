<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\CorrelationId;
use Skedli\HttpMiddleware\LogMiddleware;
use Test\Skedli\HttpMiddleware\Mocks\CapturingHandler;
use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\Logger;

final class LogMiddlewareTest extends TestCase
{
    public function testLogsRequestAndResponseOnSuccess(): void
    {
        /** @Given a GET request */
        $request = new ServerRequest('GET', '/users');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200, [], '{"status":"ok"}'));

        /** @And a logger that expects to log request and response */
        $logger = $this->createMock(Logger::class);

        $logger->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context): void {
                match ($message) {
                    'request'  => self::assertSame('GET', $context['method']),
                    'response' => self::assertSame(200, $context['status_code']),
                    default    => self::fail("Unexpected log message: $message")
                };
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be returned unchanged */
        self::assertSame(200, $actual->getStatusCode());
    }

    public function testLogsErrorLevelOnClientErrorResponse(): void
    {
        /** @Given a POST request */
        $request = new ServerRequest('POST', '/users');

        /** @And a handler that returns a 422 response */
        $handler = new CapturingHandler(
            response: new Response(422, [], '{"error":"Unprocessable Entity"}')
        );

        /** @And a logger that expects info for request and error for response */
        $logger = $this->createMock(Logger::class);

        $logger->expects(self::once())
            ->method('info')
            ->with('request');

        $logger->expects(self::once())
            ->method('error')
            ->with(
                'response',
                self::callback(function (array $context): bool {
                    return $context['status_code'] === 422;
                })
            );

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be returned with status 422 */
        self::assertSame(422, $actual->getStatusCode());
    }

    public function testLogsErrorLevelOnServerErrorResponse(): void
    {
        /** @Given a DELETE request */
        $request = new ServerRequest('DELETE', '/account/123');

        /** @And a handler that returns a 500 response */
        $handler = new CapturingHandler(
            response: new Response(500, [], '{"error":"Internal Server Error"}')
        );

        /** @And a logger that expects info for request and error for response */
        $logger = $this->createMock(Logger::class);

        $logger->expects(self::once())
            ->method('info')
            ->with('request');

        $logger->expects(self::once())
            ->method('error')
            ->with(
                'response',
                self::callback(function (array $context): bool {
                    return $context['status_code'] === 500;
                })
            );

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be returned with status 500 */
        self::assertSame(500, $actual->getStatusCode());
    }

    public function testLogsResponseContextContainsDuration(): void
    {
        /** @Given a GET request */
        $request = new ServerRequest('GET', '/health');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a logger that captures the response context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'response') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the response context should contain a non-negative duration in milliseconds */
        self::assertArrayHasKey('duration_ms', $capturedContext);
        self::assertIsFloat($capturedContext['duration_ms']);
        self::assertGreaterThanOrEqual(0, $capturedContext['duration_ms']);
    }

    public function testLogsQueryParamsWhenPresent(): void
    {
        /** @Given a GET request with query parameters */
        $request = new ServerRequest('GET', '/users?page=1&limit=10')
            ->withQueryParams(['page' => '1', 'limit' => '10']);

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a logger that captures the request context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'request') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the request context should contain the query parameters */
        self::assertArrayHasKey('query_parameters', $capturedContext);
        self::assertSame('1', $capturedContext['query_parameters']['page']);
        self::assertSame('10', $capturedContext['query_parameters']['limit']);
    }

    public function testLogsOmitsQueryParamsWhenAbsent(): void
    {
        /** @Given a GET request without query parameters */
        $request = new ServerRequest('GET', '/users');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a logger that captures the request context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'request') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the request context should not contain query_parameters */
        self::assertArrayNotHasKey('query_parameters', $capturedContext);
    }

    public function testLogsRequestBodyWhenPresent(): void
    {
        /** @Given a POST request with a parsed JSON body */
        $request = new ServerRequest('POST', '/users');
        $request = $request->withParsedBody(['name' => 'John', 'email' => 'john@example.com']);

        /** @And a handler that returns a 201 response */
        $handler = new CapturingHandler(response: new Response(201));

        /** @And a logger that captures the request context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'request') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the request context should contain the body */
        self::assertArrayHasKey('body', $capturedContext);
        self::assertSame('John', $capturedContext['body']['name']);
        self::assertSame('john@example.com', $capturedContext['body']['email']);
    }

    public function testLogsOmitsRequestBodyWhenAbsent(): void
    {
        /** @Given a GET request without a body */
        $request = new ServerRequest('GET', '/users');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a logger that captures the request context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'request') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the request context should not contain body */
        self::assertArrayNotHasKey('body', $capturedContext);
    }

    public function testLogsResponseBodyWhenPresent(): void
    {
        /** @Given a GET request */
        $request = new ServerRequest('GET', '/users/1');

        /** @And a handler that returns a response with a JSON body */
        $responseBody = (string)json_encode(['id' => 1, 'name' => 'John']);
        $handler = new CapturingHandler(
            response: new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        );

        /** @And a logger that captures the response context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'response') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the response context should contain the decoded body */
        self::assertArrayHasKey('body', $capturedContext);
        self::assertSame(1, $capturedContext['body']['id']);
        self::assertSame('John', $capturedContext['body']['name']);
    }

    public function testLogsOmitsResponseBodyWhenEmpty(): void
    {
        /** @Given a DELETE request */
        $request = new ServerRequest('DELETE', '/users/1');

        /** @And a handler that returns a 204 No Content response */
        $handler = new CapturingHandler(response: new Response(204));

        /** @And a logger that captures the response context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'response') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the response context should not contain body */
        self::assertArrayNotHasKey('body', $capturedContext);
    }

    public function testLogsOmitsResponseBodyWhenNotJson(): void
    {
        /** @Given a GET request */
        $request = new ServerRequest('GET', '/page');

        /** @And a handler that returns an HTML response */
        $handler = new CapturingHandler(
            response: new Response(200, ['Content-Type' => 'text/html'], '<h1>Hello</h1>')
        );

        /** @And a logger that captures the response context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'response') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the response context should not contain body since it's not JSON */
        self::assertArrayNotHasKey('body', $capturedContext);
    }

    public function testLogsResponseIsReturnedUnchanged(): void
    {
        /** @Given a GET request */
        $request = new ServerRequest('GET', '/data');

        /** @And a handler that returns a response with custom headers and body */
        $expectedResponse = new Response(
            200,
            ['X-Custom' => 'value', 'Content-Type' => 'application/json'],
            '{"data":"test"}'
        );
        $handler = new CapturingHandler(response: $expectedResponse);

        /** @And a logger */
        $logger = $this->createMock(Logger::class);

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response should be the exact same instance returned by the handler */
        self::assertSame($expectedResponse, $actual);

        /** @And the custom header should be preserved */
        self::assertSame('value', $actual->getHeaderLine('X-Custom'));
    }

    public function testLogsResponseBodyStreamRemainsReadableAfterLogging(): void
    {
        /** @Given a GET request */
        $request = new ServerRequest('GET', '/users');

        /** @And a handler that returns a response with a JSON body */
        $bodyContent = '{"id":1}';
        $handler = new CapturingHandler(
            response: new Response(200, ['Content-Type' => 'application/json'], $bodyContent)
        );

        /** @And a logger */
        $logger = $this->createMock(Logger::class);

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $actual = $middleware->process($request, $handler);

        /** @Then the response body should still be fully readable after logging consumed it */
        self::assertSame($bodyContent, $actual->getBody()->__toString());
    }

    public function testLogsDifferentHttpMethods(): void
    {
        /** @Given a logger that captures all request contexts */
        $logger = $this->createMock(Logger::class);

        $capturedMethods = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedMethods): void {
                if ($message === 'request') {
                    $capturedMethods[] = $context['method'];
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes requests with different HTTP methods */
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $request = new ServerRequest($method, '/resource');
            $handler = new CapturingHandler(response: new Response(200));
            $middleware->process($request, $handler);
        }

        /** @Then all methods should have been logged */
        self::assertSame($methods, $capturedMethods);
    }

    public function testLogsRequestContextAlwaysContainsMethodAndUri(): void
    {
        /** @Given a PUT request to a specific URI */
        $request = new ServerRequest('PUT', '/orders/42');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a logger that captures the request context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'request') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the context should always contain method and uri */
        self::assertSame('PUT', $capturedContext['method']);
        self::assertSame('/orders/42', $capturedContext['uri']);
    }

    public function testLogsResponseContextAlwaysContainsMethodUriStatusCodeAndDuration(): void
    {
        /** @Given a PATCH request */
        $request = new ServerRequest('PATCH', '/items/7');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a logger that captures the response context */
        $logger = $this->createMock(Logger::class);

        $capturedContext = [];

        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                if ($message === 'response') {
                    $capturedContext = $context;
                }
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the response context should contain all required keys */
        self::assertSame('PATCH', $capturedContext['method']);
        self::assertSame('/items/7', $capturedContext['uri']);
        self::assertSame(200, $capturedContext['status_code']);
        self::assertArrayHasKey('duration_ms', $capturedContext);
    }

    public function testLogsWithCorrelationIdWhenPresent(): void
    {
        /** @Given a request with a correlation ID attribute */
        $correlationId = $this->createMock(CorrelationId::class);
        $correlationId->method('toString')->willReturn('req-abc-123');

        $request = new ServerRequest('GET', '/users')
            ->withAttribute('correlationId', $correlationId);

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a contextual logger returned by withContext */
        $contextualLogger = $this->createMock(Logger::class);

        $contextualLogger->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message): void {
                self::assertContains($message, ['request', 'response']);
            });

        /** @And a base logger that expects withContext to be called with the correlation ID */
        $logger = $this->createMock(Logger::class);

        $logger->expects(self::once())
            ->method('withContext')
            ->with(
                self::callback(function (LogContext $context): bool {
                    return $context->correlationId === 'req-abc-123';
                })
            )
            ->willReturn($contextualLogger);

        $logger->expects(self::never())->method('info');
        $logger->expects(self::never())->method('error');

        /** @And a middleware configured with the base logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
        /** @Then the contextual logger should have been used (verified by mock expectations) */
    }

    public function testLogsWithBaseLoggerWhenCorrelationIdIsAbsent(): void
    {
        /** @Given a request without a correlation ID attribute */
        $request = new ServerRequest('GET', '/health');

        /** @And a handler that returns a successful response */
        $handler = new CapturingHandler(response: new Response(200));

        /** @And a logger that expects info to be called directly without withContext */
        $logger = $this->createMock(Logger::class);

        $logger->expects(self::never())->method('withContext');

        $logger->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message): void {
                self::assertContains($message, ['request', 'response']);
            });

        /** @And a middleware configured with the logger */
        $middleware = LogMiddleware::create(logger: $logger);

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);
        /** @Then the base logger should have been used directly (verified by mock expectations) */
    }
}
