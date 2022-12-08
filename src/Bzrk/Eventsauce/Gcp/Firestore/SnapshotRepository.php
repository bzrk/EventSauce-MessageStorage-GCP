<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Firestore;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use EventSauce\EventSourcing\Snapshotting\SnapshotRepository as ISnapshotRepository;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\Query;
use Ramsey\Uuid\Uuid;

class SnapshotRepository implements ISnapshotRepository
{
    public function __construct(
        private readonly FirestoreClient $client,
        private readonly string $collection,
    ) {
    }

    // @phpstan-ignore-next-line
    public function persist(Snapshot $snapshot): void
    {
        $this->client
            ->collection($this->collection)
            ->document(Uuid::uuid4()->toString())
            ->set([
                'aggregateId' => $snapshot->aggregateRootId()->toString(),
                'version' => $snapshot->aggregateRootVersion(),
                'state' => $snapshot->state()
            ]);
    }

    // @phpstan-ignore-next-line
    public function retrieve(AggregateRootId $id): ?Snapshot
    {
        $documents = $this->client
            ->collection($this->collection)
            ->where('aggregateId', '=', $id->toString())
            ->orderBy('version', Query::DIR_DESCENDING)
            ->limit(1)
            ->documents();

        /** @var DocumentSnapshot $document */
        foreach ($documents as $document) {
            $data = $document->data();
            return new Snapshot(
                $id,
                $data['version'],
                $data['state'],
            );
        }

        return null;
    }
}
