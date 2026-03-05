<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Jwks;

use Skedli\HttpMiddleware\Internal\HttpTimeout;

final readonly class JwksPublicKeyResolver
{
    private function __construct(private string $jwksUrl, private HttpTimeout $timeout)
    {
    }

    public static function from(string $jwksUrl, HttpTimeout $timeout): JwksPublicKeyResolver
    {
        return new JwksPublicKeyResolver(jwksUrl: $jwksUrl, timeout: $timeout);
    }

    public function resolve(): string
    {
        $jwks = JwksFetcher::using(timeout: $this->timeout)->fetchFrom(url: $this->jwksUrl);
        $components = RsaPublicKeyComponents::fromJwks(jwks: $jwks);

        return RsaPublicKeyPemEncoder::encode(components: $components);
    }
}
