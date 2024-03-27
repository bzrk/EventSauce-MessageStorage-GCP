<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Datastore;

use Bzrk\Eventsauce\Gcp\Cursor;
use Bzrk\Eventsauce\Gcp\Internal\DocumentBuilder as InternalDocumentBuilder;
use Bzrk\Eventsauce\Gcp\VersionConstraintException;
use Bzrk\Eventsauce\Gcp\Internal\Document;
use BZRK\PHPStream\StreamException;
use BZRK\PHPStream\Streams;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageRepository as IMessageRepository;
use EventSauce\EventSourcing\PaginationCursor;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use Exception;
use Generator;
use Google\Cloud\Core\Exception\ConflictException;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Entity;
use Google\Cloud\Datastore\EntityIterator;
use Google\Cloud\Datastore\Query\Query;

class MessageRepository implements IMessageRepository
{
    private DocumentBuilder $builder;

    public function __construct(
        private readonly DatastoreClient $client,
        private readonly string $collection,
        MessageSerializer $serializer
    ) {
        $this->builder = new DocumentBuilder($serializer);
    }

    /**
     * @throws StreamException|VersionConstraintException
     */
    public function persist(Message ...$messages): void
    {
        Streams::of($messages)
            ->map(fn(Message $msg) => $this->builder->toDocument($msg))
            ->each(fn(Document $doc) => $this->store($doc));
    }

    /**
     * @throws VersionConstraintException
     */
    private function store(Document $doc): void
    {
        $key = $this->client->key($this->collection, $doc->eventId);
        $entity = $this->client->entity(
            $key,
            $doc->payload,
            ['excludeFromIndexes' => [InternalDocumentBuilder::PAYLOAD, InternalDocumentBuilder::HEADERS]]
        );
        try {
            $this->client->insert($entity);
        } catch (Exception $exception) {
            if ($exception instanceof ConflictException) {
                throw new VersionConstraintException("AggregateId: $doc->eventId", 0, $exception);
            }
            throw $exception;
        }
    }

    /**
     * @throws StreamException
     */
    public function retrieveAll(AggregateRootId $id): Generator
    {
        $query = $this->client->query()
            ->kind($this->collection)
            ->filter(InternalDocumentBuilder::AGGREGATE_ID, '=', $id->toString())
            ->order(InternalDocumentBuilder::VERSION, Query::ORDER_ASCENDING);
        return $this->map($this->client->runQuery($query));
    }

    /**
     * @throws StreamException
     */
    public function retrieveAllAfterVersion(AggregateRootId $id, int $aggregateRootVersion): Generator
    {
        $query = $this->client->query()
            ->kind($this->collection)
            ->filter(InternalDocumentBuilder::AGGREGATE_ID, '=', $id->toString())
            ->filter(InternalDocumentBuilder::VERSION, '>', $aggregateRootVersion)
            ->order(InternalDocumentBuilder::VERSION, Query::ORDER_ASCENDING);
        return $this->map($this->client->runQuery($query));
    }

    /**
     * @throws StreamException
     */
    private function map(EntityIterator $entities): Generator
    {
        return Streams::of($entities)
            ->map(fn(Entity $entity) => $this->builder->fromEntity($entity))
            ->map(fn(Document $doc) => $this->builder->fromDocument($doc))
            ->toGenerator(fn(Message $msg) => $msg->aggregateVersion());
    }

    /**
     * @throws StreamException
     */
    public function paginate(PaginationCursor $cursor): Generator
    {
        $query = $this->client->query()
            ->kind($this->collection)
            ->filter(InternalDocumentBuilder::TIMESTAMP, '>', $cursor->toString())
            ->order(InternalDocumentBuilder::TIMESTAMP, Query::ORDER_ASCENDING);

        return Streams::of($this->client->runQuery($query))
            ->map(fn(Entity $entity) => $this->builder->fromEntity($entity))
            ->map(fn(Document $doc) => $this->builder->fromDocument($doc))
            ->toGenerator(
                fn(Message $msg) => Cursor::fromString($msg->header(InternalDocumentBuilder::TIMESTAMP)),
                Cursor::fromString($cursor->toString())
            );
    }
}
