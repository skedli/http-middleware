<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Jwks;

final readonly class JwksPublicKeyResolver
{
    private function __construct(private string $jwksUrl)
    {
    }

    public static function from(string $jwksUrl): JwksPublicKeyResolver
    {
        return new JwksPublicKeyResolver(jwksUrl: $jwksUrl);
    }

    public function resolve(): string
    {
        $jwks = JwksFetcher::fetchFrom(url: $this->jwksUrl);
        $components = RsaPublicKeyComponents::fromJwks(jwks: $jwks);

        return RsaPublicKeyPemEncoder::encode(components: $components);
    }
}
