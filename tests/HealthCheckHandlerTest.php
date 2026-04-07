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
        $endpoint = new HealthCheckHandler();

        /** @When a request is made to the endpoint */
        $request = new ServerRequest('GET', '/');

        /** @Then the response should indicate the service is healthy */
        $response = $endpoint->handle(request: $request);

        $body = json_decode($response->getBody()->__toString(), true);

        self::assertSame(Code::OK->value, $response->getStatusCode());
        self::assertSame(Code::OK->message(), $body['status']);
        self::assertArrayHasKey('service', $body);
        self::assertArrayNotHasKey('checks', $body);
    }

    public function testHealthCheckWithAllChecksUp(): void
    {
        /** @Given a health check endpoint with a check that reports UP */
        $check = $this->createHealthCheck(name: 'database', result: HealthCheckResult::up());
        $endpoint = new HealthCheckHandler($check);

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response should indicate the service is healthy with check details */
        $body = json_decode($response->getBody()->__toString(), true);

        self::assertSame(Code::OK->value, $response->getStatusCode());
        self::assertSame(Code::OK->message(), $body['status']);
        self::assertArrayHasKey('checks', $body);
        self::assertSame('UP', $body['checks']['database']['status']);
        self::assertTrue($body['checks']['database']['critical']);
    }

    public function testHealthCheckWithCriticalCheckDown(): void
    {
        /** @Given a health check endpoint with a critical check that reports DOWN */
        $check = $this->createHealthCheck(
            name: 'database',
            result: HealthCheckResult::down(message: 'Connection refused')
        );
        $endpoint = new HealthCheckHandler($check);

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response should indicate the service is unavailable */
        $body = json_decode($response->getBody()->__toString(), true);

        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $response->getStatusCode());
        self::assertSame(Code::SERVICE_UNAVAILABLE->message(), $body['status']);
        self::assertSame('DOWN', $body['checks']['database']['status']);
        self::assertTrue($body['checks']['database']['critical']);
        self::assertSame('Connection refused', $body['checks']['database']['message']);
    }

    public function testHealthCheckWithNonCriticalCheckDown(): void
    {
        /** @Given a health check endpoint with a non-critical check that reports DOWN */
        $check = $this->createHealthCheck(
            name: 'cache',
            result: HealthCheckResult::down(critical: false, message: 'Cache unavailable')
        );
        $endpoint = new HealthCheckHandler($check);

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response should still indicate the service is healthy */
        $body = json_decode($response->getBody()->__toString(), true);

        self::assertSame(Code::OK->value, $response->getStatusCode());
        self::assertSame(Code::OK->message(), $body['status']);
        self::assertSame('DOWN', $body['checks']['cache']['status']);
        self::assertFalse($body['checks']['cache']['critical']);
    }

    public function testHealthCheckWithCheckThrowingException(): void
    {
        /** @Given a health check endpoint with a check that throws an exception */
        $check = $this->createMock(HealthCheck::class);
        $check->method('name')->willReturn('database');
        $check->method('check')->willThrowException(new RuntimeException('Connection timed out'));

        $endpoint = new HealthCheckHandler($check);

        /** @When a request is made to the endpoint */
        $response = $endpoint->handle(request: new ServerRequest('GET', '/'));

        /** @Then the response should indicate the service is unavailable */
        $body = json_decode($response->getBody()->__toString(), true);

        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $response->getStatusCode());
        self::assertSame('DOWN', $body['checks']['database']['status']);
        self::assertTrue($body['checks']['database']['critical']);
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
