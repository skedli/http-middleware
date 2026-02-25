<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Error;

use Throwable;

final readonly class ErrorResponseBody
{
    private function __construct(private ErrorHandlingSettings $settings)
    {
    }

    public static function from(ErrorHandlingSettings $settings): ErrorResponseBody
    {
        return new ErrorResponseBody(settings: $settings);
    }

    public function build(Throwable $exception): string
    {
        $body = ['error' => $exception->getMessage()];

        if ($this->settings->displayErrorDetails) {
            $body['exception'] = $exception::class;
            $body['file'] = $exception->getFile();
            $body['line'] = $exception->getLine();
            $body['trace'] = explode("\n", $exception->getTraceAsString());
        }

        return (string)json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
