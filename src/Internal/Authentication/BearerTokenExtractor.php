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

        if ($authorizationHeader === '') {
            throw TokenValidationFailed::withReason(reason: 'Missing Authorization header.');
        }

        if (!str_starts_with($authorizationHeader, self::BEARER_SCHEME)) {
            throw TokenValidationFailed::withReason(reason: 'Authorization header must use Bearer scheme.');
        }

        if (!str_starts_with($authorizationHeader, self::BEARER_PREFIX)) {
            throw TokenValidationFailed::withReason(reason: 'Bearer token is empty.');
        }

        return substr($authorizationHeader, strlen(self::BEARER_PREFIX));
    }
}
