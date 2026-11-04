<?php

declare(strict_types=1);

namespace App\JobPosition\Infrastructure\Cli;

use App\JobPosition\Application\CreateJobPosition\CreateJobPosition;
use App\Shared\Application\Bus\Command\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Positions have no management UI: this command creates them. The ids are fixed, so running
 * it again updates the same positions instead of adding new ones.
 */
#[AsCommand(name: 'app:job-positions:seed', description: 'Creates or updates the demo positions')]
final readonly class SeedJobPositionsCommand
{
    /** Oldest first: the index lists the newest first, and UUID v7 ids sort by creation time. */
    private const array POSITIONS = [
        [
            'id' => '01a1a675-1dbd-7df6-a902-e56b866ab848',
            'title' => 'QA Engineer',
            'workMode' => 'hybrid',
            'location' => 'Bilbao',
            'status' => 'closed',
            'description' => <<<'TXT'
                We were looking for a QA engineer to build our end-to-end test suite.

                This position has been filled. Thanks to everyone who applied.
                TXT,
        ],
        [
            'id' => '01a1a675-1e4a-70f9-ac66-6250fb1805de',
            'title' => 'Data Engineer',
            'workMode' => 'onsite',
            'location' => 'Barcelona',
            'status' => 'open',
            'description' => <<<'TXT'
                Build the pipelines that turn hiring activity into insight for our customers.

                What you will do:
                - Design and run batch and streaming pipelines.
                - Model data for analytics and reporting.
                - Keep data quality high with tests and monitoring.

                What we look for:
                - Strong SQL and Python.
                - Experience with Airflow, dbt or similar tools.
                - Comfort with cloud data warehouses.
                TXT,
        ],
        [
            'id' => '01a1a675-1ecb-7e36-aff9-55b27d175873',
            'title' => 'Product Designer',
            'workMode' => 'remote',
            'location' => 'Spain',
            'status' => 'open',
            'description' => <<<'TXT'
                Shape how recruiters and candidates experience hiring, from the first application to the offer.

                What you will do:
                - Own the design of new features, from discovery to delivery.
                - Run user research with recruiters and candidates.
                - Grow our design system together with engineering.

                What we look for:
                - A portfolio of shipped product work.
                - Strong interaction and visual design skills.
                - Experience with Figma and design systems.
                TXT,
        ],
        [
            'id' => '01a1a675-1f50-7b74-b84c-5cc7f6c43abf',
            'title' => 'Senior Backend Engineer',
            'workMode' => 'hybrid',
            'location' => 'Madrid',
            'status' => 'open',
            'description' => <<<'TXT'
                We are looking for an engineer to own our event-driven backend and help it scale with our customers.

                What you will do:
                - Design and build services with DDD, hexagonal architecture and CQRS.
                - Run asynchronous processing on message queues.
                - Keep quality high with automated tests and code reviews.

                What we look for:
                - Five or more years building backends in PHP (Symfony) or a similar stack.
                - Experience with MongoDB or other document databases.
                - Familiarity with Docker and CI pipelines.
                TXT,
        ],
    ];

    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        foreach (self::POSITIONS as $position) {
            $this->commands->dispatch(new CreateJobPosition(
                title: $position['title'],
                description: $position['description'],
                workMode: $position['workMode'],
                location: $position['location'],
                status: $position['status'],
                id: $position['id'],
            ));
        }

        $io->success(\sprintf('%d positions seeded.', \count(self::POSITIONS)));

        return Command::SUCCESS;
    }
}
