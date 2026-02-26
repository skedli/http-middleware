<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\HealthCheckHandler;
use TinyBlocks\Http\Code;

final class HealthCheckHandlerTest extends TestCase
{
    public function testHealthCheckHandler(): void
    {
        /** @Given a health check endpoint */
        $endpoint = new HealthCheckHandler();

        /** @When a request is made to the endpoint */
        $request = new ServerRequest('GET', '/');

        /** @Then the response should indicate the service is healthy */
        $response = $endpoint->handle(request: $request);

        $body = json_decode($response->getBody()->__toString(), true);

        self::assertSame(Code::OK->value, $response->getStatusCode());
        self::assertSame(Code::OK->message(), $body['status']);
        self::assertArrayHasKey('service', $body);
    }
}
