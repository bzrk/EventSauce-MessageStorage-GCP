# Eventsauce Firestore

Implementation of [EventSauce](https://github.com/EventSaucePHP/EventSauce) 
Message- and SnapshotRepository for Google Firestore and Google Datastore 

## Usage Firestore
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

## Usage Datastore
```php

$datastoreClient = new DatastoreClient();

$this->messageRepository = new MessageRepository(
    $datastoreClient,
    'collectionForAggregateEvents',
    new ConstructingMessageSerializer()
);

$this->snapshotRepository = new SnapshotRepository(
    $datastoreClient,
    'collectionForAggregateSnapshots'
);
```

## Running Tests
```shell
// starts needed Containers
docker-compose up

// running qualtity tools
docker-compose run --rm php composer verify
```