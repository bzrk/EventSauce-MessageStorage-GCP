<?php declare(strict_types=1);

namespace Bzrk\Eventsauce\Firestore;

class Document
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly string $eventId,
        public readonly array $payload,
    )
    {
    }

    public function version() : int {
        return (int) $this->payload[DocumentBuilder::VERSION];
    }
}