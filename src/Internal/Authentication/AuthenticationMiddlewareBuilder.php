<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication;

use Skedli\HttpMiddleware\AuthenticationMiddleware;
use Skedli\HttpMiddleware\SigningAlgorithm;
use Skedli\HttpMiddleware\TokenDecoder;

final class AuthenticationMiddlewareBuilder
{
    private ?SigningAlgorithm $algorithm = null;
    private ?string $keyMaterial = null;
    private ?TokenDecoder $tokenDecoder = null;

    public function withAlgorithm(SigningAlgorithm $algorithm): AuthenticationMiddlewareBuilder
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    public function withKeyMaterial(string $keyMaterial): AuthenticationMiddlewareBuilder
    {
        $this->keyMaterial = $keyMaterial;
        return $this;
    }

    public function withTokenDecoder(TokenDecoder $tokenDecoder): AuthenticationMiddlewareBuilder
    {
        $this->tokenDecoder = $tokenDecoder;
        return $this;
    }

    public function build(): AuthenticationMiddleware
    {
        if (!is_null($this->tokenDecoder)) {
            return AuthenticationMiddleware::build(tokenDecoder: $this->tokenDecoder);
        }

        if (is_null($this->keyMaterial)) {
            $reason = 'A TokenDecoder instance or key material must be provided to build the AuthenticationMiddleware.';
            throw TokenValidationFailed::withReason(reason: $reason);
        }

        if (is_null($this->algorithm)) {
            $reason = 'A signing algorithm must be provided when using key material directly.';
            throw TokenValidationFailed::withReason(reason: $reason);
        }

        $tokenDecoder = new JwtTokenDecoder(algorithm: $this->algorithm, keyMaterial: $this->keyMaterial);

        return AuthenticationMiddleware::build(tokenDecoder: $tokenDecoder);
    }
}
