<?php

declare(strict_types=1);

namespace App\Gothic;

use InvalidArgumentException;

/**
 * Result of a solver run. When the lock is unsolvable, $path is null
 * while the search statistics remain available.
 */
final readonly class Solution
{
    /**
     * @param  list<Move>|null  $path
     */
    public function __construct(
        public ?array $path,
        public int $visitedStates,
        public int $iterations,
    ) {}

    public function isSolvable(): bool
    {
        return $this->path !== null;
    }

    public function length(): int
    {
        return count($this->path ?? []);
    }

    /**
     * @return list<string>
     */
    public function moveNames(): array
    {
        return array_map(static fn (Move $move): string => $move->name, $this->path ?? []);
    }

    /**
     * Replays the path from the given start state and returns every state
     * along the way, including start and solved state. Useful for animating
     * the solution step by step.
     *
     * @return list<State>
     */
    public function replay(State $start): array
    {
        $states = [$start];
        $current = $start;

        foreach ($this->path ?? [] as $move) {
            $current = $current->apply($move) ?? throw new InvalidArgumentException(sprintf(
                'Move "%s" is not applicable to state %s - the path does not fit this start state.',
                $move->name,
                $current->hash(),
            ));

            $states[] = $current;
        }

        return $states;
    }
}
