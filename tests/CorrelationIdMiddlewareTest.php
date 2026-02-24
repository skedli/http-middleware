<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Skedli\HttpMiddleware\CorrelationId;
use Skedli\HttpMiddleware\CorrelationIdMiddleware;
use Skedli\HttpMiddleware\CorrelationIdProvider;
use Skedli\HttpMiddleware\Internal\CorrelationId\UuidCorrelationId;
use Test\Skedli\HttpMiddleware\Mocks\CapturingHandler;

final class CorrelationIdMiddlewareTest extends TestCase
{
    public function testGeneratesCorrelationIdWhenHeaderIsMissing(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then a correlation ID should be generated and set as a request attribute */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertNotEmpty($correlationId->toString());

        /** @And the response should contain the generated Correlation-Id header */
        self::assertTrue($response->hasHeader('Correlation-Id'));
        self::assertSame($correlationId->toString(), $response->getHeaderLine('Correlation-Id'));
    }

    public function testGeneratedCorrelationIdMatchesUuidV4Format(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $middleware->process($request, $handler);

        /** @Then the generated value should match the UUID v4 format */
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        self::assertMatchesRegularExpression($uuidPattern, $handler->capturedCorrelationId()->toString());
    }

    public function testReusesCorrelationIdWhenHeaderIsPresent(): void
    {
        /** @Given a request with an existing Correlation-Id header */
        $existingCorrelationId = 'req-abc-123';
        $request = new ServerRequest('GET', '/', ['Correlation-Id' => $existingCorrelationId]);

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the existing correlation ID should be preserved in the request attribute */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertSame($existingCorrelationId, $correlationId->toString());

        /** @And the response should contain the same Correlation-Id header */
        self::assertSame($existingCorrelationId, $response->getHeaderLine('Correlation-Id'));
    }

    public function testGeneratesNewCorrelationIdWhenHeaderIsEmpty(): void
    {
        /** @Given a request with an empty Correlation-Id header */
        $request = new ServerRequest('GET', '/', ['Correlation-Id' => '']);

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then a new correlation ID should be generated */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertNotEmpty($correlationId->toString());

        /** @And the response should contain the generated Correlation-Id */
        self::assertTrue($response->hasHeader('Correlation-Id'));
        self::assertSame($correlationId->toString(), $response->getHeaderLine('Correlation-Id'));
    }

    public function testGeneratesNewCorrelationIdWhenHeaderIsWhitespaceOnly(): void
    {
        /** @Given a request that returns a whitespace-only value for the Correlation-Id header */
        $whitespaceValue = '   ';
        $request = new ServerRequest('GET', '/');
        $stubbedRequest = $this->createStub(ServerRequestInterface::class);

        $stubbedRequest->method('getHeaderLine')
            ->willReturnCallback(function (string $name) use ($whitespaceValue, $request): string {
                if (strcasecmp($name, 'Correlation-Id') === 0) {
                    return $whitespaceValue;
                }
                return $request->getHeaderLine($name);
            });

        $stubbedRequest->method('withAttribute')
            ->willReturnCallback(function (string $name, mixed $value) use ($request): ServerRequestInterface {
                return $request->withAttribute($name, $value);
            });

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($stubbedRequest, $handler);

        /** @Then a new correlation ID should be generated instead of using the whitespace */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertNotSame($whitespaceValue, $correlationId->toString());
        self::assertNotEmpty($correlationId->toString());

        /** @And the response should contain the generated Correlation-Id */
        self::assertTrue($response->hasHeader('Correlation-Id'));
        self::assertSame($correlationId->toString(), $response->getHeaderLine('Correlation-Id'));
    }

    public function testUsesCustomProvider(): void
    {
        /** @Given a custom provider that returns a fixed correlation ID */
        $fixedValue = 'custom-fixed-id-999';
        $customProvider = new readonly class ($fixedValue) implements CorrelationIdProvider {
            public function __construct(private string $value)
            {
            }

            public function generate(): CorrelationId
            {
                return UuidCorrelationId::from(value: $this->value);
            }
        };

        /** @And a middleware configured with the custom provider */
        $middleware = CorrelationIdMiddleware::create()
            ->withProvider(provider: $customProvider)
            ->build();

        /** @And a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the custom provider value should be used */
        $correlationId = $handler->capturedCorrelationId();

        self::assertNotNull($correlationId);
        self::assertSame($fixedValue, $correlationId->toString());

        /** @And the response header should contain the custom value */
        self::assertSame($fixedValue, $response->getHeaderLine('Correlation-Id'));
    }

    public function testPreservesExistingResponseHeaders(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And a handler that returns a response with an existing custom header */
        $handler = new CapturingHandler(
            response: new Response(
                status: 200,
                headers: ['Custom-Header' => 'custom-value']
            )
        );

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the existing response header should be preserved */
        self::assertSame('custom-value', $response->getHeaderLine('Custom-Header'));

        /** @And the Correlation-Id header should also be present */
        self::assertTrue($response->hasHeader('Correlation-Id'));
    }

    public function testCorrelationIdAttributeIsAccessibleDownstream(): void
    {
        /** @Given a request without the Correlation-Id header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the attribute value should match the response header */
        self::assertSame(
            $response->getHeaderLine('Correlation-Id'),
            $handler->capturedCorrelationId()->toString()
        );
    }

    public function testEachRequestGeneratesUniqueCorrelationId(): void
    {
        /** @Given a middleware using the default provider */
        $middleware = CorrelationIdMiddleware::create()->build();

        /** @And two separate requests without the Correlation-Id header */
        $firstRequest = new ServerRequest('GET', '/first');
        $secondRequest = new ServerRequest('GET', '/second');

        /** @When the middleware processes both requests */
        $firstHandler = new CapturingHandler();
        $secondHandler = new CapturingHandler();

        $middleware->process($firstRequest, $firstHandler);
        $middleware->process($secondRequest, $secondHandler);

        /** @Then each request should have received a different correlation ID */
        self::assertNotSame(
            $firstHandler->capturedCorrelationId()->toString(),
            $secondHandler->capturedCorrelationId()->toString()
        );
    }
}
