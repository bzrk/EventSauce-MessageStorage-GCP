<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Datastore;

use Bzrk\Eventsauce\Gcp\Internal\Document;
use Bzrk\Eventsauce\Gcp\Internal\DocumentBuilder as AbstractDocumentBuilder;
use EventSauce\EventSourcing\Header;
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
            $payload['headers'][Header::AGGREGATE_ROOT_ID],
            $payload['headers'][Header::AGGREGATE_ROOT_VERSION]
        );
    }


    public function fromEntity(Entity $entity): Document
    {
        $data = $entity->get();
        $aggregateId = $data[self::AGGREGATE_ID];
        return new Document($aggregateId, $entity->key()->pathEndIdentifier(), $data);
    }
}
