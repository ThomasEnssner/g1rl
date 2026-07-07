<?php

declare(strict_types=1);

namespace App\Gothic;

use InvalidArgumentException;

/**
 * Serializes a lock definition into a compact, URL-safe string and back.
 * Move names are fixed by position (P1, P2, ...), so only the ">" direction
 * deltas are stored: one row per plate, one digit per plate, offset by +1
 * so the game's delta range -1..1 maps to 0..2.
 *
 * Format: startPins~digits~digits...  Example: 156262~200100~121111
 */
final readonly class LockCodec
{
    private const string DIGITS = '012';

    private const int OFFSET = 1;

    /**
     * @param  list<array<int, int>>  $rows
     */
    public static function encode(string $startPins, array $rows): string
    {
        if (! self::isValidStartState($startPins)) {
            throw new InvalidArgumentException(sprintf('Invalid start state "%s".', $startPins));
        }

        $parts = [$startPins];

        foreach ($rows as $index => $delta) {
            if (count($delta) !== strlen($startPins)) {
                throw new InvalidArgumentException(sprintf(
                    'Move P%d has %d delta entries but the lock has %d plates.',
                    $index + 1,
                    count($delta),
                    strlen($startPins),
                ));
            }

            $digits = '';

            foreach ($delta as $value) {
                $digit = $value + self::OFFSET;

                if ($digit < 0 || $digit >= strlen(self::DIGITS)) {
                    throw new InvalidArgumentException(sprintf('Delta %d of move P%d is out of range.', $value, $index + 1));
                }

                $digits .= self::DIGITS[$digit];
            }

            $parts[] = $digits;
        }

        return implode('~', $parts);
    }

    /**
     * @return array{startPins: string, rows: list<list<int>>}
     */
    public static function decode(string $code): array
    {
        $parts = explode('~', $code);
        $startPins = array_shift($parts);

        if (! self::isValidStartState($startPins)) {
            throw new InvalidArgumentException(sprintf('Invalid start state in lock code "%s".', $code));
        }

        $rows = [];

        foreach ($parts as $part) {
            if (strlen($part) !== strlen($startPins)) {
                throw new InvalidArgumentException(sprintf('Move segment "%s" does not match the plate count.', $part));
            }

            $delta = [];

            foreach (str_split($part) as $digit) {
                $index = strpos(self::DIGITS, $digit);

                if ($index === false) {
                    throw new InvalidArgumentException(sprintf('Invalid delta digit "%s" in lock code.', $digit));
                }

                $delta[] = $index - self::OFFSET;
            }

            $rows[] = $delta;
        }

        return ['startPins' => $startPins, 'rows' => $rows];
    }

    private static function isValidStartState(string $startPins): bool
    {
        return preg_match('/^[1-7]{1,8}$/', $startPins) === 1;
    }
}
