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
        $actual = ranking_prize_results_from_podium_rows(800.0, $rows);
    } catch (Throwable $exception) {
        fail_test("{$label}: unexpected exception: {$exception->getMessage()}");
    }

    if ($actual['ranks'] !== $expected_ranks) {
        fail_test("{$label}: expected ranks " . json_encode($expected_ranks) . ', got ' . json_encode($actual['ranks']));
    }

    ksort($actual['prizes']);
    ksort($expected_prizes);

    if (array_keys($actual['prizes']) !== array_keys($expected_prizes)) {
        fail_test("{$label}: expected prize ranks " . json_encode(array_keys($expected_prizes)) . ', got ' . json_encode(array_keys($actual['prizes'])));
    }

    foreach ($expected_prizes as $rank => $expected_prize) {
        $actual_prize = $actual['prizes'][$rank] ?? null;

        if ($actual_prize === null || abs($actual_prize - $expected_prize) > 0.000001) {
            fail_test("{$label}: expected prize for rank {$rank} {$expected_prize}, got " . json_encode($actual_prize));
        }
    }
}

function expect_invalid_ranking_prizes(string $label, array $rows, string $expected_message_part): void
{
    try {
        ranking_prize_results_from_podium_rows(800.0, $rows);
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), $expected_message_part)) {
            fail_test("{$label}: unexpected exception message: {$exception->getMessage()}");
        }

        return;
    }

    fail_test("{$label}: expected InvalidArgumentException");
}

function natural_dead_heat_pool(float $total_prize, int $paid_places, int $place): float
{
    $total_weight = $paid_places * ($paid_places + 1) / 2;
    $weight = $paid_places - $place + 1;

    return $total_prize * $weight / $total_weight;
}

function expected_natural_dead_heat(int $first, int $second, int $third): array
{
    $counts = [$first, $second, $third];
    $paid_places = min(3, array_sum($counts));
    $ranks = [];
    $prizes = [];
    $occupied_places = 0;

    foreach ($counts as $count) {
        if ($count === 0) {
            continue;
        }

        $rank = $occupied_places + 1;
        $occupied_places += $count;

        if ($rank > $paid_places) {
            continue;
        }

        $consumed_pool = 0.0;

        for ($place = $rank; $place < $rank + $count && $place <= $paid_places; $place++) {
            $consumed_pool += natural_dead_heat_pool(800.0, $paid_places, $place);
        }

        $prizes[$rank] = $consumed_pool / $count;

        for ($player = 0; $player < $count; $player++) {
            $ranks[] = $rank;
        }
    }

    return [
        'ranks' => $ranks,
        'prizes' => $prizes,
    ];
}

function is_valid_ranking_combo(int $first, int $second, int $third): bool
{
    if ($first + $second + $third === 0) {
        return false;
    }

    if ($first === 0 && ($second > 0 || $third > 0)) {
        return false;
    }

    return !($second === 0 && $third > 0);
}

function expected_invalid_combo_message(int $first, int $second, int $third): string
{
    if ($first + $second + $third === 0) {
        return 'miejscu punktowanym';
    }

    if ($second === 0 && $third > 0) {
        return '2. grupa jest pusta';
    }

    if ($first === 0 && $second > 0) {
        return '1. grupa jest pusta';
    }

    return 'grupa jest pusta';
}

function expected_invalid_social_combo_message(int $first, int $second, int $third): string
{
    if ($first + $second + $third === 0) {
        return 'przynajmniej jednym miejscu';
    }

    if ($second === 0 && $third > 0) {
        return '2. miejsce jest puste';
    }

    if ($first === 0 && $second > 0) {
        return '1. miejsce jest puste';
    }

    return 'miejsce jest puste';
}

function payout_rows_difference_cents(float $total_prize, array $counts, array $prizes): int
{
    $rows = payout_rows_from_prizes($total_prize, $counts, $prizes);
    $seen_ranks = [];
    $actual_cents = payout_rows_total_cents($rows);

    foreach ($rows as $row) {
        if (isset($seen_ranks[$row['rank']])) {
            fail_test('duplicate payout row for rank ' . $row['rank']);
        }

        $seen_ranks[$row['rank']] = true;
    }

    return $actual_cents - money_to_cents($total_prize);
}

function gcd_int(int $left, int $right): int
{
    $left = abs($left);
    $right = abs($right);

    while ($right !== 0) {
        $next = $left % $right;
        $left = $right;
        $right = $next;
    }

    return $left;
}

function payout_count_gcd(array $counts): int
{
    $gcd = 0;

    foreach ($counts as $count) {
        $count = (int) $count;

        if ($count <= 0) {
            continue;
        }

        $gcd = $gcd === 0 ? $count : gcd_int($gcd, $count);
    }

    return $gcd;
}

function minimal_single_amount_difference_cents(float $total_prize, array $counts): int
{
    $gcd = payout_count_gcd($counts);

    if ($gcd === 0) {
        return 0;
    }

    $remainder = money_to_cents($total_prize) % $gcd;

    return min($remainder, $gcd - $remainder);
}

function expect_minimal_rounding_difference(string $label, float $total_prize, array $counts, array $prizes, ?int $expected_difference_cents = null): void
{
    $actual_difference = payout_rows_difference_cents($total_prize, $counts, $prizes);

    if ($expected_difference_cents !== null && $actual_difference !== $expected_difference_cents) {
        fail_test("{$label}: expected rounding difference {$expected_difference_cents} cents, got {$actual_difference} cents");
    }

    $minimal_difference = minimal_single_amount_difference_cents($total_prize, $counts);

    if (abs($actual_difference) !== $minimal_difference) {
        fail_test("{$label}: expected minimal rounding difference {$minimal_difference} cents, got {$actual_difference} cents");
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
expect_display_ranks('ranking display advances empty trailing groups', podium_test_rows(2, 0, 0), ['1', '3', '4'], MODE_RANKING);
expect_display_ranks('social display keeps consecutive places', podium_test_rows(2, 1, 2), ['1', '2', '3'], MODE_SOCIAL);
expect_invalid_rows('ranking rejects occupied group after an empty group', podium_test_rows(2, 0, 1), '2. grupa jest pusta', MODE_RANKING);
expect_invalid_rows('ranking rejects no paid places', podium_test_rows(0, 0, 0), 'miejscu punktowanym', MODE_RANKING);
expect_ranking_prizes('ranking dead heat splits all pool between two tied leaders when nobody is behind them', podium_test_rows(2, 0, 0), [1, 1], [1 => 400.0]);
expect_ranking_prizes('ranking dead heat uses natural 3-2-1 pools for full podium', podium_test_rows(2, 1, 2), [1, 1, 3], [1 => 800.0 * 5 / 12, 3 => 800.0 / 6]);
expect_ranking_prizes('ranking dead heat splits second and third natural pools', podium_test_rows(1, 2, 1), [1, 2, 2], [1 => 400.0, 2 => 200.0]);
expect_ranking_prizes('ranking dead heat splits all podium pools', podium_test_rows(3, 1, 0), [1, 1, 1], [1 => 800.0 / 3]);
expect_minimal_rounding_difference('social screenshot rounding closes exactly', 800.0, [1 => 10, 2 => 1, 3 => 1], [1 => 74.2268041237, 2 => 32.9896907216, 3 => 24.7422680412], 0);
expect_minimal_rounding_difference('ranking three tied leaders keeps one rank amount', 800.0, [1 => 3], [1 => 800.0 / 3], 1);

for ($first = 0; $first <= 20; $first++) {
    for ($second = 0; $second <= 20; $second++) {
        for ($third = 0; $third <= 20; $third++) {
            $label = "ranking dropdown combo {$first}/{$second}/{$third}";
            $rows = podium_test_rows($first, $second, $third);

            if (!is_valid_ranking_combo($first, $second, $third)) {
                expect_invalid_ranking_prizes($label, $rows, expected_invalid_combo_message($first, $second, $third));
                continue;
            }

            $expected = expected_natural_dead_heat($first, $second, $third);
            expect_ranking_prizes($label, $rows, $expected['ranks'], $expected['prizes']);
            expect_minimal_rounding_difference($label, 800.0, rank_counts($expected['ranks']), $expected['prizes']);
        }
    }
}

for ($first = 0; $first <= 20; $first++) {
    for ($second = 0; $second <= 20; $second++) {
        for ($third = 0; $third <= 20; $third++) {
            $label = "social dropdown combo {$first}/{$second}/{$third}";
            $rows = podium_test_rows($first, $second, $third);

            if (!is_valid_ranking_combo($first, $second, $third)) {
                expect_invalid_rows($label, $rows, expected_invalid_social_combo_message($first, $second, $third), MODE_SOCIAL);
                continue;
            }

            $ranks = ranks_from_podium_rows($rows, MODE_SOCIAL);
            $counts = rank_counts($ranks);
            $prizes = calculate_prizes(800.0, $ranks, [1 => 9, 2 => 4, 3 => 3]);
            expect_minimal_rounding_difference($label, 800.0, $counts, $prizes);
        }
    }
}

echo 'Podium row tests passed.' . PHP_EOL;
