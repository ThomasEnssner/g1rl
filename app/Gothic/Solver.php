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
     * The search runs on plain int arrays and int-encoded states instead of
     * State objects: the hot loop touches over a million edges, where object
     * allocation and re-validation would dominate the runtime.
     */
    public function map(Lock $lock): DistanceMap
    {
        $pinCount = $lock->startState->pinCount();
        $target = new State(array_fill(0, $pinCount, State::TARGET_PIN));

        // A state is encoded as a decimal int, one digit per plate (e.g.
        // 156262). A mirrored move then becomes a plain int offset, and only
        // the plates it actually shifts need a 1..7 bounds check on their
        // extracted digit.
        $moveTable = [];

        foreach ($lock->moves as $move) {
            $offset = 0;
            $checks = [];

            foreach ($move->delta as $index => $delta) {
                $power = 10 ** ($pinCount - 1 - $index);
                $offset -= $delta * $power;

                if ($delta !== 0) {
                    $checks[] = [$power, -$delta];
                }
            }

            $moveTable[] = [$offset, $checks];
        }

        /** @var array<int, int> $distances int-encoded state => moves to the target */
        $distances = [(int) $target->hash() => 0];
        $queue = [(int) $target->hash()];

        $iterations = 0;

        for ($head = 0; isset($queue[$head]); $head++) {
            $code = $queue[$head];
            $distance = $distances[$code];
            $iterations++;

            foreach ($moveTable as [$offset, $checks]) {
                foreach ($checks as [$power, $delta]) {
                    $value = intdiv($code, $power) % 10 + $delta;

                    if ($value < State::MIN_PIN || $value > State::MAX_PIN) {
                        continue 2;
                    }
                }

                $next = $code + $offset;

                if (isset($distances[$next])) {
                    continue;
                }

                $distances[$next] = $distance + 1;
                $queue[] = $next;
            }
        }

        return new DistanceMap($target, $distances, $iterations);
    }
}
