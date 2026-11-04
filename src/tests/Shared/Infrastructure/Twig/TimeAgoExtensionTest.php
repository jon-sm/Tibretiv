<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Twig;

use App\Shared\Infrastructure\Twig\TimeAgoExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class TimeAgoExtensionTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function times(): iterable
    {
        yield 'seconds' => ['2026-11-04 11:59:30', 'just now'];
        yield 'minutes' => ['2026-11-04 11:55:00', '5 min ago'];
        yield 'hours' => ['2026-11-04 09:00:00', '3 h ago'];
        yield 'days' => ['2026-11-02 12:00:00', '2 d ago'];
        yield 'weeks' => ['2026-10-21 12:00:00', '2 w ago'];
        yield 'older, as a Madrid date' => ['2026-09-30 23:30:00', '1 Oct 2026'];
    }

    #[DataProvider('times')]
    public function testItFormatsTheTimeRelativeToNow(string $time, string $expected): void
    {
        $extension = new TimeAgoExtension(new MockClock('2026-11-04 12:00:00 UTC'));

        self::assertSame($expected, $extension->timeAgo(new \DateTimeImmutable($time.' UTC')));
    }
}
