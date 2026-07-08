<?php

declare(strict_types=1);

use App\Gothic\LockCodec;
use Livewire\Livewire;

/**
 * The classic move set: P n > raises only plate n.
 *
 * @return list<list<int>>
 */
function singlePinMoveRows(int $pinCount): array
{
    $rows = [];

    for ($pin = 0; $pin < $pinCount; $pin++) {
        $delta = array_fill(0, $pinCount, 0);
        $delta[$pin] = 1;

        $rows[] = $delta;
    }

    return $rows;
}

test('the landing page renders the lock picker', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSeeLivewire('pages::lock-picker');
});

test('it starts without any prefilled values', function () {
    Livewire::test('pages::lock-picker')
        ->assertSet('startPins', '')
        ->assertSet('moves', [])
        ->assertSet('observationBefore', '')
        ->assertSee('Enter the start state first');
});

test('it creates one move row per plate when the start state is entered', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '7473')
        ->assertCount('moves', 4)
        ->assertSet('moves.0', [0, 0, 0, 0])
        ->assertSee('P1')
        ->assertSee('P4');
});

test('it solves the 747317 example with the shortest path', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '747317')
        ->set('moves', singlePinMoveRows(6))
        ->call('solve')
        ->assertHasNoErrors()
        ->assertSet('hasResult', true)
        ->assertSet('solvable', true)
        ->assertCount('solutionMoves', 13)
        ->assertSee('Statistics');
});

test('it rejects an invalid start state', function (string $startPins) {
    Livewire::test('pages::lock-picker')
        ->set('startPins', $startPins)
        ->call('solve')
        ->assertHasErrors('startPins')
        ->assertSet('hasResult', false);
})->with([
    'empty' => '',
    'letters' => '74a317',
    'digit out of range' => '748317',
    'too few plates' => '444',
    'too many plates' => '4444444',
]);

test('it requires at least one observed move', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '7473')
        ->call('solve')
        ->assertHasErrors('moves')
        ->assertSet('hasResult', false);
});

test('it rejects deltas outside -1..1', function (int $delta) {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '7473')
        ->set('moves.0.0', $delta)
        ->call('solve')
        ->assertHasErrors('moves.0.0')
        ->assertSet('hasResult', false);
})->with([
    'too high' => 2,
    'too low' => -2,
]);

test('it reports an impossible lock', function () {
    // The only move couples plates 1 and 2, so their difference never
    // changes and 4444 stays unreachable.
    Livewire::test('pages::lock-picker')
        ->set('startPins', '1444')
        ->set('moves.0', [1, 1, 0, 0])
        ->call('solve')
        ->assertHasNoErrors()
        ->assertSet('hasResult', true)
        ->assertSet('solvable', false)
        ->assertSee('This lock is impossible');
});

test('it generates the mirrored direction for every move', function () {
    // Only ">" (+1 on plate 1) is defined, so opening 7444 -> 4444 requires
    // the auto-generated mirrored "<" moves (-1 on plate 1) three times.
    Livewire::test('pages::lock-picker')
        ->set('startPins', '7444')
        ->set('moves.0', [1, 0, 0, 0])
        ->call('solve')
        ->assertHasNoErrors()
        ->assertSet('solvable', true)
        ->assertSet('solutionMoves', ['P1<', 'P1<', 'P1<']);
});

test('it rejects a move that does not raise its own plate', function (array $delta) {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->set('moves.0', $delta)
        ->call('solve')
        ->assertHasErrors('moves')
        ->assertSet('hasResult', false);
})->with([
    'own plate untouched' => [[0, 1, 0, 0]],
    'own plate lowered' => [[-1, 0, 0, 0]],
]);

test('it resizes the move rows when the plate count changes', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '7473')
        ->set('moves.0', [1, -1, 0, 0])
        ->set('startPins', '747317')
        ->assertCount('moves', 6)
        ->assertSet('moves.0', [1, -1, 0, 0, 0, 0])
        ->assertSet('moves.5', [0, 0, 0, 0, 0, 0])
        ->assertSet('observationBefore', '747317');
});

test('it cycles a delta cell through 0, +1 and -1', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->call('cycleDelta', 0, 1)
        // Touching a cell activates the move: the diagonal jumps to +1.
        ->assertSet('moves.0', [1, 1, 0, 0])
        ->call('cycleDelta', 0, 1)
        ->assertSet('moves.0', [1, -1, 0, 0])
        ->call('cycleDelta', 0, 1)
        ->assertSet('moves.0', [1, 0, 0, 0]);
});

test('the diagonal cell toggles the whole move on and off', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->call('cycleDelta', 1, 1)
        ->assertSet('moves.1', [0, 1, 0, 0])
        ->call('cycleDelta', 1, 0)
        ->assertSet('moves.1', [1, 1, 0, 0])
        // Toggling off clears the whole row.
        ->call('cycleDelta', 1, 1)
        ->assertSet('moves.1', [0, 0, 0, 0]);
});

test('it ignores delta cycling outside the grid', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->call('cycleDelta', 7, 0)
        ->call('cycleDelta', 0, 7)
        ->assertSet('moves.0', [0, 0, 0, 0]);
});

test('it toggles the observation direction', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->assertSet('observationDirection', '>')
        ->call('toggleObservationDirection')
        ->assertSet('observationDirection', '<')
        ->call('toggleObservationDirection')
        ->assertSet('observationDirection', '>');
});

test('it discovers a move from an observation', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '747317')
        ->set('observationPosition', 1)
        ->set('observationDirection', '>')
        ->set('observationBefore', '347317')
        ->set('observationAfter', '436427')
        ->call('discoverMove')
        ->assertHasNoErrors()
        ->assertSet('moves.0', [1, -1, -1, 1, 1, 0])
        // The next observation starts where the lock ended up...
        ->assertSet('observationBefore', '436427')
        ->assertSet('observationAfter', '')
        // ...and the next position is suggested right away.
        ->assertSet('observationPosition', 2)
        ->assertSet('observationDirection', '>');
});

test('it mirrors an observation made in the left direction', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->set('observationDirection', '<')
        ->set('observationBefore', '4444')
        ->set('observationAfter', '3544')
        ->call('discoverMove')
        ->assertHasNoErrors()
        ->assertSet('moves.0', [1, -1, 0, 0]);
});

test('it rejects observations whose plate count does not match the lock', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '747317')
        ->set('observationBefore', '4444')
        ->set('observationAfter', '4444')
        ->call('discoverMove')
        ->assertHasErrors('observationBefore')
        ->assertSet('moves.0', [0, 0, 0, 0, 0, 0]);
});

test('it rejects observations whose states have different plate counts', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '747317')
        ->set('observationBefore', '747317')
        ->set('observationAfter', '4444')
        ->call('discoverMove')
        ->assertHasErrors('observationAfter')
        ->assertSet('moves.0', [0, 0, 0, 0, 0, 0]);
});

test('it rejects observations where a plate jumps by more than one', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->set('observationBefore', '4444')
        ->set('observationAfter', '4644')
        ->call('discoverMove')
        ->assertHasErrors('observationAfter')
        ->assertSet('moves.0', [0, 0, 0, 0]);
});

test('it rejects an observation that fails the plausibility check', function (string $direction, string $after) {
    // P1> must raise plate 1 by exactly 1, P1< must lower it by exactly 1.
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->set('observationPosition', 1)
        ->set('observationDirection', $direction)
        ->set('observationBefore', '4444')
        ->set('observationAfter', $after)
        ->call('discoverMove')
        ->assertHasErrors('observationAfter')
        ->assertSet('moves.0', [0, 0, 0, 0]);
})->with([
    'right but own plate untouched' => ['>', '4544'],
    'right but own plate lowered' => ['>', '3544'],
    'left but own plate raised' => ['<', '5444'],
]);

test('it rejects an observation position outside the lock', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4443')
        ->set('observationPosition', 5)
        ->set('observationBefore', '4443')
        ->set('observationAfter', '4444')
        ->call('discoverMove')
        ->assertHasErrors('observationPosition');
});

test('it validates the observation inputs', function () {
    Livewire::test('pages::lock-picker')
        ->call('discoverMove')
        ->assertHasErrors(['observationBefore', 'observationAfter']);
});

test('it suggests the first observation once the start state is entered', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '3444')
        ->assertSet('observationPosition', 1)
        ->assertSet('observationDirection', '>');
});

test('it suggests the left direction when the plate already sits at 7', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '7473')
        ->assertSet('observationPosition', 1)
        ->assertSet('observationDirection', '<');
});

test('it suggests the next unobserved position after each discovered move', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '3474')
        ->assertSet('observationPosition', 1)
        ->set('observationAfter', '4474')
        ->call('discoverMove')
        ->assertSet('observationPosition', 2)
        ->assertSet('observationDirection', '>')
        ->set('observationAfter', '4574')
        ->call('discoverMove')
        // Plate 3 of 4574 sits at 7, so only "<" is possible.
        ->assertSet('observationPosition', 3)
        ->assertSet('observationDirection', '<');
});

test('it fills the lock from a share link', function () {
    $code = LockCodec::encode('3444', [[1, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]]);

    Livewire::withQueryParams(['lock' => $code])
        ->test('pages::lock-picker')
        ->assertSet('startPins', '3444')
        ->assertSet('moves', [[1, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]])
        ->assertSet('observationBefore', '3444')
        // Plate 1 is already observed, so plate 2 is suggested next.
        ->assertSet('observationPosition', 2)
        ->assertSee('Copy share link');
});

test('it pads a share link with fewer rows than plates', function () {
    Livewire::withQueryParams(['lock' => LockCodec::encode('3444', [[1, 0, 0, 0]])])
        ->test('pages::lock-picker')
        ->assertCount('moves', 4)
        ->assertSet('moves.1', [0, 0, 0, 0]);
});

test('it ignores an invalid share link', function () {
    Livewire::withQueryParams(['lock' => 'garbage!!!'])
        ->test('pages::lock-picker')
        ->assertSet('lockCode', '')
        ->assertSet('startPins', '')
        ->assertSet('moves', []);
});

test('it keeps the share code in sync while editing', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '3444')
        ->assertSet('lockCode', LockCodec::encode('3444', [[0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]]))
        ->set('moves.0.0', 1)
        ->assertSet('lockCode', LockCodec::encode('3444', [[1, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]]));
});

test('a discovered move becomes part of the share code', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '4444')
        ->set('observationBefore', '4444')
        ->set('observationAfter', '5344')
        ->call('discoverMove')
        ->assertSet('lockCode', LockCodec::encode('4444', [[1, -1, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]]));
});

test('it prepares the solution states for the animation', function () {
    Livewire::test('pages::lock-picker')
        ->set('startPins', '3444')
        ->set('moves.0', [1, 0, 0, 0])
        ->call('solve')
        ->assertHasNoErrors()
        ->assertSet('solutionMoves', ['P1>'])
        ->assertSet('solutionStates', [[3, 4, 4, 4], [4, 4, 4, 4]])
        ->assertSee('Play');
});
