<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Firestore;

use EventSauce\EventSourcing\PaginationCursor;

final class FirestoreCursor implements PaginationCursor
{
    public function __construct(public readonly string $value)
    {
    }

    public function toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $cursor): static
    {
        return new static($cursor);
    }

    public function isAtStart(): bool
    {
        // TODO: Implement isAtStart() method.
        return false;
    }
}
