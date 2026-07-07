<?php

declare(strict_types=1);

use App\Gothic\Lock;
use App\Gothic\Move;
use App\Gothic\Solver;
use App\Gothic\State;

/**
 * Builds the classic move set: every pin gets a "Pn<" (-1) and a "Pn>" (+1)
 * move that only touches that single pin.
 *
 * @return list<Move>
 */
function singlePinMoves(int $pinCount): array
{
    $moves = [];

    for ($pin = 0; $pin < $pinCount; $pin++) {
        $decrease = array_fill(0, $pinCount, 0);
        $decrease[$pin] = -1;

        $increase = array_fill(0, $pinCount, 0);
        $increase[$pin] = +1;

        $moves[] = new Move(sprintf('P%d<', $pin + 1), $decrease);
        $moves[] = new Move(sprintf('P%d>', $pin + 1), $increase);
    }

    return $moves;
}

test('it returns an empty path for an already solved lock', function () {
    $lock = new Lock(State::fromString('4444'), singlePinMoves(4));

    $solution = new Solver()->solve($lock);

    expect($solution->isSolvable())->toBeTrue()
        ->and($solution->path)->toBe([])
        ->and($solution->length())->toBe(0)
        ->and($solution->iterations)->toBe(0)
        ->and($solution->visitedStates)->toBe(1);
});

test('it prefers the shortest path over a longer alternative', function () {
    // "Direct" solves plate 1 in a single move; the detour via "Detour1"
    // and "Detour2" would also open the lock but needs two moves.
    $lock = new Lock(State::fromString('34'), [
        new Move('Detour1', [+1, +1]),
        new Move('Detour2', [0, -1]),
        new Move('Direct', [+1, 0]),
    ]);

    $solution = new Solver()->solve($lock);

    expect($solution->isSolvable())->toBeTrue()
        ->and($solution->moveNames())->toBe(['Direct']);
});

test('it solves the 747317 example with the minimal number of moves', function () {
    $lock = new Lock(State::fromString('747317'), singlePinMoves(6));

    $solution = new Solver()->solve($lock);

    // Manhattan distance to 444444: 3+0+3+1+3+3 = 13 moves.
    expect($solution->isSolvable())->toBeTrue()
        ->and($solution->length())->toBe(13);

    // Replaying the path must actually solve the lock.
    $state = $lock->startState;

    foreach ($solution->path as $move) {
        $state = $state->apply($move);
        expect($state)->not->toBeNull();
    }

    expect($state->isSolved())->toBeTrue();
});

test('it solves locks with moves affecting multiple pins', function () {
    $lock = new Lock(State::fromString('3535'), [
        new Move('P1>', [+1, -1, +1, -1]),
        new Move('P2>', [-1, +1, -1, +1]),
        new Move('P3>', [0, 0, +1, +1]),
        new Move('P4>', [+1, +1, 0, 0]),
    ]);

    $solution = new Solver()->solve($lock);

    expect($solution->isSolvable())->toBeTrue();

    $state = $lock->startState;

    foreach ($solution->path as $move) {
        $state = $state->apply($move);
        expect($state)->not->toBeNull();
    }

    expect($state->isSolved())->toBeTrue();
});

test('it ignores moves that would push pins out of range', function () {
    // From 74 the "Both>" move is invalid (pin 1 would become 8), so the
    // solver has to lower pin 1 first even though pin 2 is already solved.
    $lock = new Lock(State::fromString('74'), [
        new Move('Both>', [+1, +1]),
        new Move('P1<', [-1, 0]),
    ]);

    $solution = new Solver()->solve($lock);

    expect($solution->isSolvable())->toBeTrue()
        ->and($solution->moveNames())->toBe(['P1<', 'P1<', 'P1<']);
});

test('it visits every reachable state only once', function () {
    // Moves shift both pins together: only 7 states (11..77) are reachable.
    $lock = new Lock(State::fromString('11'), [
        new Move('Up', [+1, +1]),
        new Move('Down', [-1, -1]),
    ]);

    $solution = new Solver()->solve($lock);

    expect($solution->isSolvable())->toBeTrue()
        ->and($solution->length())->toBe(3)
        ->and($solution->visitedStates)->toBeLessThanOrEqual(7);
});

test('it solves a real difficulty 4 lock from the game', function () {
    // Observed in Gothic 1 Remake by chaining moves from start state 156262:
    // P1> 245251, P2> 255251, P3< 254252, P4> 254361, P5< 254251, P6> 254152.
    // Rows are the ">" direction: Pn> always raises plate n itself by 1.
    $rows = [
        'P1' => [1, -1, -1, 0, -1, -1],
        'P2' => [0, 1, 0, 0, 0, 0],
        'P3' => [0, 0, 1, 0, 0, -1],
        'P4' => [0, 0, 0, 1, 1, -1],
        'P5' => [0, 0, 0, 1, 1, 0],
        'P6' => [0, 0, 0, -1, 0, 1],
    ];

    $moves = [];

    foreach ($rows as $name => $delta) {
        $move = new Move($name.'>', $delta);

        $moves[] = $move;
        $moves[] = $move->inverted($name.'<');
    }

    $lock = new Lock(State::fromString('156262'), $moves);

    $solution = new Solver()->solve($lock);

    expect($solution->isSolvable())->toBeTrue()
        ->and($solution->length())->toBe(22);

    // replay() throws if any move along the path were invalid.
    $states = $solution->replay($lock->startState);

    expect(end($states)->isSolved())->toBeTrue();
});

test('it detects an impossible lock', function () {
    // Both moves shift the plates together, so their difference stays 3
    // forever - the equal target position 44 is unreachable.
    $lock = new Lock(State::fromString('14'), [
        new Move('Up', [+1, +1]),
        new Move('Down', [-1, -1]),
    ]);

    $solution = new Solver()->solve($lock);

    expect($solution->isSolvable())->toBeFalse()
        ->and($solution->path)->toBeNull()
        ->and($solution->length())->toBe(0)
        ->and($solution->moveNames())->toBe([])
        ->and($solution->visitedStates)->toBeGreaterThan(0);
});
