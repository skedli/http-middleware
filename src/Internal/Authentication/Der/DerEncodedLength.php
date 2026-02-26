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
        if ($size < 0x80) {
            return new DerEncodedLength(bytes: chr($size));
        }

        $sizeBytes = ltrim(pack('N', $size), "\x00");
        $lengthOfLength = strlen($sizeBytes);

        assert($lengthOfLength >= 1 && $lengthOfLength <= 4);

        $header = chr(0x80 | $lengthOfLength);

        return new DerEncodedLength(bytes: sprintf('%s%s', $header, $sizeBytes));
    }

    public function toBytes(): string
    {
        return $this->bytes;
    }
}
