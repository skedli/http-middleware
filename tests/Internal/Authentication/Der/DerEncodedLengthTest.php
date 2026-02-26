<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal\Authentication\Der;

use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\Internal\Authentication\Der\DerEncodedLength;

final class DerEncodedLengthTest extends TestCase
{
    public function testEncodesShortFormForSizeBelowBoundary(): void
    {
        /** @Given a size of 127 (0x7F), the largest short-form value */
        $result = DerEncodedLength::fromContentSize(size: 127);

        /** @Then the output should be a single byte 0x7F */
        self::assertSame("\x7F", $result->toBytes());
    }

    public function testEncodesLongFormForSizeAtBoundary(): void
    {
        /** @Given a size of exactly 128 (0x80), the first long-form value */
        $result = DerEncodedLength::fromContentSize(size: 128);

        /** @Then the output should be 0x81 (1 length byte follows) + 0x80 */
        self::assertSame("\x81\x80", $result->toBytes());
    }

    public function testEncodesShortFormForSizeZero(): void
    {
        /** @Given a size of 0 */
        $result = DerEncodedLength::fromContentSize(size: 0);

        /** @Then the output should be a single byte 0x00 */
        self::assertSame("\x00", $result->toBytes());
    }
}
