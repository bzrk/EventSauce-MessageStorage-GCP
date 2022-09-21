<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Firestore;

use Bzrk\Eventsauce\Test\Firestore\DummyEvent;
use Bzrk\Eventsauce\Test\Firestore\DummyId;
use BZRK\PHPStream\StreamException;
use BZRK\PHPStream\Streams;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;

class MessageRepositoryTest extends TestCase
{
    private const COLLECTION = "events";

    private CollectionReference $collectionReference;
    private MessageRepository $messageRepository;

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

        $this->messageRepository = new MessageRepository(
            $firestoreClient,
            self::COLLECTION,
            new ConstructingMessageSerializer()
        );
    }

    /**
     * @throws StreamException
     */
    public function testPersist(): void
    {
        $messages = [
            new Message(
                new DummyEvent(['a' => 'b']),
                [
                    Header::EVENT_ID => "1-1-1-1",
                    Header::AGGREGATE_ROOT_TYPE => "type",
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_TYPE => "bzrk.eventsauce.test.firestore.dummy_event",
                    Header::AGGREGATE_ROOT_ID => new DummyId("1-1-1-2"),
                    Header::TIME_OF_RECORDING => "2022-09-12 12:13:14.1234"
                ]
            ),
            new Message(
                new DummyEvent(['b' => 'c']),
                [
                    Header::EVENT_ID => "1-1-2-1",
                    Header::AGGREGATE_ROOT_TYPE => "type",
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_TYPE => "bzrk.eventsauce.test.firestore.dummy_event",
                    Header::AGGREGATE_ROOT_ID => new DummyId("1-1-2-2"),
                    Header::TIME_OF_RECORDING => "2022-09-12 12:13:15.1234"
                ]
            ),
        ];

        $this->messageRepository->persist(...$messages);

        /** @var DocumentReference[] $docs */
        $docs = Streams::of($this->collectionReference->listDocuments())->toList();

        self::assertCount(2, $docs);
        self::assertEquals("1-1-1-1", $docs[0]->id());
        self::assertEquals(
            [
                'headers' => [
                    Header::AGGREGATE_ROOT_TYPE => 'type',
                    Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                    Header::TIME_OF_RECORDING => '2022-09-12 12:13:14.1234',
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_ID  => '1-1-1-1',
                    Header::AGGREGATE_ROOT_ID => '1-1-1-2',
                    Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id'
                ],
                'version' => 1,
                'aggregate' => 'type',
                'aggregateId' => '1-1-1-2',
                'payload' => ['a' => 'b'],
                'recordedAt' => '2022-09-12 12:13:14.1234',
                'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
            ],
            $docs[0]->snapshot()->data()
        );
        self::assertEquals("1-1-2-1", $docs[1]->id());
        self::assertEquals(
            [
                'headers' => [
                    Header::AGGREGATE_ROOT_TYPE => 'type',
                    Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                    Header::TIME_OF_RECORDING => '2022-09-12 12:13:15.1234',
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_ID  => '1-1-2-1',
                    Header::AGGREGATE_ROOT_ID => '1-1-2-2',
                    Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id'
                ],
                'version' => 1,
                'aggregate' => 'type',
                'aggregateId' => '1-1-2-2',
                'payload' => ['b' => 'c'],
                'recordedAt' => '2022-09-12 12:13:15.1234',
                'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
            ],
            $docs[1]->snapshot()->data()
        );
    }

    /**
     * @throws StreamException
     */
    public function testPersistWithSameAggregateIdAndVersion(): void
    {
        $this->collectionReference->document("1-1-1-3")->set(
            [
                'headers' => [
                    Header::AGGREGATE_ROOT_TYPE => 'type',
                    Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                    Header::TIME_OF_RECORDING => '2022-09-12 12:13:14.1234',
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_ID  => '1-1-1-1',
                    Header::AGGREGATE_ROOT_ID => '1-1-1-2',
                    Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id'
                ],
                'version' => 1,
                'aggregate' => 'type',
                'aggregateId' => '1-1-1-2',
                'payload' => ['a' => 'b'],
                'recordedAt' => '2022-09-12 12:13:14.1234',
                'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
            ]
        );

        $message = new Message(
            new DummyEvent(['a' => 'b']),
            [
                Header::EVENT_ID => "1-1-1-1",
                Header::AGGREGATE_ROOT_TYPE => "type",
                Header::AGGREGATE_ROOT_VERSION => 1,
                Header::EVENT_TYPE => "bzrk.eventsauce.test.firestore.dummy_event",
                Header::AGGREGATE_ROOT_ID => new DummyId('1-1-1-2'),
                Header::TIME_OF_RECORDING => "2022-09-12 12:13:14.1234"
            ]
        );

        $this->expectException(VersionConstraintException::class);
        $this->expectExceptionMessage("AggregateId: 1-1-1-2, version: 1");

        $this->messageRepository->persist($message);
    }

    /**
     * @throws StreamException
     */
    public function testRetrieveAll(): void
    {
        $this->initForRetrieveOrPaginate();

        /** @var Message[] $messages */
        $messages = Streams::of($this->messageRepository->retrieveAll(new DummyId('1-1-1-1')))->toList();
        self::assertCount(4, $messages);
        self::assertInstanceOf(DummyEvent::class, $messages[0]->payload());
        self::assertEquals(
            [
                Header::AGGREGATE_ROOT_TYPE => 'type',
                Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                Header::TIME_OF_RECORDING => '2022-09-17 12:11:57.433743+0200',
                Header::AGGREGATE_ROOT_VERSION => 1,
                Header::EVENT_ID => '1-1-1-1',
                Header::AGGREGATE_ROOT_ID => new DummyId('1-1-1-1'),
                Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id',
            ],
            $messages[0]->headers()
        );
        self::assertEquals(new DummyId('1-1-1-1'), $messages[1]->aggregateRootId());
        self::assertEquals(2, $messages[1]->aggregateVersion());
        self::assertEquals(new DummyId('1-1-1-1'), $messages[2]->aggregateRootId());
        self::assertEquals(3, $messages[2]->aggregateVersion());
        self::assertEquals(new DummyId('1-1-1-1'), $messages[3]->aggregateRootId());
        self::assertEquals(4, $messages[3]->aggregateVersion());
    }

    /**
     * @throws StreamException
     */
    public function testRetrieveAfterVersion(): void
    {
        $this->initForRetrieveOrPaginate();

        /** @var Message[] $messages */
        $messages = Streams::of($this->messageRepository->retrieveAllAfterVersion(new DummyId('1-1-1-1'), 2))->toList();
        self::assertCount(2, $messages);
        self::assertInstanceOf(DummyEvent::class, $messages[0]->payload());
        self::assertEquals(
            [
                Header::AGGREGATE_ROOT_TYPE => 'type',
                Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                Header::TIME_OF_RECORDING => '2022-09-17 12:13:57.433743+0200',
                Header::AGGREGATE_ROOT_VERSION => 3,
                Header::EVENT_ID => '1-1-1-3',
                Header::AGGREGATE_ROOT_ID => new DummyId('1-1-1-1'),
                Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id',
            ],
            $messages[0]->headers()
        );
        self::assertEquals(new DummyId('1-1-1-1'), $messages[1]->aggregateRootId());
        self::assertEquals(4, $messages[1]->aggregateVersion());
    }

    /**
     * @throws StreamException
     */
    public function testPaginate(): void
    {
        $this->initForRetrieveOrPaginate();

        $generator = $this->messageRepository->paginate(FirestoreCursor::fromString('2022-09-17 12:12:57.433743+0200'));

        /** @var Message[] $messages */
        $messages = Streams::of($generator)
            ->map(fn(Message $msg) => [$msg->aggregateRootId()->toString(), $msg->timeOfRecording()->getTimestamp()])
            ->toList();

        $newCursor = $generator->getReturn();

        self::assertEquals(
            [
                ['1-1-1-1', 1663409637],
                ['2-1-1-1', 1663409637],
                ['1-1-1-1', 1663409697],
            ],
            $messages
        );

        self::assertInstanceOf(FirestoreCursor::class, $newCursor);
        self::assertEquals('2022-09-17 12:14:57.433743+0200', $newCursor->toString());
    }

    private function initForRetrieveOrPaginate(): void
    {
        Streams::range(1, 4)
            ->each(function (int $cnt) {
                $this->collectionReference
                    ->document("1-1-1-$cnt")
                    ->set(
                        [
                            'headers' => [
                                Header::AGGREGATE_ROOT_TYPE => "type",
                                Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                                Header::TIME_OF_RECORDING => "2022-09-17 12:1{$cnt}:57.433743+0200",
                                Header::AGGREGATE_ROOT_VERSION => $cnt,
                                Header::EVENT_ID  => "1-1-1-$cnt",
                                Header::AGGREGATE_ROOT_ID => "1-1-1-1",
                                Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id'
                            ],
                            'version' => $cnt,
                            'aggregate' => 'type',
                            'aggregateId' => "1-1-1-1",
                            'payload' => ['a' => 'b'],
                            'recordedAt' => "2022-09-17 12:1{$cnt}:57.433743+0200",
                            'event' => 'eventType'
                        ]
                    );
            });

        $this->collectionReference
            ->document("2-1-1-1")
            ->set(
                [
                    'headers' => [
                        Header::AGGREGATE_ROOT_TYPE => "type",
                        Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                        Header::TIME_OF_RECORDING => "2022-09-17 12:13:57.433743+0200",
                        Header::AGGREGATE_ROOT_VERSION => 1,
                        Header::EVENT_ID  => "2-1-1-1",
                        Header::AGGREGATE_ROOT_ID => "2-1-1-1",
                        Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id'
                    ],
                    'version' => 1,
                    'aggregate' => 'type',
                    'aggregateId' => "2-1-1-1",
                    'payload' => ['a' => 'b'],
                    'recordedAt' => "2022-09-17 12:13:57.433743+0200",
                    'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
                ]
            );
    }
}
