<?php

declare(strict_types=1);

use App\Gothic\Move;
use App\Gothic\State;

test('it can be created from a digit string', function () {
    $state = State::fromString('747317');

    expect($state->pins)->toBe([7, 4, 7, 3, 1, 7])
        ->and($state->pinCount())->toBe(6)
        ->and($state->hash())->toBe('747317')
        ->and((string) $state)->toBe('747317');
});

test('it rejects invalid state strings', function (string $pins) {
    State::fromString($pins);
})->with([
    'empty' => '',
    'letters' => '74a317',
    'pin below range' => '740317',
    'pin above range' => '748317',
])->throws(InvalidArgumentException::class);

test('it rejects pins outside the valid range', function (array $pins) {
    new State($pins);
})->with([
    'no pins' => [[]],
    'too low' => [[1, 0, 4]],
    'too high' => [[1, 8, 4]],
])->throws(InvalidArgumentException::class);

test('it applies a move to every pin', function () {
    $state = State::fromString('347317');
    $move = new Move('P2<', [+1, -1, -1, +1, +1, 0]);

    $next = $state->apply($move);

    expect($next)->not->toBeNull()
        ->and($next->pins)->toBe([4, 3, 6, 4, 2, 7])
        ->and($state->pins)->toBe([3, 4, 7, 3, 1, 7]);
});

test('it returns null for a move that pushes a pin below the minimum', function () {
    $state = State::fromString('1444');
    $move = new Move('P1<', [-1, 0, 0, 0]);

    expect($state->apply($move))->toBeNull()
        ->and($state->canApply($move))->toBeFalse();
});

test('it returns null for a move that pushes a pin above the maximum', function () {
    $state = State::fromString('7444');
    $move = new Move('P1>', [+1, 0, 0, 0]);

    expect($state->apply($move))->toBeNull()
        ->and($state->canApply($move))->toBeFalse();
});

test('it compares states by value', function () {
    expect(State::fromString('747317')->equals(State::fromString('747317')))->toBeTrue()
        ->and(State::fromString('747317')->equals(State::fromString('747311')))->toBeFalse();
});

test('it knows when it is solved', function () {
    expect(State::fromString('444444')->isSolved())->toBeTrue()
        ->and(State::fromString('444344')->isSolved())->toBeFalse()
        ->and(State::fromString('4')->isSolved())->toBeTrue();
});
