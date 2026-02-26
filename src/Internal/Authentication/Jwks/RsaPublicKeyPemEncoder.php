<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Jwks;

use Skedli\HttpMiddleware\Internal\Authentication\Der\DerBitString;
use Skedli\HttpMiddleware\Internal\Authentication\Der\DerInteger;
use Skedli\HttpMiddleware\Internal\Authentication\Der\DerSequence;

final readonly class RsaPublicKeyPemEncoder
{
    private const string RSA_ALGORITHM_IDENTIFIER = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

    private const string PEM_HEADER = "-----BEGIN PUBLIC KEY-----";
    private const string PEM_FOOTER = "-----END PUBLIC KEY-----";
    private const int PEM_LINE_LENGTH = 64;

    public static function encode(RsaPublicKeyComponents $components): string
    {
        $modulus = DerInteger::fromUnsignedBytes(bytes: $components->modulus());
        $exponent = DerInteger::fromUnsignedBytes(bytes: $components->exponent());

        $rsaPublicKey = DerSequence::fromContent(
            content: $modulus->toBytes() . $exponent->toBytes()
        );

        $bitString = DerBitString::fromContent(content: $rsaPublicKey->toBytes());

        $subjectPublicKeyInfo = DerSequence::fromContent(
            content: self::RSA_ALGORITHM_IDENTIFIER . $bitString->toBytes()
        );

        $base64 = chunk_split(base64_encode($subjectPublicKeyInfo->toBytes()), self::PEM_LINE_LENGTH);

        return implode("\n", [self::PEM_HEADER, $base64 . self::PEM_FOOTER]);
    }
}
