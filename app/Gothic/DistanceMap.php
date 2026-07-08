<?php

declare(strict_types=1);

namespace App\Gothic;

use LogicException;

/**
 * Shortest distances from every reachable state to the target state,
 * produced by a single breadth first search backwards from the target.
 * One map answers shortest-path queries for any number of start states.
 */
final readonly class DistanceMap
{
    /**
     * @param  array<int, int>  $distances  int-encoded state hash => moves to the target
     */
    public function __construct(
        public State $target,
        private array $distances,
        public int $iterations,
    ) {}

    public function distanceTo(State $state): ?int
    {
        return $this->distances[(int) $state->hash()] ?? null;
    }

    public function contains(State $state): bool
    {
        return isset($this->distances[(int) $state->hash()]);
    }

    public function stateCount(): int
    {
        return count($this->distances);
    }

    /**
     * Reconstructs a shortest path by walking downhill through the map:
     * from a state at distance d there is always a move leading to d - 1.
     * Returns null when the target is unreachable from the given state.
     *
     * @param  list<Move>  $moves
     * @return list<Move>|null
     */
    public function pathFrom(State $start, array $moves): ?array
    {
        $distance = $this->distanceTo($start);

        if ($distance === null) {
            return null;
        }

        $path = [];
        $state = $start;

        while ($distance > 0) {
            foreach ($moves as $move) {
                $next = $state->apply($move);

                if ($next instanceof State && $this->distanceTo($next) === $distance - 1) {
                    $path[] = $move;
                    $state = $next;
                    $distance--;

                    continue 2;
                }
            }

            throw new LogicException('The distance map does not match the given moves.');
        }

        return $path;
    }
}
