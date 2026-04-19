<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Skedli\HttpMiddleware\HealthCheckHandler;
use Skedli\HttpMiddleware\HealthCheckResult;
use Test\Skedli\HttpMiddleware\Mocks\HealthCheckMock;
use TinyBlocks\Http\Code;

final class HealthCheckHandlerTest extends TestCase
{
    public function testHealthCheckWithoutChecks(): void
    {
        /** @Given a health check endpoint with no registered checks */
        $endpoint = HealthCheckHandler::create()->build();

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response status code should be 200 OK */
        $body = json_decode($response->getBody()->__toString(), true);

        /** @And the status should indicate the service is healthy */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the status message should be OK */
        self::assertSame(Code::OK->message(), $body['status']);

        /** @And the service name should be present */
        self::assertArrayHasKey('service', $body);

        /** @And no checks should be listed */
        self::assertArrayNotHasKey('checks', $body);
    }

    public function testHealthCheckWithAllChecksUp(): void
    {
        /** @Given a health check endpoint with a check that reports UP */
        $endpoint = HealthCheckHandler::create()
            ->withCheck(check: HealthCheckMock::reporting(name: 'database', result: HealthCheckResult::up()))
            ->build();

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response status code should be 200 OK */
        $body = json_decode($response->getBody()->__toString(), true);

        /** @And the status should indicate the service is healthy */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the status message should be OK */
        self::assertSame(Code::OK->message(), $body['status']);

        /** @And the checks should be present */
        self::assertArrayHasKey('checks', $body);

        /** @And the database check status should be UP */
        self::assertSame('UP', $body['checks']['database']['status']);

        /** @And the database check should be critical */
        self::assertTrue($body['checks']['database']['critical']);
    }

    public function testHealthCheckWithCriticalCheckDown(): void
    {
        /** @Given a health check endpoint with a critical check that reports DOWN */
        $endpoint = HealthCheckHandler::create()
            ->withCheck(check: HealthCheckMock::reporting(
                name: 'database',
                result: HealthCheckResult::down(message: 'Connection refused')
            ))
            ->build();

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response status code should be 503 Service Unavailable */
        $body = json_decode($response->getBody()->__toString(), true);

        /** @And the status should indicate the service is unavailable */
        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $response->getStatusCode());

        /** @And the status message should be Service Unavailable */
        self::assertSame(Code::SERVICE_UNAVAILABLE->message(), $body['status']);

        /** @And the database check status should be DOWN */
        self::assertSame('DOWN', $body['checks']['database']['status']);

        /** @And the database check should be critical */
        self::assertTrue($body['checks']['database']['critical']);

        /** @And the message should describe the failure */
        self::assertSame('Connection refused', $body['checks']['database']['message']);
    }

    public function testHealthCheckWithNonCriticalCheckDown(): void
    {
        /** @Given a health check endpoint with a non-critical check that reports DOWN */
        $endpoint = HealthCheckHandler::create()
            ->withCheck(check: HealthCheckMock::reporting(
                name: 'cache',
                result: HealthCheckResult::down(critical: false, message: 'Cache unavailable')
            ))
            ->build();

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response status code should still be 200 OK */
        $body = json_decode($response->getBody()->__toString(), true);

        /** @And the status should indicate the service is healthy */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the status message should be OK */
        self::assertSame(Code::OK->message(), $body['status']);

        /** @And the cache check status should be DOWN */
        self::assertSame('DOWN', $body['checks']['cache']['status']);

        /** @And the cache check should not be critical */
        self::assertFalse($body['checks']['cache']['critical']);
    }

    public function testHealthCheckWithCheckThrowingException(): void
    {
        /** @Given a health check endpoint with a check that throws an exception */
        $endpoint = HealthCheckHandler::create()
            ->withCheck(check: HealthCheckMock::throwing(
                name: 'database',
                exception: new RuntimeException('Connection timed out')
            ))
            ->build();

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response status code should be 503 Service Unavailable */
        $body = json_decode($response->getBody()->__toString(), true);

        /** @And the status code should indicate unavailability */
        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $response->getStatusCode());

        /** @And the database check status should be DOWN */
        self::assertSame('DOWN', $body['checks']['database']['status']);

        /** @And the database check should be critical */
        self::assertTrue($body['checks']['database']['critical']);

        /** @And the message should describe the exception */
        self::assertSame('Connection timed out', $body['checks']['database']['message']);
    }
}

