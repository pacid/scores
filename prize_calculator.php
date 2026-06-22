<?php

declare(strict_types=1);

function calculate_prizes(float $total_prize, array $ranks, array $proportions = [1 => 9, 2 => 4, 3 => 3]): array
{
    if ($total_prize <= 0) {
        throw new InvalidArgumentException('Suma nagród musi być większa od zera.');
    }

    if ($ranks === []) {
        throw new InvalidArgumentException('Podaj rangę przynajmniej jednego gracza.');
    }

    $rank_counts = [];

    foreach ($ranks as $rank) {
        $rank = (int) $rank;

        if (!isset($proportions[$rank])) {
            throw new InvalidArgumentException("Brakuje proporcji dla rangi {$rank}.");
        }

        if (!isset($rank_counts[$rank])) {
            $rank_counts[$rank] = 0;
        }

        $rank_counts[$rank]++;
    }

    ksort($rank_counts);

    $total_proportion_sum = 0;

    foreach ($rank_counts as $rank => $count) {
        $total_proportion_sum += $proportions[$rank] * $count;
    }

    if ($total_proportion_sum <= 0) {
        throw new InvalidArgumentException('Suma proporcji musi być większa od zera.');
    }

    $k = $total_prize / $total_proportion_sum;
    $prizes = [];

    foreach ($rank_counts as $rank => $count) {
        $prizes[$rank] = $proportions[$rank] * $k;
    }

    return $prizes;
}

function parse_ranks(string $value): array
{
    $items = preg_split('/[\s,;]+/', trim($value));
    $ranks = [];

    foreach ($items ?: [] as $item) {
        if ($item === '') {
            continue;
        }

        if (!ctype_digit($item)) {
            throw new InvalidArgumentException("Nieprawidłowa wartość rangi: {$item}");
        }

        $ranks[] = (int) $item;
    }

    return $ranks;
}

function parse_proportions(string $value): array
{
    $items = preg_split('/[\s,;]+/', trim($value));
    $proportions = [];

    foreach ($items ?: [] as $item) {
        if ($item === '') {
            continue;
        }

        if (!str_contains($item, ':')) {
            throw new InvalidArgumentException("Nieprawidłowa proporcja: {$item}. Użyj formatu ranga:waga.");
        }

        [$rank, $weight] = array_map('trim', explode(':', $item, 2));

        if (!ctype_digit($rank) || !is_numeric($weight) || (float) $weight <= 0) {
            throw new InvalidArgumentException("Nieprawidłowa proporcja: {$item}. Użyj dodatnich wartości, np. 1:9.");
        }

        $proportions[(int) $rank] = (float) $weight;
    }

    if ($proportions === []) {
        throw new InvalidArgumentException('Podaj przynajmniej jedną proporcję dla rangi.');
    }

    ksort($proportions);

    return $proportions;
}

function rank_counts(array $ranks): array
{
    $counts = [];

    foreach ($ranks as $rank) {
        if (!isset($counts[$rank])) {
            $counts[$rank] = 0;
        }

        $counts[$rank]++;
    }

    ksort($counts);

    return $counts;
}

function format_pln(float $amount): string
{
    return number_format($amount, 2, '.', ' ') . ' PLN';
}
