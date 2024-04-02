<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CursorTest extends TestCase
{
    #[Test]
    public function fromStart(): void
    {
        $cursor = Cursor::fromStart();

        self::assertEquals('0', $cursor->toString());
        self::assertTrue($cursor->isAtStart());
    }

    #[Test]
    public function fromString(): void
    {
        $cursor = Cursor::fromString("foobar");

        self::assertEquals('foobar', $cursor->toString());
        self::assertFalse($cursor->isAtStart());
    }
}
