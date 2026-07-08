<?php

declare(strict_types=1);

namespace App\Gothic;

use LogicException;

/**
 * Shortest distances from every reachable state to the target state,
 * produced by a single breadth first search backwards from the target.
 * One map answers shortest-path queries for any number of start states.
 *
 * Distances are stored in one packed byte string indexed by the state's
 * 3-bits-per-plate int encoding - compact enough for 7-plate locks.
 */
final readonly class DistanceMap
{
    public const string UNREACHABLE = "\xff";

    public function __construct(
        public State $target,
        private string $distances,
        public int $iterations,
        private int $stateCount,
    ) {}

    /**
     * Encodes a state as an int with 3 bits per plate (values 1..7).
     */
    public static function encode(State $state): int
    {
        $code = 0;

        foreach ($state->pins as $index => $pin) {
            $code |= $pin << (3 * $index);
        }

        return $code;
    }

    public function distanceTo(State $state): ?int
    {
        if ($state->pinCount() !== $this->target->pinCount()) {
            return null;
        }

        $byte = $this->distances[self::encode($state)];

        return $byte === self::UNREACHABLE ? null : ord($byte);
    }

    public function contains(State $state): bool
    {
        return $this->distanceTo($state) !== null;
    }

    public function stateCount(): int
    {
        return $this->stateCount;
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
