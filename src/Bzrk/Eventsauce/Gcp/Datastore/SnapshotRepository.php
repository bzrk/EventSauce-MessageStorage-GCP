<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Datastore;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use EventSauce\EventSourcing\Snapshotting\SnapshotRepository as ISnapshotRepository;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Entity;
use Google\Cloud\Datastore\Query\Query;

class SnapshotRepository implements ISnapshotRepository
{
    public function __construct(
        private readonly DatastoreClient $client,
        private readonly string $collection,
    ) {
    }

    public function persist(Snapshot $snapshot): void
    {
        $this->client->insert(
            $this->client->entity(
                $this->client->key(
                    $this->collection,
                    "{$snapshot->aggregateRootId()->toString()}::{$snapshot->aggregateRootVersion()}"
                ),
                [
                    'aggregateId' => $snapshot->aggregateRootId()->toString(),
                    'version' => $snapshot->aggregateRootVersion(),
                    'state' => $snapshot->state()
                ]
            )
        );
    }

    public function retrieve(AggregateRootId $id): ?Snapshot
    {
        $query = $this->client->query()
            ->kind($this->collection)
            ->filter('aggregateId', '=', $id->toString())
            ->order('version', Query::ORDER_DESCENDING)
            ->limit(1);

        $entities = $this->client->runQuery($query);

        /** @var Entity $entity */
        foreach ($entities as $entity) {
            return new Snapshot(
                $id,
                $entity->getProperty('version'),
                $entity->getProperty('state')
            );
        }

        return null;
    }
}
