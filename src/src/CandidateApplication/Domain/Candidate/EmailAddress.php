<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain\Candidate;

/**
 * An email as typed (trimmed and lowercased), plus its canonical mailbox for duplicate checks.
 *
 * Dots and +tags are only removed for providers known to ignore them: elsewhere they can be
 * part of a different, real address.
 */
final readonly class EmailAddress
{
    /** Providers that ignore dots in the local part, and the domain their aliases map to. */
    private const array DOT_INSENSITIVE = [
        'gmail.com' => 'gmail.com',
        'googlemail.com' => 'gmail.com',
    ];

    /** Providers that deliver local+tag to local. */
    private const array PLUS_TAGGED = [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'live.com',
        'icloud.com', 'me.com',
        'fastmail.com',
        'proton.me', 'protonmail.com',
    ];

    public string $value;
    public string $canonical;

    public function __construct(string $email)
    {
        $email = mb_strtolower(trim($email));

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid email address.', $email));
        }

        $this->value = $email;
        $this->canonical = self::canonicalise($email);
    }

    private static function canonicalise(string $email): string
    {
        $at = strrpos($email, '@');
        \assert(false !== $at);
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);

        if (\in_array($domain, self::PLUS_TAGGED, true) && false !== ($plus = strpos($local, '+'))) {
            $local = substr($local, 0, $plus);
        }

        if (isset(self::DOT_INSENSITIVE[$domain])) {
            $local = str_replace('.', '', $local);
            $domain = self::DOT_INSENSITIVE[$domain];
        }

        return $local.'@'.$domain;
    }
}
