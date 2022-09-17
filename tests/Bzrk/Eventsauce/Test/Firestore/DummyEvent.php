<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Test\Firestore;

use EventSauce\EventSourcing\Serialization\SerializablePayload;

final class DummyEvent implements SerializablePayload
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(public readonly array $data)
    {
    }

    /**
     * @return array<mixed>
     */
    public function toPayload(): array
    {
        return $this->data;
    }

    /**
     * @param array<mixed> $payload
     * @return static
     */
    public static function fromPayload(array $payload): static
    {
        return new static($payload);
    }
}
