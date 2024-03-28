<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Datastore;

use Bzrk\Eventsauce\Gcp\Cursor;
use Bzrk\Eventsauce\Gcp\Internal\DocumentBuilder as InternalDocumentBuilder;
use Bzrk\Eventsauce\Gcp\VersionConstraintException;
use Bzrk\Eventsauce\Test\Firestore\DummyEvent;
use Bzrk\Eventsauce\Test\Firestore\DummyId;
use BZRK\PHPStream\Stream;
use BZRK\PHPStream\StreamException;
use BZRK\PHPStream\Streams;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\EntityInterface;
use PHPUnit\Framework\TestCase;

class MessageRepositoryTest extends TestCase
{
    private const COLLECTION = "events";

    private DatastoreClient $dataStoreClient;
    private MessageRepository $messageRepository;

    /**
     * @throws StreamException
     */
    protected function setUp(): void
    {
        $this->dataStoreClient = new DatastoreClient();
        $this->allDocuments()->each(fn(EntityInterface $it) => $this->dataStoreClient->delete($it->key()));

        $this->messageRepository = new MessageRepository(
            $this->dataStoreClient,
            self::COLLECTION,
            new ConstructingMessageSerializer()
        );
    }

    /**
     * @throws StreamException|VersionConstraintException
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
                    Header::TIME_OF_RECORDING => "2022-09-12 12:13:14.728749+0200"
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
                    Header::TIME_OF_RECORDING => "2022-09-12 12:13:15.728749+0200"
                ]
            ),
        ];

        $this->messageRepository->persist(...$messages);

        /** @var EntityInterface[] $docs */
        $docs = $this->allDocuments()->toList();

        self::assertCount(2, $docs);
        self::assertEquals("1-1-1-2::1", $docs[0]->key()->pathEndIdentifier());
        self::assertEquals(
            [
                'headers' => json_encode([
                    Header::EVENT_ID => '1-1-1-2::1',
                    Header::AGGREGATE_ROOT_TYPE => 'type',
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                    Header::AGGREGATE_ROOT_ID => '1-1-1-2',
                    Header::TIME_OF_RECORDING => '2022-09-12 12:13:14.728749+0200',
                    Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id',
                    InternalDocumentBuilder::TIMESTAMP => '1662977594.728749'
                ]),
                'version' => 1,
                'aggregate' => 'type',
                'aggregateId' => '1-1-1-2',
                'payload' => json_encode(['a' => 'b']),
                'timestamp' => '1662977594.728749',
                'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
            ],
            $docs[0]->get()
        );
        self::assertEquals("1-1-2-2::1", $docs[1]->key()->pathEndIdentifier());
        self::assertEquals(
            [
                'headers' => json_encode([
                    Header::EVENT_ID => '1-1-2-2::1',
                    Header::AGGREGATE_ROOT_TYPE => 'type',
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                    Header::AGGREGATE_ROOT_ID => '1-1-2-2',
                    Header::TIME_OF_RECORDING => '2022-09-12 12:13:15.728749+0200',
                    Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id',
                    InternalDocumentBuilder::TIMESTAMP => '1662977595.728749'
                ]),
                'version' => 1,
                'aggregate' => 'type',
                'aggregateId' => '1-1-2-2',
                'payload' => json_encode(['b' => 'c']),
                'timestamp' => '1662977595.728749',
                'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
            ],
            $docs[1]->get()
        );
    }

    /**
     * @throws StreamException|VersionConstraintException
     */
    public function testPersistWithNullValuesInPayload(): void
    {
        $message = new Message(
            new DummyEvent(['a' => 'b', 'c' => null]),
            [
                Header::EVENT_ID => "1-1-1-1",
                Header::AGGREGATE_ROOT_TYPE => "type",
                Header::AGGREGATE_ROOT_VERSION => 1,
                Header::EVENT_TYPE => "bzrk.eventsauce.test.firestore.dummy_event",
                Header::AGGREGATE_ROOT_ID => new DummyId("1-1-1-2"),
                Header::TIME_OF_RECORDING => "2022-09-12 12:13:14.728749+0200"
            ]
        );

        $this->messageRepository->persist($message);

        /** @var EntityInterface[] $docs */
        $docs = $this->allDocuments()->toList();

        self::assertCount(1, $docs);
        self::assertEquals(json_encode(['a' => 'b']), $docs[0]['payload']);
    }

    /**
     * @throws StreamException
     */
    public function testPersistWithSameAggregateIdAndVersion(): void
    {
        $key = $this->dataStoreClient->key(self::COLLECTION, "1-1-1-2::1");
        $entity = $this->dataStoreClient->entity($key, [
            'headers' => [
                Header::AGGREGATE_ROOT_TYPE => 'type',
                Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                Header::TIME_OF_RECORDING => '2022-09-12 12:13:14.728749+0200',
                Header::AGGREGATE_ROOT_VERSION => 1,
                Header::EVENT_ID => '1-1-1-1',
                Header::AGGREGATE_ROOT_ID => '1-1-1-2',
                Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id',
                InternalDocumentBuilder::TIMESTAMP => '1662977595.728749'
            ],
            'version' => 1,
            'aggregate' => 'type',
            'aggregateId' => '1-1-1-2',
            'payload' => ['a' => 'b'],
            'timestamp' => '1662977595.728749',
            'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
        ]);
        $this->dataStoreClient->insert($entity);

        $message = new Message(
            new DummyEvent(['a' => 'b']),
            [
                Header::EVENT_ID => "1-1-1-1",
                Header::AGGREGATE_ROOT_TYPE => "type",
                Header::AGGREGATE_ROOT_VERSION => 1,
                Header::EVENT_TYPE => "bzrk.eventsauce.test.firestore.dummy_event",
                Header::AGGREGATE_ROOT_ID => new DummyId('1-1-1-2'),
                Header::TIME_OF_RECORDING => "2022-09-12 12:13:14.728749+0200"
            ]
        );

        $this->expectException(VersionConstraintException::class);
        $this->expectExceptionMessage("AggregateId: 1-1-1-2::1");

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
                InternalDocumentBuilder::TIMESTAMP => '11.433743'
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
                InternalDocumentBuilder::TIMESTAMP => '13.433743'
            ],
            $messages[0]->headers()
        );
        self::assertEquals(new DummyId('1-1-1-1'), $messages[1]->aggregateRootId());
        self::assertEquals(4, $messages[1]->aggregateVersion());
    }

    public function testPaginate(): void
    {
        $this->initForRetrieveOrPaginate();

        $generator = $this->messageRepository->paginate(Cursor::fromString('12.433743'));

        /** @var Message[] $messages */
        $messages = Streams::of($generator)
            ->map(fn(Message $msg) => [$msg->aggregateRootId()->toString(), $msg->timeOfRecording()->getTimestamp()])
            ->toList();

        $newCursor = $generator->getReturn();

        self::assertEquals(
            [
                ['1-1-1-1', 1663409637],
                ['1-1-1-1', 1663409697],
                ['2-1-1-1', 1663409637],
            ],
            $messages
        );

        self::assertInstanceOf(Cursor::class, $newCursor);
        self::assertEquals('1663409637.433743', $newCursor->toString());
    }

    private function initForRetrieveOrPaginate(): void
    {
        Streams::range(1, 4)
            ->each(function (int $cnt) {
                $key = $this->dataStoreClient->key(self::COLLECTION, "1-1-1-1::$cnt");
                $entity = $this->dataStoreClient->entity(
                    $key,
                    [
                        'headers' => json_encode([
                            Header::AGGREGATE_ROOT_TYPE => "type",
                            Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                            Header::TIME_OF_RECORDING => "2022-09-17 12:1{$cnt}:57.433743+0200",
                            Header::AGGREGATE_ROOT_VERSION => $cnt,
                            Header::EVENT_ID => "1-1-1-$cnt",
                            Header::AGGREGATE_ROOT_ID => "1-1-1-1",
                            Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id',
                            InternalDocumentBuilder::TIMESTAMP => "1{$cnt}.433743",
                        ]),
                        'version' => $cnt,
                        'aggregate' => 'type',
                        'aggregateId' => "1-1-1-1",
                        'payload' => json_encode(['a' => 'b']),
                        'timestamp' => "1{$cnt}.433743",
                        'event' => 'eventType'
                    ]
                );
                $this->dataStoreClient->insert($entity);
            });

        $key = $this->dataStoreClient->key(self::COLLECTION, '2-1-1-1::1');
        $entity = $this->dataStoreClient->entity(
            $key,
            [
                'headers' => json_encode([
                    Header::AGGREGATE_ROOT_TYPE => "type",
                    Header::EVENT_TYPE => 'bzrk.eventsauce.test.firestore.dummy_event',
                    Header::TIME_OF_RECORDING => "2022-09-17 12:13:57.433743+0200",
                    Header::AGGREGATE_ROOT_VERSION => 1,
                    Header::EVENT_ID => "2-1-1-1",
                    Header::AGGREGATE_ROOT_ID => "2-1-1-1",
                    Header::AGGREGATE_ROOT_ID_TYPE => 'bzrk.eventsauce.test.firestore.dummy_id',
                    InternalDocumentBuilder::TIMESTAMP => "1663409637.433743",
                ]),
                'version' => 1,
                'aggregate' => 'type',
                'aggregateId' => "2-1-1-1",
                'payload' => json_encode(['a' => 'b']),
                'timestamp' => "1663409637.433743",
                'event' => 'bzrk.eventsauce.test.firestore.dummy_event'
            ]
        );
        $this->dataStoreClient->insert($entity);
    }

    /**
     * @throws StreamException
     */
    private function allDocuments(): Stream
    {
        return Streams::of($this->dataStoreClient->runQuery(
            $this->dataStoreClient->query()->kind(self::COLLECTION)
        ));
    }
}
