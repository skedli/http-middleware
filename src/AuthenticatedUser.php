<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

/**
 * Represents the authenticated user context extracted from a valid access token.
 *
 * This contract is propagated through the request pipeline as a request attribute,
 * enabling downstream handlers to identify the caller without consulting external databases.
 *
 * Implementations may extend this contract with additional claims (e.g., roles, tenant, email)
 * by providing a custom TokenDecoder that returns a richer implementation.
 */
interface AuthenticatedUser
{
    /**
     * Retrieves the unique identifier of the authenticated user, as extracted from the token's "sub" claim.
     *
     * @return string The user ID associated with the authenticated user.
     */
    public function userId(): string;

    /**
     * Retrieves the timestamp (in seconds since the Unix epoch) when the token was issued,
     * as extracted from the "iat" claim.
     *
     * @return int The issued-at timestamp of the token.
     */
    public function issuedAt(): int;

    /**
     * Retrieves the timestamp (in seconds since the Unix epoch) when the token expires,
     * as extracted from the "exp" claim.
     *
     * @return int The expiration timestamp of the token.
     */
    public function expiresAt(): int;
}
