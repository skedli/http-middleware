<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Error;

final readonly class ErrorHandlingSettings
{
    private function __construct(
        public bool $logErrors,
        public bool $logErrorDetails,
        public bool $displayErrorDetails
    ) {
    }

    public static function default(): ErrorHandlingSettings
    {
        return self::from(
            logErrors: false,
            logErrorDetails: false,
            displayErrorDetails: false
        );
    }

    public static function from(
        bool $logErrors,
        bool $logErrorDetails,
        bool $displayErrorDetails
    ): ErrorHandlingSettings {
        return new ErrorHandlingSettings(
            logErrors: $logErrors,
            logErrorDetails: $logErrorDetails,
            displayErrorDetails: $displayErrorDetails
        );
    }
}
