<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication\Der;

final readonly class DerSequence
{
    private const string TAG = "\x30";

    private function __construct(private string $encoded)
    {
    }

    public static function fromContent(string $content): DerSequence
    {
        $length = DerEncodedLength::fromContentSize(size: strlen($content));
        $encoded = sprintf('%s%s%s', self::TAG, $length->toBytes(), $content);

        return new DerSequence(encoded: $encoded);
    }

    public function toBytes(): string
    {
        return $this->encoded;
    }
}
