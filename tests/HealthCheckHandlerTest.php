<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Skedli\HttpMiddleware\HealthCheck;
use Skedli\HttpMiddleware\HealthCheckHandler;
use Skedli\HttpMiddleware\HealthCheckResult;
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
        /** @Given a health check with a check that reports UP */
        $check = $this->createHealthCheck(name: 'database', result: HealthCheckResult::up());

        /** @And the endpoint is built with the check */
        $endpoint = HealthCheckHandler::create()->withCheck(check: $check)->build();

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
        /** @Given a health check with a critical check that reports DOWN */
        $check = $this->createHealthCheck(name: 'database', result: HealthCheckResult::down(message: 'Connection refused'));

        /** @And the endpoint is built with the check */
        $endpoint = HealthCheckHandler::create()->withCheck(check: $check)->build();

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
        /** @Given a health check with a non-critical check that reports DOWN */
        $check = $this->createHealthCheck(name: 'cache', result: HealthCheckResult::down(critical: false, message: 'Cache unavailable'));

        /** @And the endpoint is built with the check */
        $endpoint = HealthCheckHandler::create()->withCheck(check: $check)->build();

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
        /** @Given a health check that throws an exception */
        $check = $this->createMock(HealthCheck::class);

        /** @And the check is named "database" */
        $check->method('name')->willReturn('database');

        /** @And the check throws a RuntimeException */
        $check->method('check')->willThrowException(new RuntimeException('Connection timed out'));

        /** @And the endpoint is built with the check */
        $endpoint = HealthCheckHandler::create()->withCheck(check: $check)->build();

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

    private function createHealthCheck(string $name, HealthCheckResult $result): HealthCheck
    {
        $check = $this->createMock(HealthCheck::class);
        $check->method('name')->willReturn($name);
        $check->method('check')->willReturn($result);

        return $check;
    }
}
