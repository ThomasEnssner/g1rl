<?php

declare(strict_types=1);

namespace App\Gothic;

use SplQueue;

/**
 * Breadth first search solver. BFS explores states level by level, so the
 * first time the target state is reached the path is guaranteed to be the
 * shortest one. Iterative by design - no recursion.
 */
final class Solver implements SolverInterface
{
    public function solve(Lock $lock): Solution
    {
        $start = $lock->startState;

        if ($start->isSolved()) {
            return new Solution([], visitedStates: 1, iterations: 0);
        }

        /** @var SplQueue<State> $queue */
        $queue = new SplQueue();
        $queue->enqueue($start);

        /** @var array<string, true> $visited */
        $visited = [$start->hash() => true];

        /** @var array<string, array{string, Move}> $cameFrom hash => [parent hash, move that led here] */
        $cameFrom = [];

        $iterations = 0;

        while (! $queue->isEmpty()) {
            $current = $queue->dequeue();
            $iterations++;

            foreach ($lock->moves as $move) {
                $next = $current->apply($move);

                if (! $next instanceof State) {
                    continue;
                }

                $hash = $next->hash();

                if (isset($visited[$hash])) {
                    continue;
                }

                $visited[$hash] = true;
                $cameFrom[$hash] = [$current->hash(), $move];

                if ($next->isSolved()) {
                    return new Solution(
                        $this->reconstructPath($cameFrom, $start->hash(), $hash),
                        visitedStates: count($visited),
                        iterations: $iterations,
                    );
                }

                $queue->enqueue($next);
            }
        }

        return new Solution(null, visitedStates: count($visited), iterations: $iterations);
    }

    /**
     * Walks the parent chain from the goal back to the start.
     *
     * @param  array<string, array{string, Move}>  $cameFrom
     * @return list<Move>
     */
    private function reconstructPath(array $cameFrom, string $startHash, string $goalHash): array
    {
        $path = [];
        $hash = $goalHash;

        while ($hash !== $startHash) {
            [$hash, $move] = $cameFrom[$hash];
            $path[] = $move;
        }

        return array_reverse($path);
    }
}
