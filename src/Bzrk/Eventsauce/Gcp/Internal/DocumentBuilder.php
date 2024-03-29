<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Internal;

use BZRK\PHPStream\Streams;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\MessageSerializer;

abstract class DocumentBuilder
{
    public const VERSION      = 'version';
    public const EVENT        = 'event';
    public const AGGREGATE    = 'aggregate';
    public const AGGREGATE_ID = 'aggregateId';
    public const TIMESTAMP    = 'timestamp';
    public const PAYLOAD    = 'payload';
    public const HEADERS    = 'headers';

    public function __construct(private readonly MessageSerializer $serializer)
    {
    }

    public function toDocument(Message $msg): Document
    {
        $payload = $this->serializer->serializeMessage($msg);

        $payload[self::PAYLOAD] = Streams::of($payload[self::PAYLOAD])
            ->filter(fn($var) => !is_null($var))
            ->toList(true);

        $payload[self::HEADERS][Header::EVENT_ID] = $this->generateKey($payload);
        $payload[self::HEADERS][self::TIMESTAMP] = $msg->timeOfRecording()->format('U.u');
        $payload[self::AGGREGATE] = $payload[self::HEADERS][Header::AGGREGATE_ROOT_TYPE];
        $payload[self::VERSION] = $payload[self::HEADERS][Header::AGGREGATE_ROOT_VERSION];
        $payload[self::EVENT] = $payload[self::HEADERS][Header::EVENT_TYPE];
        $payload[self::AGGREGATE_ID] = $payload[self::HEADERS][Header::AGGREGATE_ROOT_ID];
        $payload[self::TIMESTAMP] = $payload[self::HEADERS][self::TIMESTAMP];

        return new Document(
            $msg->aggregateRootId()->toString(),
            $payload[self::HEADERS][Header::EVENT_ID],
            $payload
        );
    }

    /**
     * @param array<mixed> $payload
     * @return string
     */
    abstract protected function generateKey(array $payload): string;

    public function fromDocument(Document $document): Message
    {
        return $this->serializer->unserializePayload($document->payload);
    }
}
