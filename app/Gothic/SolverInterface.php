<?php

declare(strict_types=1);

namespace App\Gothic;

/**
 * Contract for lock solvers. Allows swapping the BFS solver for other
 * strategies (e.g. A*) without touching consumers.
 */
interface SolverInterface
{
    public function solve(Lock $lock): Solution;
}
