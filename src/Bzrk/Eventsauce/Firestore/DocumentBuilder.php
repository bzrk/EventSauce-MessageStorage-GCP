<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Firestore;

use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use Google\Cloud\Firestore\DocumentSnapshot;
use Ramsey\Uuid\Uuid;

class DocumentBuilder
{
    public const VERSION      = 'version';
    public const EVENT        = 'event';
    public const AGGREGATE    = 'aggregate';
    public const AGGREGATE_ID = 'aggregateId';
    public const TIMESTAMP    = 'timestamp';

    public function __construct(private readonly MessageSerializer $serializer)
    {
    }

    public function toDocument(Message $msg): Document
    {
        $payload = $this->serializer->serializeMessage($msg);
        $payload['headers'][Header::EVENT_ID] ??= Uuid::uuid4()->toString();
        $payload[self::AGGREGATE] = $payload['headers'][Header::AGGREGATE_ROOT_TYPE];
        $payload[self::VERSION] = $payload['headers'][Header::AGGREGATE_ROOT_VERSION];
        $payload[self::EVENT] = $payload['headers'][Header::EVENT_TYPE];
        $payload[self::AGGREGATE_ID] = $payload['headers'][Header::AGGREGATE_ROOT_ID];
        $payload[self::TIMESTAMP] = $msg->timeOfRecording()->format('U.u');

        return new Document(
            $msg->aggregateRootId()->toString(),
            $payload['headers'][Header::EVENT_ID],
            $payload
        );
    }

    public function fromDocument(Document $document): Message
    {
        return $this->serializer->unserializePayload($document->payload);
    }

    public function fromDocumentSnapshot(DocumentSnapshot $snapshot): Document
    {
        $data = $snapshot->data();
        $aggregateId = $data[self::AGGREGATE_ID];
        return new Document($aggregateId, $snapshot->id(), $data);
    }
}
