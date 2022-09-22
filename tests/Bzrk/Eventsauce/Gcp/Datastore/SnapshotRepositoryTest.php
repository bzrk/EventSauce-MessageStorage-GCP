<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Datastore;

use Bzrk\Eventsauce\Test\Firestore\DummyId;
use BZRK\PHPStream\StreamException;
use BZRK\PHPStream\Streams;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Entity;
use PHPUnit\Framework\TestCase;

class SnapshotRepositoryTest extends TestCase
{
    private const COLLECTION = "snapshots";

    private DatastoreClient $datastoreClient;

    private SnapshotRepository $snapshotRepository;

    /**
     * @throws StreamException
     */
    protected function setUp(): void
    {
        $this->datastoreClient = new DatastoreClient();
        Streams::of($this->datastoreClient->runQuery($this->datastoreClient->query()))->each(
            fn(Entity $entity) => $this->datastoreClient->delete($entity->key())
        );

        $this->snapshotRepository = new SnapshotRepository(
            $this->datastoreClient,
            self::COLLECTION
        );
    }

    /**
     * @throws StreamException
     */
    public function testPersist(): void
    {
        $state = ['state' => 'state', 'foo' => 'bar'];

        $snapShot = new Snapshot(
            new DummyId('1-1-1-1'),
            10,
            $state
        );
        $this->snapshotRepository->persist($snapShot);

        /** @var Entity[] $entities */
        $entities = Streams::of($this->datastoreClient->runQuery($this->datastoreClient->query()))->toList();

        self::assertCount(1, $entities);
        self::assertEquals('1-1-1-1::10', $entities[0]->key()->pathEndIdentifier());
        self::assertEquals(10, $entities[0]->get()['version']);
        self::assertEquals('1-1-1-1', $entities[0]->get()['aggregateId']);
        self::assertEquals($state, $entities[0]->get()['state']);
    }

    public function testRetrieveNotFoundASnapshot(): void
    {
        Streams::range(1, 2)->each(
            fn(int $cnt) => $this->datastoreClient->insert(
                $this->datastoreClient->entity(
                    $this->datastoreClient->key(self::COLLECTION, "1-1-1-2::$cnt"),
                    [
                        'version' => $cnt,
                        'aggregateId' => '1-1-1-2',
                        'state' => ['v' => $cnt]
                    ]
                )
            )
        );

        self::assertNull($this->snapshotRepository->retrieve(new DummyId('1-1-1-1')));
    }

    public function testRetrieve(): void
    {
        Streams::range(1, 5)->each(
            fn(int $cnt) => $this->datastoreClient->insert(
                $this->datastoreClient->entity(
                    $this->datastoreClient->key(self::COLLECTION, "1-1-1-1::$cnt"),
                    [
                        'version' => $cnt,
                        'aggregateId' => '1-1-1-1',
                        'state' => ['v' => $cnt]
                    ]
                )
            )
        );

        $snapShot = $this->snapshotRepository->retrieve(new DummyId('1-1-1-1'));

        self::assertInstanceOf(DummyId::class, $snapShot->aggregateRootId());
        self::assertEquals('1-1-1-1', $snapShot->aggregateRootId()->toString());
        self::assertEquals(5, $snapShot->aggregateRootVersion());
        self::assertEquals(['v' => 5], $snapShot->state());
    }
}
