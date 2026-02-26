<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Error;

use Psr\Http\Message\ServerRequestInterface;
use Skedli\HttpMiddleware\Internal\Log\CorrelatedLogger;
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

    public function log(Throwable $exception, ServerRequestInterface $request): void
    {
        if ($this->logger === null || !$this->settings->logErrors) {
            return;
        }

        $logger = CorrelatedLogger::from(logger: $this->logger)->resolve(request: $request);

        $context = ['message' => $exception->getMessage()];

        if ($this->settings->logErrorDetails) {
            $context['exception'] = $exception::class;
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
            $context['trace'] = $exception->getTraceAsString();
        }

        $logger->error(message: 'error', context: $context);
    }
}
