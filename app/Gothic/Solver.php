<?php

declare(strict_types=1);

namespace App\Gothic;

/**
 * Breadth first search solver. The search runs backwards from the target
 * state using the mirrored moves, producing a DistanceMap that answers
 * shortest-path queries for any start state - solving from both the start
 * and the current position costs a single search. BFS explores states level
 * by level, so reconstructed paths are guaranteed to be the shortest ones.
 * Iterative by design - no recursion.
 */
final class Solver implements SolverInterface
{
    public function solve(Lock $lock): Solution
    {
        if ($lock->startState->isSolved()) {
            return new Solution([], visitedStates: 1, iterations: 0);
        }

        $map = $this->map($lock);

        return new Solution(
            $map->pathFrom($lock->startState, $lock->moves),
            visitedStates: $map->stateCount(),
            iterations: $map->iterations,
        );
    }

    /**
     * Explores every state that can reach the target. Walking an edge
     * backwards (from a state towards the target) is exactly the mirrored
     * move applied forwards, including the 1..7 bounds check.
     *
     * The search never touches State objects: a state is encoded as an int
     * with 3 bits per plate, so a mirrored move becomes a plain int offset
     * and only the plates it actually shifts need a bounds check on their
     * extracted 3-bit value. Distances live in one packed byte string
     * indexed by state code - a 7-plate lock needs 2 MB instead of an
     * 800k-entry hash map. The hot loop touches millions of edges, where
     * object allocation and re-validation would dominate the runtime.
     */
    public function map(Lock $lock): DistanceMap
    {
        $pinCount = $lock->startState->pinCount();
        $target = new State(array_fill(0, $pinCount, State::TARGET_PIN));

        $moveTable = [];

        foreach ($lock->moves as $move) {
            $offset = 0;
            $checks = [];

            foreach ($move->delta as $index => $delta) {
                if ($delta !== 0) {
                    $shift = 3 * $index;
                    $offset -= $delta << $shift;
                    $checks[] = [$shift, -$delta];
                }
            }

            $moveTable[] = [$offset, $checks];
        }

        $targetCode = DistanceMap::encode($target);

        $distances = str_repeat(DistanceMap::UNREACHABLE, 1 << (3 * $pinCount));
        $distances[$targetCode] = "\x00";

        $queue = [$targetCode];
        $stateCount = 1;
        $iterations = 0;

        for ($head = 0; isset($queue[$head]); $head++) {
            $code = $queue[$head];
            $distance = ord($distances[$code]);
            $iterations++;

            if ($distance >= 254) {
                throw new \LogicException('Shortest paths beyond 254 moves are not supported.');
            }

            $nextDistance = chr($distance + 1);

            foreach ($moveTable as [$offset, $checks]) {
                foreach ($checks as [$shift, $delta]) {
                    $value = (($code >> $shift) & 7) + $delta;

                    if ($value < State::MIN_PIN || $value > State::MAX_PIN) {
                        continue 2;
                    }
                }

                $next = $code + $offset;

                if ($distances[$next] !== DistanceMap::UNREACHABLE) {
                    continue;
                }

                $distances[$next] = $nextDistance;
                $queue[] = $next;
                $stateCount++;
            }
        }

        return new DistanceMap($target, $distances, $iterations, $stateCount);
    }
}
