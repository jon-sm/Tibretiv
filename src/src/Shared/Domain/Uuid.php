<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Identifier in canonical RFC 4122 text form. Each aggregate extends it with its own type,
 * so ids of different aggregates cannot be mixed up.
 *
 * Why a regex and not a library: PHP has no native UUID support, and symfony/uid (used by the
 * Infrastructure adapters to generate v7 ids and convert them to BSON binary) is off limits here,
 * because deptrac keeps the Domain free of Symfony code. A deptrac exception would allow
 * UuidV7::isValid(), which is stricter (version 7 only), at the cost of a Domain dependency.
 */
abstract readonly class Uuid implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    final public function __construct(public string $value)
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid UUID.', $value));
        }
    }

    public static function isValid(string $value): bool
    {
        return 1 === preg_match(self::PATTERN, $value);
    }

    public function equals(self $other): bool
    {
        return $other instanceof static && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
