<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Firestore;

use Bzrk\Eventsauce\Gcp\Cursor;
use Bzrk\Eventsauce\Gcp\Firestore\DocumentBuilder as FirestoreDocumentBuilder;
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
use Generator;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\QuerySnapshot;
use Google\Cloud\Firestore\Transaction;

class MessageRepository implements IMessageRepository
{
    private FirestoreDocumentBuilder $builder;

    public function __construct(
        private readonly FirestoreClient $client,
        private readonly string $collection,
        MessageSerializer $serializer
    ) {
        $this->builder = new FirestoreDocumentBuilder($serializer);
    }

    /**
     * @throws StreamException
     */
    public function persist(Message ...$messages): void
    {
        Streams::of($messages)
            ->map(fn(Message $msg) => $this->builder->toDocument($msg))
            ->each(fn(Document $doc) => $this->store($doc));
    }

    private function store(Document $doc): void
    {
        $this->client->runTransaction(function (Transaction $transaction) use ($doc) {
            $collection = $this->client->collection($this->collection);

            $docCount = $collection->where(InternalDocumentBuilder::AGGREGATE_ID, '=', $doc->aggregateId)
                ->where(InternalDocumentBuilder::VERSION, '=', $doc->version())
                ->documents()->size();

            if ($docCount == 0) {
                $fireDoc = $collection->document($doc->eventId);
                $transaction->set($fireDoc, $doc->payload);
                return true;
            }

            throw new VersionConstraintException("AggregateId: $doc->aggregateId, version: {$doc->version()}");
        });
    }

    /**
     * @throws StreamException
     */
    public function retrieveAll(AggregateRootId $id): Generator
    {
        return $this->map(
            $this->client->collection($this->collection)
                ->where(InternalDocumentBuilder::AGGREGATE_ID, '=', $id->toString())
                ->orderBy(InternalDocumentBuilder::VERSION)
                ->documents()
        );
    }

    /**
     * @throws StreamException
     */
    public function retrieveAllAfterVersion(AggregateRootId $id, int $aggregateRootVersion): Generator
    {
        return $this->map(
            $this->client->collection($this->collection)
                ->where(InternalDocumentBuilder::AGGREGATE_ID, '=', $id->toString())
                ->where(InternalDocumentBuilder::VERSION, '>', $aggregateRootVersion)
                ->orderBy(InternalDocumentBuilder::VERSION)
                ->documents()
        );
    }

    /**
     * @throws StreamException
     */
    private function map(QuerySnapshot $snapshot): Generator
    {
        return Streams::of($snapshot->getIterator())
            ->map(fn(DocumentSnapshot $snapshot) => $this->builder->fromDocumentSnapshot($snapshot))
            ->map(fn(Document $doc) => $this->builder->fromDocument($doc))
            ->toGenerator(fn(Message $msg) => $msg->aggregateVersion());
    }

    /**
     * @throws StreamException
     */
    public function paginate(PaginationCursor $cursor): Generator
    {
        $snapshot = $this->client->collection($this->collection)
            ->where(InternalDocumentBuilder::TIMESTAMP, '>', $cursor->toString())
            ->orderBy(InternalDocumentBuilder::TIMESTAMP)
            ->limit(1000)
            ->documents();

        return Streams::of($snapshot->getIterator())
            ->map(fn(DocumentSnapshot $snapshot) => $this->builder->fromDocumentSnapshot($snapshot))
            ->map(fn(Document $doc) => $this->builder->fromDocument($doc))
            ->toGenerator(
                fn(Message $msg) => Cursor::fromString($msg->header(InternalDocumentBuilder::TIMESTAMP)),
                Cursor::fromString($cursor->toString())
            );
    }
}
