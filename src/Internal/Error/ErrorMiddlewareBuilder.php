<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Error;

use Skedli\HttpMiddleware\ErrorMiddleware;
use TinyBlocks\Logger\Logger;

final class ErrorMiddlewareBuilder
{
    private ?Logger $logger = null;
    private ErrorHandlingSettings $settings;

    public function __construct()
    {
        $this->settings = ErrorHandlingSettings::default();
    }

    public function withLogger(Logger $logger): ErrorMiddlewareBuilder
    {
        $this->logger = $logger;
        return $this;
    }

    public function withSettings(ErrorHandlingSettings $settings): ErrorMiddlewareBuilder
    {
        $this->settings = $settings;
        return $this;
    }

    public function build(): ErrorMiddleware
    {
        return ErrorMiddleware::build(logger: $this->logger, settings: $this->settings);
    }
}
