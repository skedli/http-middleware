<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Jwks;

use Skedli\HttpMiddleware\AuthenticatedUser;
use Skedli\HttpMiddleware\Internal\Authentication\JwtTokenDecoder;
use Skedli\HttpMiddleware\Internal\HttpTimeout;
use Skedli\HttpMiddleware\SigningAlgorithm;
use Skedli\HttpMiddleware\TokenDecoder;

final class LazyJwksTokenDecoder implements TokenDecoder
{
    private ?TokenDecoder $resolved = null;

    public function __construct(private readonly string $jwksUrl, private readonly HttpTimeout $timeout)
    {
    }

    public function decode(string $token): AuthenticatedUser
    {
        $this->resolved ??= $this->resolveDecoder();

        return $this->resolved->decode(token: $token);
    }

    private function resolveDecoder(): JwtTokenDecoder
    {
        $keyMaterial = JwksPublicKeyResolver::from(jwksUrl: $this->jwksUrl, timeout: $this->timeout)->resolve();

        return new JwtTokenDecoder(algorithm: SigningAlgorithm::RS256, keyMaterial: $keyMaterial);
    }
}
