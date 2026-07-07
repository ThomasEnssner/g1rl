<?php

declare(strict_types=1);

use App\Gothic\Lock;
use App\Gothic\Move;
use App\Gothic\State;

test('it holds a start state and the available moves', function () {
    $lock = new Lock(State::fromString('7473'), [
        new Move('P1<', [-1, 0, 0, 0]),
        new Move('P1>', [+1, 0, 0, 0]),
    ]);

    expect($lock->startState->hash())->toBe('7473')
        ->and($lock->moves)->toHaveCount(2);
});

test('it rejects an empty move list', function () {
    new Lock(State::fromString('7473'), []);
})->throws(InvalidArgumentException::class);

test('it rejects moves whose delta length does not match the pin count', function () {
    new Lock(State::fromString('7473'), [
        new Move('P1<', [-1, 0, 0]),
    ]);
})->throws(InvalidArgumentException::class);
