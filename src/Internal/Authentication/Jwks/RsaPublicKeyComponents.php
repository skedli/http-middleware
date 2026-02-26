<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Jwks;

use Skedli\HttpMiddleware\Internal\Authentication\TokenValidationFailed;

final readonly class RsaPublicKeyComponents
{
    private function __construct(private string $modulus, private string $exponent)
    {
    }

    public static function fromJwks(array $jwks): RsaPublicKeyComponents
    {
        $key = $jwks['keys'][0];

        if (!isset($key['n'], $key['e'])) {
            throw TokenValidationFailed::withReason(
                reason: 'JWKS response does not contain a valid RSA key (missing n or e).'
            );
        }

        $modulus = sodium_base642bin($key['n'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $exponent = sodium_base642bin($key['e'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        return new RsaPublicKeyComponents(modulus: $modulus, exponent: $exponent);
    }

    public function modulus(): string
    {
        return $this->modulus;
    }

    public function exponent(): string
    {
        return $this->exponent;
    }
}
