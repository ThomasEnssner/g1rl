<?php

declare(strict_types=1);

use App\Gothic\LockCodec;

test('it encodes a lock definition into a url safe string', function () {
    $code = LockCodec::encode('156262', [
        [1, -1, -1, 0, -1, -1],
        [0, 1, 0, 0, 0, 0],
    ]);

    expect($code)->toBe('156262~200100~121111');
});

test('it decodes a lock code back into the definition', function () {
    $decoded = LockCodec::decode('156262~200100~121111');

    expect($decoded['startPins'])->toBe('156262')
        ->and($decoded['rows'])->toBe([
            [1, -1, -1, 0, -1, -1],
            [0, 1, 0, 0, 0, 0],
        ]);
});

test('it round trips a lock without moves', function () {
    expect(LockCodec::decode(LockCodec::encode('7473', [])))
        ->toBe(['startPins' => '7473', 'rows' => []]);
});

test('it rejects invalid definitions when encoding', function (string $startPins, array $rows) {
    LockCodec::encode($startPins, $rows);
})->with([
    'invalid start state' => ['84', [[1, 0]]],
    'delta count mismatch' => ['44', [[1]]],
    'delta out of range' => ['44', [[2, 0]]],
])->throws(InvalidArgumentException::class);

test('it rejects invalid lock codes when decoding', function (string $code) {
    LockCodec::decode($code);
})->with([
    'empty' => '',
    'garbage' => 'garbage!!!',
    'invalid start state' => '84~21',
    'empty move segment' => '44~',
    'digit count mismatch' => '44~2',
    'invalid delta digit' => '44~2z',
])->throws(InvalidArgumentException::class);
