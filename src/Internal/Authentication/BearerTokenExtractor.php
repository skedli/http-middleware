<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication;

use Psr\Http\Message\ServerRequestInterface;

final readonly class BearerTokenExtractor
{
    private const string BEARER_PREFIX = 'Bearer ';
    private const string BEARER_SCHEME = 'Bearer';

    public function extract(ServerRequestInterface $request): string
    {
        $authorizationHeader = $request->getHeaderLine('Authorization');

        if (empty($authorizationHeader)) {
            throw TokenValidationFailed::withReason(reason: 'Missing Authorization header.');
        }

        if ($authorizationHeader === self::BEARER_SCHEME) {
            throw TokenValidationFailed::withReason(reason: 'Bearer token is empty.');
        }

        if (!str_starts_with($authorizationHeader, self::BEARER_PREFIX)) {
            throw TokenValidationFailed::withReason(reason: 'Authorization header must use Bearer scheme.');
        }

        return substr($authorizationHeader, strlen(self::BEARER_PREFIX));
    }
}
