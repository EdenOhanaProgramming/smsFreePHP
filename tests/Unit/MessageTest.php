<?php

declare(strict_types=1);

namespace EdenOhana\SmsFree\Tests\Unit;

use EdenOhana\SmsFree\Exception\InvalidArgumentException;
use EdenOhana\SmsFree\Message;
use EdenOhana\SmsFree\SmsEncoding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
#[CoversClass(SmsEncoding::class)]
final class MessageTest extends TestCase
{
    public function testItRejectsAnEmptyBody(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Message::of('   ');
    }

    public function testItRejectsBrokenUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Message::of("\xB1\x31");
    }

    public function testItCountsCharactersNotBytes(): void
    {
        // Nine characters as a human counts them, seventeen bytes on the wire.
        $message = Message::of('שלום עולם');

        self::assertSame(9, $message->length());
        self::assertSame(17, \strlen($message->text()));
    }

    /**
     * The 1.x implementation cut the body with `substr()`. On a Hebrew message
     * that slices a two-byte character in half and the recipient gets a
     * replacement glyph at the end. This is the regression test for that.
     */
    public function testTruncationNeverSplitsAMultibyteCharacter(): void
    {
        $message = Message::of(str_repeat('א', 200))->truncateTo(134);

        self::assertSame(134, $message->length());
        self::assertTrue(mb_check_encoding($message->text(), 'UTF-8'));
        self::assertTrue($message->isTruncated());
    }

    public function testTruncationIsANoOpForAShortMessage(): void
    {
        $message = Message::of('short');

        self::assertSame($message, $message->truncateTo(134));
        self::assertFalse($message->isTruncated());
    }

    public function testTruncationRejectsANonPositiveLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Message::of('anything')->truncateTo(0);
    }

    public function testItDetectsTheAlphabetTheMessageNeeds(): void
    {
        self::assertSame(SmsEncoding::Gsm7, Message::of('Your code is 123456')->encoding());
        self::assertSame(SmsEncoding::Ucs2, Message::of('הקוד שלך הוא 123456')->encoding());
        self::assertSame(SmsEncoding::Ucs2, Message::of('Nice work 🎉')->encoding());
    }

    public function testItCountsTheSmsPartsAMessageIsBilledAs(): void
    {
        self::assertSame(1, Message::of(str_repeat('a', 160))->parts());
        self::assertSame(2, Message::of(str_repeat('a', 161))->parts());

        // Hebrew travels as UCS-2, so only 70 characters fit in one part.
        self::assertSame(1, Message::of(str_repeat('א', 70))->parts());
        self::assertSame(2, Message::of(str_repeat('א', 71))->parts());
        self::assertSame(3, Message::of(str_repeat('א', 140))->parts());
    }

    public function testCharactersFromTheGsmExtensionTableCostTwoSeptets(): void
    {
        // 80 opening braces are escaped characters: 160 septets, still one part.
        self::assertSame(1, Message::of(str_repeat('{', 80))->parts());
        self::assertSame(2, Message::of(str_repeat('{', 81))->parts());
    }

    public function testItCastsToItsText(): void
    {
        self::assertSame('hello', (string) Message::of('hello'));
    }
}
