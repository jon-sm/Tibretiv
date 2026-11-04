<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Infrastructure;

use App\CandidateApplication\Application\Port\CvAnalysisFailed;
use App\CandidateApplication\Application\Port\JobPositionInfo;
use App\CandidateApplication\Infrastructure\Ai\MockCvAnalyser;
use PHPUnit\Framework\TestCase;

final class MockCvAnalyserTest extends TestCase
{
    public function testItSummarisesTheStartOfTheCvAndGivesTheStubScore(): void
    {
        $analysis = (new MockCvAnalyser())->analyse(str_repeat('Backend engineer with PHP. ', 30), $this->position());

        self::assertSame(MockCvAnalyser::STUB_SCORE, $analysis->score);
        self::assertStringStartsWith('Backend engineer with PHP.', $analysis->summary);
        self::assertStringEndsWith('…', $analysis->summary);
        self::assertLessThanOrEqual(281, mb_strlen($analysis->summary));
    }

    public function testAShortCvIsItsOwnSummary(): void
    {
        self::assertSame('Short CV.', (new MockCvAnalyser())->analyse("  Short\n CV. ", $this->position())->summary);
    }

    public function testTheTriggerKeywordMakesItFail(): void
    {
        $this->expectException(CvAnalysisFailed::class);

        (new MockCvAnalyser())->analyse('My CV. '.MockCvAnalyser::FAILURE_TRIGGER, $this->position());
    }

    private function position(): JobPositionInfo
    {
        return new JobPositionInfo('0193f6a2-7c1e-7b3a-9d4e-2f1a8c6b5e01', 'Backend', 'PHP', 'remote', 'Spain', true);
    }
}
