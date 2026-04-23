<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Skedli\HttpMiddleware\HealthCheckResult;
use Skedli\HttpMiddleware\Internal\HealthCheck\ReadinessMisconfigured;
use Skedli\HttpMiddleware\ReadinessHandler;
use Test\Skedli\HttpMiddleware\Mocks\CountingHealthCheckMock;
use Test\Skedli\HttpMiddleware\Mocks\HealthCheckMock;
use TinyBlocks\Http\Code;

final class ReadinessHandlerTest extends TestCase
{
    public function testReadinessHandlerBuildWithoutChecksThrowsReadinessMisconfigured(): void
    {
        /** @Then an exception should be thrown when no check is registered */
        $this->expectException(ReadinessMisconfigured::class);

        /** @When build is called with no checks registered */
        ReadinessHandler::create()->build();
    }

    public function testReadinessHandlerWithAllCriticalChecksUpReturnsOk(): void
    {
        /** @Given a readiness handler with a check that reports UP */
        $handler = ReadinessHandler::create()
            ->withCheck(check: HealthCheckMock::reporting(name: 'database', result: HealthCheckResult::up()))
            ->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/readiness'));

        /** @Then the response status code should be 200 OK */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the checks should be present in the response */
        $body = json_decode($response->getBody()->__toString(), true);
        self::assertArrayHasKey('checks', $body);

        /** @And the overall status should be OK */
        self::assertSame('OK', $body['status']);

        /** @And the database check status should be UP */
        self::assertSame('UP', $body['checks']['database']['status']);

        /** @And the database check should be marked as critical */
        self::assertTrue($body['checks']['database']['critical']);
    }

    public function testReadinessHandlerWithCriticalCheckDownReturnsServiceUnavailable(): void
    {
        /** @Given a readiness handler with a critical check that reports DOWN */
        $handler = ReadinessHandler::create()
            ->withCheck(check: HealthCheckMock::reporting(
                name: 'database',
                result: HealthCheckResult::down(message: 'Connection refused')
            ))
            ->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/readiness'));

        /** @Then the response status code should be 503 Service Unavailable */
        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $response->getStatusCode());

        /** @And the database check status should be DOWN */
        $body = json_decode($response->getBody()->__toString(), true);
        self::assertSame('DOWN', $body['checks']['database']['status']);

        /** @And the failure message should be present */
        self::assertSame('Connection refused', $body['checks']['database']['message']);
    }

    public function testReadinessHandlerWithNonCriticalCheckDownAndCriticalCheckUpReturnsOk(): void
    {
        /** @Given a readiness handler with a critical check UP and a non-critical check DOWN */
        $handler = ReadinessHandler::create()
            ->withCheck(check: HealthCheckMock::reporting(name: 'database', result: HealthCheckResult::up()))
            ->withCheck(check: HealthCheckMock::reporting(
                name: 'cache',
                result: HealthCheckResult::down(message: 'Cache unavailable', critical: false)
            ))
            ->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/readiness'));

        /** @Then the response status code should be 200 OK */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the non-critical cache check should be DOWN */
        $body = json_decode($response->getBody()->__toString(), true);
        self::assertSame('DOWN', $body['checks']['cache']['status']);

        /** @And the non-critical flag should be false */
        self::assertFalse($body['checks']['cache']['critical']);
    }

    public function testReadinessHandlerWithCheckThrowingExceptionReturnsServiceUnavailable(): void
    {
        /** @Given a readiness handler with a check that throws an exception */
        $handler = ReadinessHandler::create()
            ->withCheck(check: HealthCheckMock::throwing(
                name: 'database',
                exception: new RuntimeException('Connection timed out')
            ))
            ->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/readiness'));

        /** @Then the response status code should be 503 Service Unavailable */
        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $response->getStatusCode());

        /** @And the exception message should appear in the check payload */
        $body = json_decode($response->getBody()->__toString(), true);
        self::assertSame('Connection timed out', $body['checks']['database']['message']);
    }

    public function testReadinessHandlerWithActiveDrainMarkerReturnsDrainingWithoutExecutingChecks(): void
    {
        /** @Given a temporary drain marker file path */
        $drainPath = sys_get_temp_dir() . '/drain-' . uniqid('', true);

        /** @And the drain marker file is created */
        touch($drainPath);

        /** @And a counting check to observe whether it is invoked */
        $observedCheck = new CountingHealthCheckMock(name: 'database');

        /** @And a readiness handler configured with the drain marker and the check */
        $handler = ReadinessHandler::create()
            ->withCheck(check: $observedCheck)
            ->withDrainMarker(path: $drainPath)
            ->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/readiness'));

        /** @Then the response status code should be 503 Service Unavailable */
        self::assertSame(Code::SERVICE_UNAVAILABLE->value, $response->getStatusCode());

        /** @And the reason should indicate draining */
        $body = json_decode($response->getBody()->__toString(), true);
        self::assertSame('draining', $body['reason']);

        /** @And the overall status should indicate service unavailable */
        self::assertSame('Service Unavailable', $body['status']);

        /** @And the check should not have been invoked */
        self::assertSame(0, $observedCheck->invocationCount());

        unlink($drainPath);
    }

    public function testReadinessHandlerDetectsDrainDeactivationDespiteStaleStatCache(): void
    {
        /** @Given a drain marker target file */
        $targetPath = sys_get_temp_dir() . '/drain-target-' . uniqid('', true);

        /** @And a symlink path that will serve as the drain marker */
        $symlinkPath = sys_get_temp_dir() . '/drain-link-' . uniqid('', true);

        /** @And the target file is created */
        touch($targetPath);

        /** @And a symlink to the target is created at the drain marker path */
        symlink($targetPath, $symlinkPath);

        /** @And the drain marker is cached as active via the symlink path */
        is_file($symlinkPath);

        /** @And the target is removed via an external process to preserve the stale PHP stat cache for the symlink path */
        pclose(popen('rm ' . escapeshellarg($targetPath), 'r'));

        /** @And a readiness handler configured with the symlink path as the drain marker */
        $handler = ReadinessHandler::create()
            ->withCheck(check: HealthCheckMock::reporting(name: 'database', result: HealthCheckResult::up()))
            ->withDrainMarker(path: $symlinkPath)
            ->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/readiness'));

        /** @Then the response status code should be 200 OK */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        unlink($symlinkPath);
    }

    public function testReadinessHandlerWithInactiveDrainMarkerExecutesChecksNormally(): void
    {
        /** @Given a drain marker path that does not exist */
        $drainPath = sys_get_temp_dir() . '/non-existent-drain-' . uniqid('', true);

        /** @And a counting check to observe that it is invoked */
        $observedCheck = new CountingHealthCheckMock(name: 'database');

        /** @And a readiness handler with the inactive drain marker */
        $handler = ReadinessHandler::create()
            ->withCheck(check: $observedCheck)
            ->withDrainMarker(path: $drainPath)
            ->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/readiness'));

        /** @Then the response status code should be 200 OK */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the check should have been invoked */
        self::assertSame(1, $observedCheck->invocationCount());
    }
}
