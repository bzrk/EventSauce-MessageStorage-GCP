<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp;

use PHPUnit\Framework\TestCase;

class CursorTest extends TestCase
{
    public function testFromStart(): void
    {
        $cursor = Cursor::fromStart();

        self::assertEquals('0', $cursor->toString());
        self::assertTrue($cursor->isAtStart());
    }

    public function testFromString(): void
    {
        $cursor = Cursor::fromString("foobar");

        self::assertEquals('foobar', $cursor->toString());
        self::assertFalse($cursor->isAtStart());
    }
}
