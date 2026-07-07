<?php

declare(strict_types=1);

use App\Gothic\Move;
use App\Gothic\State;

test('it holds a name and one delta per pin', function () {
    $move = new Move('P2<', [+1, -1, -1, +1, +1, 0]);

    expect($move->name)->toBe('P2<')
        ->and($move->delta)->toBe([1, -1, -1, 1, 1, 0])
        ->and($move->pinCount())->toBe(6);
});

test('it rejects an empty name', function () {
    new Move('  ', [1, 0]);
})->throws(InvalidArgumentException::class);

test('it rejects an empty delta list', function () {
    new Move('P1<', []);
})->throws(InvalidArgumentException::class);

test('it rejects deltas outside -1..1', function (array $delta) {
    new Move('P1>', $delta);
})->with([
    'too high' => [[2, 0]],
    'too low' => [[0, -2]],
])->throws(InvalidArgumentException::class);

test('it can be inverted into the mirrored move', function () {
    $move = new Move('P2>', [+1, -1, 0]);

    $inverse = $move->inverted('P2<');

    expect($inverse->name)->toBe('P2<')
        ->and($inverse->delta)->toBe([-1, 1, 0])
        ->and($move->delta)->toBe([1, -1, 0])
        ->and($inverse->inverted('P2>')->delta)->toBe($move->delta);
});

test('it can be discovered from two observed states', function () {
    $move = Move::fromObservation('P2<', State::fromString('347317'), State::fromString('436427'));

    expect($move->name)->toBe('P2<')
        ->and($move->delta)->toBe([1, -1, -1, 1, 1, 0]);
});

test('it rejects observations with different pin counts', function () {
    Move::fromObservation('P1>', State::fromString('44'), State::fromString('444'));
})->throws(InvalidArgumentException::class);
