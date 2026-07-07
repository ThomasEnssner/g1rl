<?php

declare(strict_types=1);

namespace App\Gothic;

use InvalidArgumentException;

/**
 * A named move that shifts one or more pins, e.g. "P2<" with delta [+1, -1, -1, +1, +1, 0].
 */
final readonly class Move
{
    public const int MIN_DELTA = -1;

    public const int MAX_DELTA = 1;

    /**
     * @param  list<int>  $delta
     */
    public function __construct(
        public string $name,
        public array $delta,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A move needs a name.');
        }

        if ($delta === []) {
            throw new InvalidArgumentException('A move needs at least one delta entry.');
        }

        foreach ($delta as $value) {
            if ($value < self::MIN_DELTA || $value > self::MAX_DELTA) {
                throw new InvalidArgumentException(sprintf(
                    'Delta %d of move "%s" is out of range: a plate can only move by -1, 0 or 1.',
                    $value,
                    $name,
                ));
            }
        }
    }

    /**
     * Derives a move from an in-game observation: try the move once and
     * enter the pin positions before and after - the delta is the difference.
     */
    public static function fromObservation(string $name, State $before, State $after): self
    {
        if ($before->pinCount() !== $after->pinCount()) {
            throw new InvalidArgumentException(sprintf(
                'Observed states have different pin counts (%d and %d).',
                $before->pinCount(),
                $after->pinCount(),
            ));
        }

        $delta = [];

        foreach ($before->pins as $index => $pin) {
            $delta[] = $after->pins[$index] - $pin;
        }

        return new self($name, $delta);
    }

    public function pinCount(): int
    {
        return count($this->delta);
    }

    /**
     * The mirrored move: same pins, opposite direction. In the game, moving
     * a pick position left or right always applies the same deltas negated.
     */
    public function inverted(string $name): self
    {
        return new self($name, array_map(static fn (int $delta): int => -$delta, $this->delta));
    }
}
