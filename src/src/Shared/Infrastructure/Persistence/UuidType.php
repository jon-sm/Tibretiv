<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Uuid;
use Doctrine\ODM\MongoDB\Types\ClosureToPHP;
use Doctrine\ODM\MongoDB\Types\Type;
use MongoDB\BSON\Binary;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * Stores a domain Uuid as BSON binary subtype 4 (16 bytes) rather than a 36-character string.
 */
abstract class UuidType extends Type
{
    use ClosureToPHP;

    /** @return class-string<Uuid> */
    abstract protected function idClass(): string;

    public function convertToDatabaseValue(mixed $value): ?Binary
    {
        return match (true) {
            null === $value, $value instanceof Binary => $value,
            $value instanceof Uuid => self::toBinary($value->value),
            \is_string($value) => self::toBinary($value),
            default => throw new \InvalidArgumentException(\sprintf('Cannot store %s as a UUID.', get_debug_type($value))),
        };
    }

    public function convertToPHPValue(mixed $value): ?Uuid
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Binary || Binary::TYPE_UUID !== $value->getType()) {
            throw new \InvalidArgumentException(\sprintf('Expected a binary UUID, got %s.', get_debug_type($value)));
        }

        $class = $this->idClass();

        return new $class(SymfonyUuid::fromBinary($value->getData())->toRfc4122());
    }

    public static function toBinary(string $uuid): Binary
    {
        return new Binary(SymfonyUuid::fromString($uuid)->toBinary(), Binary::TYPE_UUID);
    }

    public static function fromBinary(Binary $binary): string
    {
        return SymfonyUuid::fromBinary($binary->getData())->toRfc4122();
    }
}
