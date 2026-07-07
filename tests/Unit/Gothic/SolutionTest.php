<?php

declare(strict_types=1);

use App\Gothic\Lock;
use App\Gothic\Move;
use App\Gothic\Solution;
use App\Gothic\Solver;
use App\Gothic\State;

test('it can replay the path as a sequence of states', function () {
    $lock = new Lock(State::fromString('3444'), [
        new Move('P1>', [1, 0, 0, 0]),
    ]);

    $solution = new Solver()->solve($lock);

    $hashes = array_map(
        static fn (State $state): string => $state->hash(),
        $solution->replay($lock->startState),
    );

    expect($hashes)->toBe(['3444', '4444']);
});

test('it replays an empty path as just the start state', function () {
    $solution = new Solution([], visitedStates: 1, iterations: 0);

    $states = $solution->replay(State::fromString('4444'));

    expect($states)->toHaveCount(1)
        ->and($states[0]->hash())->toBe('4444');
});

test('it rejects replaying from a state the path does not fit', function () {
    $solution = new Solution([new Move('P1>', [1, 0, 0, 0])], visitedStates: 2, iterations: 1);

    $solution->replay(State::fromString('7444'));
})->throws(InvalidArgumentException::class);
