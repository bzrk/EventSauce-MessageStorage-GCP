# Eventsauce Firestore

Implementation of [EventSauce](https://github.com/EventSaucePHP/EventSauce) Message- and SnapshotRepository for Google Firestore

## Usage
```php

$firestoreClient = new FirestoreClient();

$this->messageRepository = new MessageRepository(
    $firestoreClient,
    'collectionForAggregateEvents',
    new ConstructingMessageSerializer()
);

$this->snapshotRepository = new SnapshotRepository(
    $firestoreClient,
    'collectionForAggregateSnapshots'
);
```

## Running Tests
```shell
// starts needed Containers
docker-compose up

// tests
docker-compose run php composer verify
```