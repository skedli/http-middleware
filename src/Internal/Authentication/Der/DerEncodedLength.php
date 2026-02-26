<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Der;

final readonly class DerEncodedLength
{
    private function __construct(private string $bytes)
    {
    }

    public static function fromContentSize(int $size): DerEncodedLength
    {
        if ($size >= 0 && $size < 0x80) {
            return new DerEncodedLength(bytes: chr($size));
        }

        $sizeBytes = ltrim(pack('N', $size), "\x00");
        $header = chr((0x80 | strlen($sizeBytes)) & 0xFF);

        return new DerEncodedLength(bytes: sprintf('%s%s', $header, $sizeBytes));
    }

    public function toBytes(): string
    {
        return $this->bytes;
    }
}
