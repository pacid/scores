<?php

declare(strict_types=1);

ob_start();
require dirname(__DIR__) . '/index.php';
ob_end_clean();

function podium_test_rows(int $first, int $second, int $third): array
{
    return [
        ['rank' => '1', 'count' => (string) $first],
        ['rank' => '2', 'count' => (string) $second],
        ['rank' => '3', 'count' => (string) $third],
    ];
}

function fail_test(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expect_ranks(string $label, array $rows, array $expected): void
{
    try {
        $actual = ranks_from_podium_rows($rows);
    } catch (Throwable $exception) {
        fail_test("{$label}: unexpected exception: {$exception->getMessage()}");
    }

    if ($actual !== $expected) {
        fail_test("{$label}: expected " . json_encode($expected) . ', got ' . json_encode($actual));
    }
}

function expect_invalid_rows(string $label, array $rows, string $expected_message_part): void
{
    try {
        ranks_from_podium_rows($rows);
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), $expected_message_part)) {
            fail_test("{$label}: unexpected exception message: {$exception->getMessage()}");
        }

        return;
    }

    fail_test("{$label}: expected InvalidArgumentException");
}

expect_ranks('allows all players on first place', podium_test_rows(5, 0, 0), [1, 1, 1, 1, 1]);
expect_ranks('allows first and second place without third', podium_test_rows(2, 3, 0), [1, 1, 2, 2, 2]);
expect_ranks('allows a full podium', podium_test_rows(1, 1, 1), [1, 2, 3]);

expect_invalid_rows('rejects occupied second place without first', podium_test_rows(0, 1, 0), '1. miejsce jest puste');
expect_invalid_rows('rejects occupied third place after empty second', podium_test_rows(2, 0, 1), '2. miejsce jest puste');
expect_invalid_rows('rejects empty podium', podium_test_rows(0, 0, 0), 'przynajmniej jednym miejscu');

echo 'Podium row tests passed.' . PHP_EOL;
