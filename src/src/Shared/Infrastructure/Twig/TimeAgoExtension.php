<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Twig;

use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFilter;

/**
 * Short relative times for lists: "just now", "5 min ago", "3 h ago", "2 d ago", "1 w ago".
 * Older than four weeks, the date itself ("4 Nov 2026").
 */
final readonly class TimeAgoExtension
{
    public function __construct(private ClockInterface $clock)
    {
    }

    #[AsTwigFilter('time_ago')]
    public function timeAgo(\DateTimeInterface $time, string $timezone = 'Europe/Madrid'): string
    {
        $seconds = $this->clock->now()->getTimestamp() - $time->getTimestamp();

        return match (true) {
            $seconds < 60 => 'just now',
            $seconds < 3600 => intdiv($seconds, 60).' min ago',
            $seconds < 86400 => intdiv($seconds, 3600).' h ago',
            $seconds < 7 * 86400 => intdiv($seconds, 86400).' d ago',
            $seconds < 28 * 86400 => intdiv($seconds, 7 * 86400).' w ago',
            default => \DateTimeImmutable::createFromInterface($time)->setTimezone(new \DateTimeZone($timezone))->format('j M Y'),
        };
    }
}
