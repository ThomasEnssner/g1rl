<?php

declare(strict_types=1);

namespace App\Gothic;

use InvalidArgumentException;

/**
 * A lock definition: the start state plus the moves available to the player.
 */
final readonly class Lock
{
    /**
     * @var list<Move>
     */
    public array $moves;

    /**
     * @param  array<int, Move>  $moves
     */
    public function __construct(
        public State $startState,
        array $moves,
    ) {
        if ($moves === []) {
            throw new InvalidArgumentException('A lock needs at least one move.');
        }

        foreach ($moves as $move) {
            if ($move->pinCount() !== $startState->pinCount()) {
                throw new InvalidArgumentException(sprintf(
                    'Move "%s" has %d delta entries but the lock has %d pins.',
                    $move->name,
                    $move->pinCount(),
                    $startState->pinCount(),
                ));
            }
        }

        $this->moves = array_values($moves);
    }
}
