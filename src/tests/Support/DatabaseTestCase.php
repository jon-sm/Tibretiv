<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\CandidateApplication\Application\SubmitCandidateApplication\SubmitCandidateApplication;
use App\JobPosition\Application\CreateJobPosition\CreateJobPosition;
use App\Shared\Application\Bus\Command\CommandBus;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Collection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Integration tests against the real MongoDB, in the app_test database, emptied before each test.
 * Extends WebTestCase so HTTP tests can share it; service tests just never create a client.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    public const string OPEN_POSITION = '01a1a675-1f50-7b74-b84c-5cc7f6c43abf';
    public const string OTHER_POSITION = '01a1a675-1ecb-7e36-aff9-55b27d175873';
    public const string CLOSED_POSITION = '01a1a675-1dbd-7df6-a902-e56b866ab848';

    private static bool $indexesEnsured = false;

    protected function resetDatabase(): void
    {
        $documentManager = $this->documentManager();

        // A real MONGODB_DB variable wins over .env.test: never empty a database that isn't a test one
        $database = (string) $documentManager->getConfiguration()->getDefaultDB();
        if (!str_ends_with($database, '_test')) {
            throw new \LogicException(\sprintf('Refusing to empty "%s": tests must use a *_test database.', $database));
        }

        foreach ($documentManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (!$metadata->isEmbeddedDocument) {
                $documentManager->getDocumentCollection($metadata->getName())->deleteMany([]);
            }
        }

        if (!self::$indexesEnsured) {
            $documentManager->getSchemaManager()->ensureIndexes();
            self::$indexesEnsured = true;
        }

        $documentManager->clear();
    }

    protected function documentManager(): DocumentManager
    {
        $documentManager = self::getContainer()->get('doctrine_mongodb.odm.document_manager');
        \assert($documentManager instanceof DocumentManager);

        return $documentManager;
    }

    /** @param class-string $document */
    protected function collection(string $document): Collection
    {
        return $this->documentManager()->getDocumentCollection($document);
    }

    protected function clock(): MockClock
    {
        $clock = self::getContainer()->get('clock');
        \assert($clock instanceof MockClock);

        return $clock;
    }

    protected function commandBus(): CommandBus
    {
        $bus = self::getContainer()->get(CommandBus::class);
        \assert($bus instanceof CommandBus);

        return $bus;
    }

    protected function asyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return $transport;
    }

    protected function seedPositions(): void
    {
        $bus = $this->commandBus();
        $bus->dispatch(new CreateJobPosition('Senior Backend Engineer', 'PHP, Symfony and MongoDB.', 'hybrid', 'Madrid', 'open', self::OPEN_POSITION));
        $bus->dispatch(new CreateJobPosition('Product Designer', 'Figma and research.', 'remote', 'Spain', 'open', self::OTHER_POSITION));
        $bus->dispatch(new CreateJobPosition('QA Engineer', 'Filled.', 'hybrid', 'Bilbao', 'closed', self::CLOSED_POSITION));
    }

    protected function submit(
        string $email = 'jane.doe@gmail.com',
        string $name = 'Jane',
        string $surname = 'Doe',
        string $positionId = self::OPEN_POSITION,
        string $cv = 'Ten years building backend systems in PHP.',
        ?string $notes = null,
    ): void {
        $this->commandBus()->dispatch(new SubmitCandidateApplication($positionId, $name, $surname, $email, '+34 600 000 000', $cv, $notes));
    }
}
