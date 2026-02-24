<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Log;

use Psr\Http\Message\ServerRequestInterface;
use Skedli\HttpMiddleware\CorrelationId;
use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\Logger;

final readonly class CorrelatedLogger
{
    private function __construct(private Logger $logger)
    {
    }

    public static function from(Logger $logger): CorrelatedLogger
    {
        return new CorrelatedLogger(logger: $logger);
    }

    public function resolve(ServerRequestInterface $request): Logger
    {
        $correlationId = $request->getAttribute('correlationId');

        if (!$correlationId instanceof CorrelationId) {
            return $this->logger;
        }

        $context = LogContext::from(correlationId: $correlationId->toString());

        return $this->logger->withContext(context: $context);
    }
}
