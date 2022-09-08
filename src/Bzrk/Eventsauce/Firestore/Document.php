<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Firestore;

class Document
{
    /**
     * @param string $aggregateId
     * @param string $eventId
     * @param array<mixed> $payload
     */
    public function __construct(
        public readonly string $aggregateId,
        public readonly string $eventId,
        public readonly array $payload,
    ) {
    }

    public function version(): int
    {
        return (int) $this->payload[DocumentBuilder::VERSION];
    }
}
