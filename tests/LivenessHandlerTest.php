<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\LivenessHandler;
use TinyBlocks\Http\Code;

final class LivenessHandlerTest extends TestCase
{
    public function testLivenessHandlerReturns200WithDefaultServiceName(): void
    {
        /** @Given a liveness handler */
        $handler = LivenessHandler::create()->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/liveness'));

        /** @Then the response status code should be 200 OK */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the body should contain status OK */
        $body = json_decode($response->getBody()->__toString(), true);
        self::assertSame('OK', $body['status']);

        /** @And the service name should default to app */
        self::assertSame('app', $body['service']);
    }

    public function testLivenessHandlerUsesAppNameEnvironmentVariable(): void
    {
        /** @Given APP_NAME environment variable is set to identity */
        putenv('APP_NAME=identity');

        /** @And a liveness handler */
        $handler = LivenessHandler::create()->build();

        /** @When the handler handles a request */
        $response = $handler->handle(request: new ServerRequest(method: 'GET', uri: '/health/liveness'));

        /** @Then the service name in the body should be identity */
        $body = json_decode($response->getBody()->__toString(), true);
        self::assertSame('identity', $body['service']);

        putenv('APP_NAME');
    }
}
