<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal;

use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\Internal\HttpStreamContext;
use Skedli\HttpMiddleware\Internal\HttpTimeout;

final class HttpStreamContextTest extends TestCase
{
    public function testCreatesStreamContextWithConfiguredTimeout(): void
    {
        /** @Given a timeout of 7 seconds */
        $timeout = HttpTimeout::inSeconds(7);

        /** @When a stream context is created from the timeout */
        $context = HttpStreamContext::from(timeout: $timeout);

        /** @Then the underlying resource should have the HTTP timeout option set */
        $options = stream_context_get_options($context->toResource());

        self::assertSame(7, $options['http']['timeout']);
    }

    public function testCreatesStreamContextWithDefaultTimeout(): void
    {
        /** @Given the default timeout */
        $timeout = HttpTimeout::default();

        /** @When a stream context is created from the timeout */
        $context = HttpStreamContext::from(timeout: $timeout);

        /** @Then the underlying resource should have the default timeout value */
        $options = stream_context_get_options($context->toResource());

        self::assertSame(5, $options['http']['timeout']);
    }
}
