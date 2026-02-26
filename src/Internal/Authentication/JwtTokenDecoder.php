<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Skedli\HttpMiddleware\AuthenticatedUser;
use Skedli\HttpMiddleware\SigningAlgorithm;
use Skedli\HttpMiddleware\TokenDecoder;
use Throwable;

final readonly class JwtTokenDecoder implements TokenDecoder
{
    public function __construct(private SigningAlgorithm $algorithm, private string $keyMaterial)
    {
    }

    public function decode(string $token): AuthenticatedUser
    {
        try {
            $payload = (array)JWT::decode(
                jwt: $token,
                keyOrKeyArray: new Key(
                    keyMaterial: $this->keyMaterial,
                    algorithm: $this->algorithm->value
                )
            );

            $subject = ($payload['sub'] ?? null);
            $issuedAt = ($payload['iat'] ?? null);
            $expiration = ($payload['exp'] ?? null);

            if (empty($subject)) {
                throw TokenValidationFailed::withReason(reason: 'Token is missing the subject (sub) claim.');
            }

            return JwtAuthenticatedUser::from(
                userId: (string)$subject,
                issuedAt: (int)$issuedAt,
                expiresAt: (int)$expiration
            );
        } catch (ExpiredException $exception) {
            throw TokenValidationFailed::withReason(reason: 'Token has expired.', previous: $exception);
        } catch (TokenValidationFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw TokenValidationFailed::withReason(
                reason: 'Token is invalid or could not be decoded.',
                previous: $exception
            );
        }
    }
}
