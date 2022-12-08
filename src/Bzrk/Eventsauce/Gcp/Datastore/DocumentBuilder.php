<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Datastore;

use Bzrk\Eventsauce\Gcp\Internal\Document;
use Bzrk\Eventsauce\Gcp\Internal\DocumentBuilder as AbstractDocumentBuilder;
use BZRK\PHPStream\StreamException;
use BZRK\PHPStream\Streams;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use Google\Cloud\Datastore\Entity;
use Webmozart\Assert\Assert;

class DocumentBuilder extends AbstractDocumentBuilder
{
    private ?string $prefix = null;

    /**
     * @param string|null $prefix
     */
    public function __construct(MessageSerializer $serializer, ?string $prefix = null)
    {
        parent::__construct($serializer);

        if ($prefix != null) {
            Assert::notWhitespaceOnly($prefix);
            $this->prefix = $prefix;
        }
    }


    /**
     * @param array<mixed> $payload
     * @return string
     * @throws StreamException
     */
    protected function generateKey(array $payload): string
    {
        return Streams::of(
            [
                $this->prefix,
                $payload['headers'][Header::AGGREGATE_ROOT_ID],
                $payload['headers'][Header::AGGREGATE_ROOT_VERSION]
            ]
        )->filter(fn($item) => !is_null($item))->implode('::');
    }

    public function fromEntity(Entity $entity): Document
    {
        $data = $entity->get();
        $aggregateId = $data[self::AGGREGATE_ID];
        return new Document($aggregateId, $entity->key()->pathEndIdentifier(), $data);
    }
}
