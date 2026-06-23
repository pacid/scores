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

function expect_ranks(string $label, array $rows, array $expected, string $mode = MODE_SOCIAL): void
{
    try {
        $actual = ranks_from_podium_rows($rows, $mode);
    } catch (Throwable $exception) {
        fail_test("{$label}: unexpected exception: {$exception->getMessage()}");
    }

    if ($actual !== $expected) {
        fail_test("{$label}: expected " . json_encode($expected) . ', got ' . json_encode($actual));
    }
}

function expect_invalid_rows(string $label, array $rows, string $expected_message_part, string $mode = MODE_SOCIAL): void
{
    try {
        ranks_from_podium_rows($rows, $mode);
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), $expected_message_part)) {
            fail_test("{$label}: unexpected exception message: {$exception->getMessage()}");
        }

        return;
    }

    fail_test("{$label}: expected InvalidArgumentException");
}

function expect_display_ranks(string $label, array $rows, array $expected, string $mode): void
{
    $actual = array_map(
        static fn (array $row): string => (string) $row['rank'],
        podium_rows_for_display($rows, $mode)
    );

    if ($actual !== $expected) {
        fail_test("{$label}: expected " . json_encode($expected) . ', got ' . json_encode($actual));
    }
}

function expect_ranking_prizes(string $label, array $rows, array $expected_ranks, array $expected_prizes): void
{
    try {
        $actual = ranking_prize_results_from_podium_rows(800.0, $rows, [1 => 9, 2 => 4, 3 => 3]);
    } catch (Throwable $exception) {
        fail_test("{$label}: unexpected exception: {$exception->getMessage()}");
    }

    if ($actual['ranks'] !== $expected_ranks) {
        fail_test("{$label}: expected ranks " . json_encode($expected_ranks) . ', got ' . json_encode($actual['ranks']));
    }

    foreach ($expected_prizes as $rank => $expected_prize) {
        $actual_prize = $actual['prizes'][$rank] ?? null;

        if ($actual_prize === null || abs($actual_prize - $expected_prize) > 0.000001) {
            fail_test("{$label}: expected prize for rank {$rank} {$expected_prize}, got " . json_encode($actual_prize));
        }
    }
}

expect_ranks('allows all players on first place', podium_test_rows(5, 0, 0), [1, 1, 1, 1, 1]);
expect_ranks('allows first and second place without third', podium_test_rows(2, 3, 0), [1, 1, 2, 2, 2]);
expect_ranks('allows a full podium', podium_test_rows(1, 1, 1), [1, 2, 3]);

expect_invalid_rows('rejects occupied second place without first', podium_test_rows(0, 1, 0), '1. miejsce jest puste');
expect_invalid_rows('rejects occupied third place after empty second', podium_test_rows(2, 0, 1), '2. miejsce jest puste');
expect_invalid_rows('rejects empty podium', podium_test_rows(0, 0, 0), 'przynajmniej jednym miejscu');

expect_ranks('ranking skips second place after two leaders', podium_test_rows(2, 1, 2), [1, 1, 3], MODE_RANKING);
expect_ranks('ranking skips third place after a second-place tie', podium_test_rows(1, 2, 1), [1, 2, 2], MODE_RANKING);
expect_ranks('ranking pays only the tied leaders when they fill the podium', podium_test_rows(3, 1, 0), [1, 1, 1], MODE_RANKING);
expect_display_ranks('ranking display follows bookmaker places', podium_test_rows(2, 1, 2), ['1', '3', '4'], MODE_RANKING);
expect_display_ranks('social display keeps consecutive places', podium_test_rows(2, 1, 2), ['1', '2', '3'], MODE_SOCIAL);
expect_invalid_rows('ranking rejects occupied group after an empty group', podium_test_rows(2, 0, 1), '2. grupa jest pusta', MODE_RANKING);
expect_invalid_rows('ranking rejects no paid places', podium_test_rows(0, 0, 0), 'miejscu punktowanym', MODE_RANKING);
expect_ranking_prizes('ranking dead heat splits first and second pools', podium_test_rows(2, 1, 2), [1, 1, 3], [1 => 325.0, 3 => 150.0]);
expect_ranking_prizes('ranking dead heat splits second and third pools', podium_test_rows(1, 2, 1), [1, 2, 2], [1 => 450.0, 2 => 175.0]);
expect_ranking_prizes('ranking dead heat splits all podium pools', podium_test_rows(3, 1, 0), [1, 1, 1], [1 => 800.0 / 3]);

echo 'Podium row tests passed.' . PHP_EOL;
