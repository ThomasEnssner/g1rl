<?php

declare(strict_types=1);

namespace App\Gothic;

use InvalidArgumentException;

/**
 * Immutable snapshot of all pin positions of a lock.
 */
final readonly class State
{
    public const int MIN_PIN = 1;

    public const int MAX_PIN = 7;

    public const int TARGET_PIN = 4;

    /**
     * @param  list<int>  $pins
     */
    public function __construct(public array $pins)
    {
        if ($pins === []) {
            throw new InvalidArgumentException('A state needs at least one pin.');
        }

        foreach ($pins as $pin) {
            if ($pin < self::MIN_PIN || $pin > self::MAX_PIN) {
                throw new InvalidArgumentException(
                    sprintf('Pin value %d is outside the valid range %d..%d.', $pin, self::MIN_PIN, self::MAX_PIN),
                );
            }
        }
    }

    /**
     * Creates a state from a digit string such as "747317".
     */
    public static function fromString(string $pins): self
    {
        $trimmed = trim($pins);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            throw new InvalidArgumentException(sprintf('Invalid state string "%s".', $pins));
        }

        return new self(array_map(intval(...), str_split($trimmed)));
    }

    public function pinCount(): int
    {
        return count($this->pins);
    }

    /**
     * Applies a move and returns the resulting state, or null when the
     * move would push any pin outside the valid range.
     */
    public function apply(Move $move): ?self
    {
        $pins = [];

        foreach ($this->pins as $index => $pin) {
            $pin += $move->delta[$index] ?? 0;

            if ($pin < self::MIN_PIN || $pin > self::MAX_PIN) {
                return null;
            }

            $pins[] = $pin;
        }

        return new self($pins);
    }

    public function canApply(Move $move): bool
    {
        return $this->apply($move) instanceof self;
    }

    public function equals(self $other): bool
    {
        return $this->pins === $other->pins;
    }

    /**
     * Unique hash of the state, e.g. "747317". Pins are single digits,
     * so the concatenation is collision free.
     */
    public function hash(): string
    {
        return implode('', $this->pins);
    }

    public function isSolved(): bool
    {
        foreach ($this->pins as $pin) {
            if ($pin !== self::TARGET_PIN) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->hash();
    }
}
