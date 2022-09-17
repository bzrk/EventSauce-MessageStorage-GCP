<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Test\Firestore;

use EventSauce\EventSourcing\AggregateRootId;

final class DummyId implements AggregateRootId
{
    public function __construct(public readonly string $id)
    {
    }

    public function toString(): string
    {
        return $this->id;
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static($aggregateRootId);
    }
}
