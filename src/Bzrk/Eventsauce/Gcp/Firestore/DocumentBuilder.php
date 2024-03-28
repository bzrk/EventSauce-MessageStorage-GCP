<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Firestore;

use Bzrk\Eventsauce\Gcp\Internal\Document;
use Bzrk\Eventsauce\Gcp\Internal\DocumentBuilder as AbstractDocumentBuilder;
use EventSauce\EventSourcing\Header;
use Google\Cloud\Firestore\DocumentSnapshot;
use Ramsey\Uuid\Uuid;

class DocumentBuilder extends AbstractDocumentBuilder
{
    /**
     * @param array<mixed> $payload
     * @return string
     */
    protected function generateKey(array $payload): string
    {
        return $payload[self::HEADERS][Header::EVENT_ID] ?? Uuid::uuid4()->toString();
    }

    public function fromDocumentSnapshot(DocumentSnapshot $snapshot): Document
    {
        $data = $snapshot->data();
        $aggregateId = $data[self::AGGREGATE_ID];
        return new Document($aggregateId, $snapshot->id(), $data);
    }
}
