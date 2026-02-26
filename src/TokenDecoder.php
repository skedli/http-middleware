<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Skedli\HttpMiddleware\Internal\Authentication\TokenValidationFailed;

/**
 * Decodes and validates access tokens.
 *
 * Implementations MUST validate the token locally (stateless), without performing
 * any network call or database query to external services.
 * The validation relies exclusively on the token's cryptographic signature and its claims.
 */
interface TokenDecoder
{
    /**
     * Decodes a raw token string and returns the authenticated user context.
     *
     * @param string $token The raw token value (without the "Bearer " prefix).
     * @return AuthenticatedUser The authenticated user context extracted from valid token claims.
     * @throws TokenValidationFailed If the token is invalid, expired, malformed, or missing required claims.
     */
    public function decode(string $token): AuthenticatedUser;
}
