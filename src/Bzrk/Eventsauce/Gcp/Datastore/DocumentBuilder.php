<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Datastore;

use Bzrk\Eventsauce\Gcp\Internal\Document;
use Bzrk\Eventsauce\Gcp\Internal\DocumentBuilder as AbstractDocumentBuilder;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use Google\Cloud\Datastore\Entity;

class DocumentBuilder extends AbstractDocumentBuilder
{
    /**
    * @param array<mixed> $payload
    * @return string
    */
    protected function generateKey(array $payload): string
    {
        return sprintf(
            '%s::%s',
            $payload[self::HEADERS][Header::AGGREGATE_ROOT_ID],
            $payload[self::HEADERS][Header::AGGREGATE_ROOT_VERSION]
        );
    }

    public function toDocument(Message $msg): Document
    {
        $doc = parent::toDocument($msg);
        $payload = $doc->payload;

        $payload[self::PAYLOAD] = json_encode($doc->payload[self::PAYLOAD]);
        $payload[self::HEADERS] = json_encode($doc->payload[self::HEADERS]);

        return new Document(
            $doc->aggregateId,
            $doc->eventId,
            $payload
        );
    }


    public function fromEntity(Entity $entity): Document
    {
        $data = $entity->get();
        $data[self::PAYLOAD] = json_decode($data[self::PAYLOAD], true);
        $data[self::HEADERS] = json_decode($data[self::HEADERS], true);

        $aggregateId = $data[self::AGGREGATE_ID];
        return new Document($aggregateId, $entity->key()->pathEndIdentifier(), $data);
    }
}
