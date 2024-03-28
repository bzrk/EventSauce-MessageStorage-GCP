<?php

/**
 * This script is used to update the Datastore entities to the new format.
 *
 * The new format is:
 *
 * - The payload and headers are now stored as JSON strings.
 * - The payload and headers are now excluded from indexes.
 *
 * This script will update all entities in the Datastore to the new format.
 */

use Bzrk\Eventsauce\Gcp\Internal\DocumentBuilder;
use BZRK\PHPStream\Stream;
use BZRK\PHPStream\Streams;
use Google\Cloud\Datastore\Entity;

include __DIR__ . '/../vendor/autoload.php';

$args = $_SERVER['argv'];
if (count($args) != 4) {
    echo "Usage: php scripts/v0-to-v1.php <projectId> <databaseId> <collection>\n";
    exit(1);
}

list($script, $projectId, $databaseId, $collection) = $args;


$client = new Google\Cloud\Datastore\DatastoreClient(
    [
        'projectId'  => $projectId,
        'databaseId' => $databaseId == '(default)' ? '' : $databaseId,
    ]
);

$result = $client->runQuery($client->query()->kind($collection));
$count = 0;
$batchSize = 500;

Streams::of($result)
    ->map(function (Entity $entity) use ($client) {
        $data = $entity->get();
        $properties = [DocumentBuilder::PAYLOAD, DocumentBuilder::HEADERS];

        Streams::of($properties)->each(function ($value) use ($data, $entity) {
            if (is_object($data[$value]) || is_array($data[$value])) {
                $entity->setProperty($value, json_encode($data[$value]));
            }
        });

        $entity->setExcludeFromIndexes($properties);

        return $entity;
    })
    ->batch($batchSize)
    ->each(function (Stream $entities) use ($client, &$count, $batchSize) {
        $data = $entities->toList();
        $client->updateBatch($data);
        $count += count($data);
        echo str_pad("Updated " . ($count) . " entities", 40, ' ') . "\r";
    });

echo "\nDone\n";
