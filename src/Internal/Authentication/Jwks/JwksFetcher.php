<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Jwks;

use Skedli\HttpMiddleware\Internal\Authentication\TokenValidationFailed;
use Skedli\HttpMiddleware\Internal\HttpStreamContext;
use Skedli\HttpMiddleware\Internal\HttpTimeout;

final readonly class JwksFetcher
{
    private function __construct(private HttpStreamContext $context)
    {
    }

    public static function using(HttpTimeout $timeout): JwksFetcher
    {
        return new JwksFetcher(context: HttpStreamContext::from(timeout: $timeout));
    }

    public function fetchFrom(string $url): array
    {
        $errorMessage = '';

        set_error_handler(static function (int $severity, string $message) use (&$errorMessage): true {
            $errorMessage = $message;
            return true;
        });

        try {
            $json = file_get_contents($url, false, $this->context->toResource());
        } finally {
            restore_error_handler();
        }

        if ($json === false) {
            throw TokenValidationFailed::withReason(
                reason: sprintf('Failed to fetch JWKS from <%s>: %s', $url, $errorMessage)
            );
        }

        $jwks = json_decode($json, true);

        if (!is_array($jwks) || empty($jwks['keys'])) {
            throw TokenValidationFailed::withReason(
                reason: sprintf('Invalid JWKS response from <%s>.', $url)
            );
        }

        return $jwks;
    }
}
