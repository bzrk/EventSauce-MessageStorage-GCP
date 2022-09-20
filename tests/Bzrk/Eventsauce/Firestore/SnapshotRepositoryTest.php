<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Firestore;

use Bzrk\Eventsauce\Test\Firestore\DummyId;
use BZRK\PHPStream\StreamException;
use BZRK\PHPStream\Streams;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;

class SnapshotRepositoryTest extends TestCase
{
    private const COLLECTION = "snapshots";

    private CollectionReference $collectionReference;
    private SnapshotRepository $snapshotRepository;

    /**
     * @before
     * @throws StreamException
     */
    protected function setUp(): void
    {
        $firestoreClient = new FirestoreClient();
        $this->collectionReference = $firestoreClient->collection(self::COLLECTION);
        Streams::of($this->collectionReference->listDocuments())->each(
            fn(DocumentReference $doc) => $doc->delete()
        );

        $this->snapshotRepository = new SnapshotRepository(
            $firestoreClient,
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

        /** @var DocumentReference[] $docs */
        $docs = Streams::of($this->collectionReference->listDocuments())->toList();

        self::assertCount(1, $docs);
        self::assertEquals(10, $docs[0]->snapshot()->data()['version']);
        self::assertEquals('1-1-1-1', $docs[0]->snapshot()->data()['aggregateId']);
        self::assertEquals($state, $docs[0]->snapshot()->data()['state']);
    }

    public function testRetrieveNotFoundASnapshot(): void
    {
        Streams::range(1, 2)->each(
            fn(int $cnt) => $this->collectionReference
                ->document("2-2-2-$cnt")
                ->set(
                    [
                        'version' => $cnt,
                        'aggregateId' => '1-1-1-2',
                        'state' => ['v' => $cnt]
                    ]
                )
        );

        self::assertNull($this->snapshotRepository->retrieve(new DummyId('1-1-1-1')));
    }

    public function testRetrieve(): void
    {
        Streams::range(1, 5)->each(
            fn(int $cnt) => $this->collectionReference
                ->document("2-2-2-$cnt")
                ->set(
                    [
                        'version' => $cnt,
                        'aggregateId' => '1-1-1-1',
                        'state' => ['v' => $cnt]
                    ]
                )
        );

        $snapShot = $this->snapshotRepository->retrieve(new DummyId('1-1-1-1'));

        self::assertInstanceOf(DummyId::class, $snapShot->aggregateRootId());
        self::assertEquals('1-1-1-1', $snapShot->aggregateRootId()->toString());
        self::assertEquals(5, $snapShot->aggregateRootVersion());
        self::assertEquals(['v' => 5], $snapShot->state());
    }
}
