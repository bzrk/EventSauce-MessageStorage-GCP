<?php

declare(strict_types=1);

namespace Bzrk\Eventsauce\Gcp\Internal;

use Bzrk\Eventsauce\Gcp\Firestore\DocumentBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    /**
     * @test
     */
    public function extractVersionFromPayload(): void
    {
        $document = new Document('id', 'eid', [DocumentBuilder::VERSION => 4]);
        self::assertThat($document->version(), self::equalTo(4));
    }

    /**
     * @test
     */
    public function extractVersionFromPayloadThrowsExceptionIfNotExists(): void
    {
        $document = new Document('id', 'eid', []);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Expected the key "version" to exist.');

        $document->version();
    }
}
