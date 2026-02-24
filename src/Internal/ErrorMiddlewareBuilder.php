<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal;

use Skedli\HttpMiddleware\ErrorMiddleware;
use TinyBlocks\Logger\Logger;

final class ErrorMiddlewareBuilder
{
    private ?Logger $logger = null;

    public function withLogger(Logger $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    public function build(): ErrorMiddleware
    {
        return ErrorMiddleware::build(logger: $this->logger);
    }
}
