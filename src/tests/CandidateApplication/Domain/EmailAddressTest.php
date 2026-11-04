<?php

declare(strict_types=1);

namespace App\Tests\CandidateApplication\Domain;

use App\CandidateApplication\Domain\Candidate\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function emails(): iterable
    {
        yield 'gmail drops dots and tag' => ['Jane.Doe+jobs@gmail.com', 'jane.doe+jobs@gmail.com', 'janedoe@gmail.com'];
        yield 'googlemail becomes gmail' => ['jane.doe+jobs@googlemail.com', 'jane.doe+jobs@googlemail.com', 'janedoe@gmail.com'];
        yield 'outlook drops tag only' => ['jane.doe+jobs@outlook.com', 'jane.doe+jobs@outlook.com', 'jane.doe@outlook.com'];
        yield 'proton drops tag' => ['jane+x@proton.me', 'jane+x@proton.me', 'jane@proton.me'];
        yield 'other domains are kept' => ['jane.doe+x@acme.com', 'jane.doe+x@acme.com', 'jane.doe+x@acme.com'];
        yield 'trimmed and lowercased' => ['  JANE@Acme.COM ', 'jane@acme.com', 'jane@acme.com'];
    }

    #[DataProvider('emails')]
    public function testItNormalisesTheEmailAndItsCanonicalMailbox(string $typed, string $value, string $canonical): void
    {
        $email = new EmailAddress($typed);

        self::assertSame($value, $email->value);
        self::assertSame($canonical, $email->canonical);
    }

    public function testItRejectsAnInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EmailAddress('not-an-email');
    }
}
