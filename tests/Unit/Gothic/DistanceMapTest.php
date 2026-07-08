<?php

declare(strict_types=1);

use App\Gothic\Lock;
use App\Gothic\Move;
use App\Gothic\Solver;
use App\Gothic\State;

test('it maps the distance of every reachable state with a single search', function () {
    $lock = new Lock(State::fromString('1444'), [
        new Move('P1>', [1, 0, 0, 0]),
        new Move('P1<', [-1, 0, 0, 0]),
    ]);

    $map = new Solver()->map($lock);

    expect($map->target->hash())->toBe('4444')
        ->and($map->distanceTo(State::fromString('4444')))->toBe(0)
        ->and($map->distanceTo(State::fromString('1444')))->toBe(3)
        ->and($map->distanceTo(State::fromString('7444')))->toBe(3)
        // Only plate 1 moves, so exactly the 7 states x444 are reachable.
        ->and($map->stateCount())->toBe(7)
        ->and($map->contains(State::fromString('4441')))->toBeFalse()
        ->and($map->distanceTo(State::fromString('4441')))->toBeNull();
});

test('it reconstructs shortest paths for multiple starts from one map', function () {
    $lock = new Lock(State::fromString('2444'), [
        new Move('P1>', [1, 0, 0, 0]),
        new Move('P1<', [-1, 0, 0, 0]),
    ]);

    $map = new Solver()->map($lock);

    $fromStart = $map->pathFrom(State::fromString('2444'), $lock->moves);
    $fromCurrent = $map->pathFrom(State::fromString('6444'), $lock->moves);

    expect(array_map(fn (Move $move): string => $move->name, $fromStart))->toBe(['P1>', 'P1>'])
        ->and(array_map(fn (Move $move): string => $move->name, $fromCurrent))->toBe(['P1<', 'P1<'])
        ->and($map->pathFrom(State::fromString('4444'), $lock->moves))->toBe([]);
});

test('it returns null paths for unreachable states', function () {
    $lock = new Lock(State::fromString('1444'), [
        new Move('P1>', [1, 0, 0, 0]),
        new Move('P1<', [-1, 0, 0, 0]),
    ]);

    $map = new Solver()->map($lock);

    expect($map->pathFrom(State::fromString('4544'), $lock->moves))->toBeNull();
});

test('path lengths always equal the mapped distance', function () {
    $lock = new Lock(State::fromString('156262'), [
        new Move('P1>', [1, -1, -1, 0, -1, -1]),
        new Move('P1<', [-1, 1, 1, 0, 1, 1]),
        new Move('P4>', [0, 0, 0, 1, 1, -1]),
        new Move('P4<', [0, 0, 0, -1, -1, 1]),
    ]);

    $map = new Solver()->map($lock);
    $start = $lock->startState;

    $distance = $map->distanceTo($start);

    if ($distance === null) {
        expect($map->pathFrom($start, $lock->moves))->toBeNull();

        return;
    }

    expect($map->pathFrom($start, $lock->moves))->toHaveCount($distance);
});
