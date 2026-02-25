<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Error;

use Throwable;
use TinyBlocks\Logger\Logger;

final readonly class ErrorLogger
{
    private function __construct(private ?Logger $logger, private ErrorHandlingSettings $settings)
    {
    }

    public static function from(?Logger $logger, ErrorHandlingSettings $settings): ErrorLogger
    {
        return new ErrorLogger(logger: $logger, settings: $settings);
    }

    public function log(Throwable $exception): void
    {
        if ($this->logger === null || !$this->settings->logErrors) {
            return;
        }

        if ($this->settings->logErrorDetails) {
            $this->logger->error(message: $exception->getMessage(), context: [
                'exception' => $exception::class,
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTraceAsString()
            ]);
            return;
        }

        $this->logger->error(message: $exception->getMessage());
    }
}
