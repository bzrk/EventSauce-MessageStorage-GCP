<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Firestore;

use PHPUnit\Framework\TestCase;

class FirestoreCursorTest extends TestCase
{
    public function testFromStart(): void
    {
        $cursor = FirestoreCursor::fromStart();

        self::assertEquals('', $cursor->toString());
        self::assertTrue($cursor->isAtStart());
    }

    public function testFromString(): void
    {
        $cursor = FirestoreCursor::fromString("foobar");

        self::assertEquals('foobar', $cursor->toString());
        self::assertFalse($cursor->isAtStart());
    }
}
