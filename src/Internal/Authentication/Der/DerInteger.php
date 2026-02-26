<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Der;

final readonly class DerInteger
{
    private const string TAG = "\x02";

    private function __construct(private string $encoded)
    {
    }

    public static function fromUnsignedBytes(string $bytes): DerInteger
    {
        $bytes = ltrim($bytes, "\x00");
        $needsPadding = (ord($bytes[0]) & 0x80) !== 0;

        if ($needsPadding) {
            $bytes = sprintf("\x00%s", $bytes);
        }

        $length = DerEncodedLength::fromContentSize(size: strlen($bytes));
        $encoded = sprintf('%s%s%s', self::TAG, $length->toBytes(), $bytes);

        return new DerInteger(encoded: $encoded);
    }

    public function toBytes(): string
    {
        return $this->encoded;
    }
}
