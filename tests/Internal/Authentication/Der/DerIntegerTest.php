<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Internal\Authentication\Der;

use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\Internal\Authentication\Der\DerInteger;

final class DerIntegerTest extends TestCase
{
    public function testDoesNotPadWhenHighBitIsNotSet(): void
    {
        /** @Given a byte with value 0x7F (high bit not set) */
        $result = DerInteger::fromUnsignedBytes(bytes: "\x7F");

        /** @Then the output should be TAG + length 1 + 0x7F (no padding byte) */
        self::assertSame("\x02\x01\x7F", $result->toBytes());
    }

    public function testDoesNotPadSmallPositiveValue(): void
    {
        /** @Given a byte with value 0x01 (high bit not set) */
        $result = DerInteger::fromUnsignedBytes(bytes: "\x01");

        /** @Then the output should be TAG + length 1 + 0x01 (no padding byte) */
        self::assertSame("\x02\x01\x01", $result->toBytes());
    }

    public function testStripsLeadingZeroBytes(): void
    {
        /** @Given a byte string with leading zero bytes followed by a small value */
        $withLeadingZeros = "\x00\x00\x05";

        /** @When encoding as a DER integer */
        $result = DerInteger::fromUnsignedBytes(bytes: $withLeadingZeros);

        /** @Then the output should be TAG + length 1 + 0x05 (zeros stripped) */
        self::assertSame("\x02\x01\x05", $result->toBytes());
    }

    public function testPadsWhenHighBitIsSet(): void
    {
        /** @Given a byte with value 0x80 (high bit set, must be padded) */
        $result = DerInteger::fromUnsignedBytes(bytes: "\x80");

        /** @Then the output should have a 0x00 padding byte before 0x80 */
        self::assertSame("\x02\x02\x00\x80", $result->toBytes());
    }
}
