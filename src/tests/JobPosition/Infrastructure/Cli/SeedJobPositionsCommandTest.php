<?php

declare(strict_types=1);

namespace App\Tests\JobPosition\Infrastructure\Cli;

use App\JobPosition\Domain\JobPosition;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedJobPositionsCommandTest extends DatabaseTestCase
{
    public function testSeedingTwiceKeepsOneCopyOfEachPosition(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        $tester = new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('app:job-positions:seed'));

        $tester->execute([]);
        $this->clock()->modify('+1 day');
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $positions = $this->collection(JobPosition::class);
        self::assertSame(4, $positions->countDocuments());
        self::assertSame(3, $positions->countDocuments(['status' => 'open']));
        self::assertSame(0, $positions->countDocuments(['createdAt' => ['$gt' => new \MongoDB\BSON\UTCDateTime(new \DateTimeImmutable('2026-11-04 10:00:00 UTC'))]]), 'createdAt is kept on update');
    }
}
