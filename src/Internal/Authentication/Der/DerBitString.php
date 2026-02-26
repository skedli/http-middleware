<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Der;

final readonly class DerBitString
{
    private const string TAG = "\x03";
    private const string NO_UNUSED_BITS = "\x00";

    private function __construct(private string $encoded)
    {
    }

    public static function fromContent(string $content): DerBitString
    {
        $payload = sprintf('%s%s', self::NO_UNUSED_BITS, $content);
        $length = DerEncodedLength::fromContentSize(size: strlen($payload));
        $encoded = sprintf('%s%s%s', self::TAG, $length->toBytes(), $payload);

        return new DerBitString(encoded: $encoded);
    }

    public function toBytes(): string
    {
        return $this->encoded;
    }
}
