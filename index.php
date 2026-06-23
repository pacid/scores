<?php

declare(strict_types=1);

require_once __DIR__ . '/prize_calculator.php';

const MODE_SOCIAL = 'social';
const MODE_RANKING = 'ranking';

$scenarios = [
    'original' => [
        'label' => 'Przypadek bazowy',
        'total' => '800',
        'ranks' => '1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 3, 3',
        'proportions' => '1:9, 2:4, 3:3',
    ],
    'crowded_first' => [
        'label' => 'Dużo pierwszych miejsc',
        'total' => '800',
        'ranks' => '1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 3, 3',
        'proportions' => '1:9, 2:4, 3:3',
    ],
    'single_winners' => [
        'label' => 'Klasyczne podium',
        'total' => '800',
        'ranks' => '1, 2, 3',
        'proportions' => '1:9, 2:4, 3:3',
    ],
    'larger_pool' => [
        'label' => 'Większa pula nagród',
        'total' => '1500',
        'ranks' => '1, 1, 2, 2, 2, 3, 3, 3, 3',
        'proportions' => '1:9, 2:4, 3:3',
    ],
];

$use_scenario_defaults = isset($_POST['load_scenario']);
$selected_scenario = $_POST['load_scenario'] ?? $_POST['scenario'] ?? 'original';
$scenario = $scenarios[$selected_scenario] ?? $scenarios['original'];

$total_prize = $use_scenario_defaults ? $scenario['total'] : ($_POST['total_prize'] ?? $scenario['total']);
$proportions_text = $use_scenario_defaults ? $scenario['proportions'] : ($_POST['proportions'] ?? $scenario['proportions']);
$mode = normalize_mode((string) ($_POST['mode'] ?? MODE_SOCIAL));
$podium_rows = $use_scenario_defaults || !isset($_POST['rank_counts'])
    ? podium_rows_from_ranks($scenario['ranks'])
    : normalize_podium_rows((array) $_POST['rank_counts']);
$display_podium_rows = podium_rows_for_display($podium_rows, $mode);

$error = null;
$ranks = [];
$proportions = [];
$prizes = [];
$counts = [];
$total_paid = 0.0;

try {
    $total_prize_number = (float) str_replace(',', '.', (string) $total_prize);
    $proportions = parse_proportions((string) $proportions_text);

    if ($mode === MODE_RANKING) {
        $ranking_results = ranking_prize_results_from_podium_rows($total_prize_number, $podium_rows, $proportions);
        $ranks = $ranking_results['ranks'];
        $prizes = $ranking_results['prizes'];
    } else {
        $ranks = ranks_from_podium_rows($podium_rows, $mode);
        $prizes = calculate_prizes($total_prize_number, $ranks, $proportions);
    }

    $counts = rank_counts($ranks);

    foreach ($ranks as $rank) {
        $total_paid += $prizes[$rank];
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function persons_label(int $count): string
{
    if ($count === 1) {
        return 'osoba';
    }

    $last_digit = $count % 10;
    $last_two_digits = $count % 100;

    if ($last_digit >= 2 && $last_digit <= 4 && ($last_two_digits < 12 || $last_two_digits > 14)) {
        return 'osoby';
    }

    return 'osób';
}

function normalize_mode(string $mode): string
{
    return $mode === MODE_RANKING ? MODE_RANKING : MODE_SOCIAL;
}

function podium_rows_from_ranks(string $ranks_text): array
{
    $counts = rank_counts(parse_ranks($ranks_text));
    $rows = [];

    for ($rank = 1; $rank <= 3; $rank++) {
        $rows[] = [
            'rank' => (string) $rank,
            'count' => (string) ($counts[$rank] ?? 0),
        ];
    }

    return $rows;
}

function normalize_podium_rows(array $rank_counts): array
{
    $rows = [];

    for ($index = 0; $index < 3; $index++) {
        $rows[] = [
            'rank' => (string) ($index + 1),
            'count' => (string) ($rank_counts[$index] ?? 0),
        ];
    }

    return $rows;
}

function podium_rows_for_display(array $rows, string $mode): array
{
    if ($mode !== MODE_RANKING) {
        return $rows;
    }

    $display_rows = [];
    $occupied_places = 0;

    foreach ($rows as $row) {
        $count = (int) ($row['count'] ?? 0);
        $row['rank'] = (string) ($occupied_places + 1);
        $display_rows[] = $row;
        $occupied_places += max(0, $count);
    }

    return $display_rows;
}

function ranks_from_podium_rows(array $rows, string $mode = MODE_SOCIAL): array
{
    if ($mode === MODE_RANKING) {
        return ranking_ranks_from_podium_rows($rows);
    }

    $ranks = [];
    $previous_empty_rank = null;

    foreach ($rows as $row) {
        $rank = (string) ($row['rank'] ?? '');
        $count = (string) ($row['count'] ?? '');

        if (!ctype_digit($rank) || (int) $rank < 1) {
            throw new InvalidArgumentException("Nieprawidłowa wartość miejsca: {$rank}");
        }

        if (!ctype_digit($count) || (int) $count < 0) {
            throw new InvalidArgumentException("Nieprawidłowa liczba osób: {$count}");
        }

        $rank_number = (int) $rank;
        $count_number = (int) $count;

        if ($count_number > 0 && $previous_empty_rank !== null) {
            throw new InvalidArgumentException("Nie można dodać graczy na {$rank_number}. miejscu, jeśli {$previous_empty_rank}. miejsce jest puste.");
        }

        if ($count_number === 0) {
            $previous_empty_rank = $rank_number;
        }

        for ($player = 0; $player < $count_number; $player++) {
            $ranks[] = $rank_number;
        }
    }

    if ($ranks === []) {
        throw new InvalidArgumentException('Podaj liczbę osób przy przynajmniej jednym miejscu.');
    }

    return $ranks;
}

function ranking_ranks_from_podium_rows(array $rows): array
{
    $ranks = [];
    $previous_empty_group = null;
    $occupied_places = 0;

    foreach ($rows as $index => $row) {
        $count = (string) ($row['count'] ?? '');
        $group_number = $index + 1;

        if (!ctype_digit($count) || (int) $count < 0) {
            throw new InvalidArgumentException("Nieprawidłowa liczba osób: {$count}");
        }

        $count_number = (int) $count;

        if ($count_number > 0 && $previous_empty_group !== null) {
            throw new InvalidArgumentException("Nie można dodać graczy w {$group_number}. grupie, jeśli {$previous_empty_group}. grupa jest pusta.");
        }

        if ($count_number === 0) {
            $previous_empty_group = $group_number;
            continue;
        }

        $rank_number = $occupied_places + 1;
        $occupied_places += $count_number;

        if ($rank_number > 3) {
            continue;
        }

        for ($player = 0; $player < $count_number; $player++) {
            $ranks[] = $rank_number;
        }
    }

    if ($ranks === []) {
        throw new InvalidArgumentException('Podaj liczbę osób przy przynajmniej jednym miejscu punktowanym.');
    }

    return $ranks;
}

function ranking_prize_results_from_podium_rows(float $total_prize, array $rows, array $proportions): array
{
    if ($total_prize <= 0) {
        throw new InvalidArgumentException('Suma nagród musi być większa od zera.');
    }

    $pools = ranking_prize_pools($total_prize, $proportions);
    $ranks = [];
    $prizes = [];
    $previous_empty_group = null;
    $occupied_places = 0;

    foreach ($rows as $index => $row) {
        $count = (string) ($row['count'] ?? '');
        $group_number = $index + 1;

        if (!ctype_digit($count) || (int) $count < 0) {
            throw new InvalidArgumentException("Nieprawidłowa liczba osób: {$count}");
        }

        $count_number = (int) $count;

        if ($count_number > 0 && $previous_empty_group !== null) {
            throw new InvalidArgumentException("Nie można dodać graczy w {$group_number}. grupie, jeśli {$previous_empty_group}. grupa jest pusta.");
        }

        if ($count_number === 0) {
            $previous_empty_group = $group_number;
            continue;
        }

        $rank_number = $occupied_places + 1;
        $occupied_places += $count_number;

        if ($rank_number > 3) {
            continue;
        }

        $consumed_pool = 0.0;

        for ($place = $rank_number; $place < $rank_number + $count_number && $place <= 3; $place++) {
            $consumed_pool += $pools[$place];
        }

        if ($consumed_pool <= 0) {
            continue;
        }

        $prizes[$rank_number] = $consumed_pool / $count_number;

        for ($player = 0; $player < $count_number; $player++) {
            $ranks[] = $rank_number;
        }
    }

    if ($ranks === []) {
        throw new InvalidArgumentException('Podaj liczbę osób przy przynajmniej jednym miejscu punktowanym.');
    }

    return [
        'ranks' => $ranks,
        'prizes' => $prizes,
    ];
}

function ranking_prize_pools(float $total_prize, array $proportions): array
{
    $total_weight = 0.0;

    for ($place = 1; $place <= 3; $place++) {
        if (!isset($proportions[$place])) {
            throw new InvalidArgumentException("Brakuje proporcji dla miejsca {$place}.");
        }

        $total_weight += $proportions[$place];
    }

    if ($total_weight <= 0) {
        throw new InvalidArgumentException('Suma proporcji musi być większa od zera.');
    }

    $pools = [];

    for ($place = 1; $place <= 3; $place++) {
        $pools[$place] = $total_prize * $proportions[$place] / $total_weight;
    }

    return $pools;
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Symulator podziału nagród</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f7f9;
            --surface: #ffffff;
            --surface-soft: #eef6f2;
            --ink: #172026;
            --muted: #64717c;
            --line: #dbe1e7;
            --accent: #24745a;
            --accent-strong: #165a44;
            --warn-bg: #fff2e3;
            --warn: #8b3f05;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 36px 20px 48px;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        h1 {
            font-size: clamp(2rem, 4vw, 3.8rem);
            line-height: 1;
            letter-spacing: 0;
            flex: 1;
            max-width: none;
        }

        .mode-toggle {
            display: inline-grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px;
            width: min(100%, 430px);
            margin-bottom: 26px;
            padding: 4px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
        }

        .mode-toggle label {
            color: inherit;
            display: block;
            font-size: inherit;
            font-weight: inherit;
            margin: 0;
            text-transform: none;
        }

        .mode-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mode-toggle span {
            align-items: center;
            border-radius: 6px;
            color: var(--muted);
            cursor: pointer;
            display: flex;
            font-weight: 800;
            justify-content: center;
            min-height: 40px;
            padding: 8px 12px;
            text-align: center;
        }

        .mode-toggle input:checked + span {
            background: var(--accent);
            color: #fff;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(320px, 410px) minmax(0, 1fr);
            gap: 22px;
            align-items: start;
        }

        .panel,
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 14px 35px rgba(23, 32, 38, 0.06);
        }

        .panel {
            padding: 20px;
        }

        .settings-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
            color: var(--ink);
            cursor: pointer;
        }

        .settings-button:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .settings-button svg {
            width: 22px;
            height: 22px;
        }

        .scenario-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 16px 0 22px;
        }

        .scenario {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            min-height: 58px;
            padding: 10px 12px;
            text-align: left;
            font: inherit;
        }

        .scenario.is-active,
        .scenario:hover {
            border-color: var(--accent);
            background: var(--surface-soft);
        }

        label {
            display: block;
            color: var(--muted);
            font-size: 0.83rem;
            font-weight: 700;
            margin-bottom: 7px;
            text-transform: uppercase;
        }

        input,
        select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            padding: 12px 13px;
        }

        select {
            cursor: pointer;
        }

        .field {
            margin-bottom: 16px;
        }

        .podium {
            display: grid;
            gap: 12px;
            margin-bottom: 18px;
        }

        .podium-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 12px;
        }

        .podium-place {
            align-items: center;
            background: #fbfcfd;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--ink);
            display: flex;
            font-weight: 800;
            min-height: 48px;
            padding: 12px 13px;
        }

        .podium-place.is-outside-podium {
            color: var(--muted);
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 48px;
            border: 0;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
        }

        .button:hover {
            background: var(--accent-strong);
        }

        dialog {
            width: min(560px, calc(100vw - 32px));
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(23, 32, 38, 0.22);
            color: var(--ink);
            padding: 0;
        }

        dialog::backdrop {
            background: rgba(23, 32, 38, 0.34);
        }

        .dialog-content {
            padding: 20px;
        }

        .dialog-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .dialog-header h2 {
            font-size: 1.15rem;
        }

        .close-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            font-size: 1.25rem;
            line-height: 1;
        }

        .close-button:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .results {
            display: grid;
            gap: 18px;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .metric {
            padding: 16px;
        }

        .metric span {
            color: var(--muted);
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .metric strong {
            display: block;
            font-size: clamp(1.4rem, 3vw, 2.1rem);
            line-height: 1.1;
            margin-top: 8px;
        }

        .section {
            padding: 18px;
        }

        .section h2 {
            font-size: 1.15rem;
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 11px 8px;
            text-align: right;
            white-space: nowrap;
        }

        th:first-child,
        td:first-child {
            text-align: left;
        }

        th {
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .players {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 10px;
        }

        .player {
            background: #fbfcfd;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
        }

        .player strong,
        .player span {
            display: block;
        }

        .player span {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .error {
            background: var(--warn-bg);
            border: 1px solid #f2c08a;
            border-radius: 8px;
            color: var(--warn);
            padding: 14px 16px;
        }

        @media (max-width: 860px) {
            .layout,
            .metrics {
                grid-template-columns: 1fr;
            }

            .scenario-grid {
                grid-template-columns: 1fr;
            }

            th,
            td {
                white-space: normal;
            }
        }

        @media (max-width: 520px) {
            header {
                align-items: start;
            }

            .podium-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <header>
            <h1>Symulator podziału nagród</h1>
            <button class="settings-button" type="button" aria-label="Ustawienia" data-open-settings>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"></path>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 8.92 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82 1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path>
                </svg>
            </button>
        </header>

        <div class="mode-toggle" role="radiogroup" aria-label="Tryb algorytmu">
            <label>
                <input type="radio" name="mode" value="<?php echo MODE_SOCIAL; ?>" form="simulator-form" <?php echo $mode === MODE_SOCIAL ? 'checked' : ''; ?> data-mode-option>
                <span>Tryb towarzyski</span>
            </label>
            <label>
                <input type="radio" name="mode" value="<?php echo MODE_RANKING; ?>" form="simulator-form" <?php echo $mode === MODE_RANKING ? 'checked' : ''; ?> data-mode-option>
                <span>Tryb rankingowy</span>
            </label>
        </div>

        <div class="layout">
            <form id="simulator-form" class="panel" method="post">
                <input type="hidden" name="scenario" value="<?php echo e((string) $selected_scenario); ?>">

                <div class="field">
                    <label for="total_prize">Suma nagród</label>
                    <input id="total_prize" name="total_prize" type="number" step="0.01" min="0.01" value="<?php echo e((string) $total_prize); ?>" required>
                </div>

                <div class="podium" aria-label="Podium">
                    <?php foreach ($display_podium_rows as $index => $row): ?>
                        <div class="podium-row">
                            <div>
                                <label>Miejsce</label>
                                <div class="podium-place <?php echo (int) $row['rank'] > 3 ? 'is-outside-podium' : ''; ?>" data-place-label><?php echo e((string) $row['rank']); ?>. miejsce</div>
                            </div>
                            <div>
                                <label for="rank_count_<?php echo $index; ?>">Liczba osób</label>
                                <select id="rank_count_<?php echo $index; ?>" name="rank_counts[]" data-rank-count>
                                    <?php for ($count_option = 0; $count_option <= 20; $count_option++): ?>
                                        <option value="<?php echo $count_option; ?>" <?php echo (string) $row['count'] === (string) $count_option ? 'selected' : ''; ?>>
                                            <?php echo $count_option; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <dialog id="settings-dialog">
                    <div class="dialog-content">
                        <div class="dialog-header">
                            <h2>Ustawienia</h2>
                            <button class="close-button" type="button" aria-label="Zamknij" data-close-settings>&times;</button>
                        </div>

                        <h3>Przypadki</h3>
                        <div class="scenario-grid">
                            <?php foreach ($scenarios as $key => $item): ?>
                                <button class="scenario <?php echo $selected_scenario === $key ? 'is-active' : ''; ?>" type="submit" name="load_scenario" value="<?php echo e($key); ?>">
                                    <?php echo e($item['label']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="field">
                            <label for="proportions">Proporcje rang</label>
                            <input id="proportions" name="proportions" value="<?php echo e((string) $proportions_text); ?>" required>
                        </div>
                    </div>
                </dialog>

                <button class="button" type="submit">Uruchom symulację</button>
            </form>

            <section class="results" aria-live="polite">
                <?php if ($error !== null): ?>
                    <div class="error"><?php echo e($error); ?></div>
                <?php else: ?>
                    <div class="metrics">
                        <div class="card metric">
                            <span>Suma nagród</span>
                            <strong><?php echo format_pln((float) $total_prize_number); ?></strong>
                        </div>
                        <div class="card metric">
                            <span>Gracze</span>
                            <strong><?php echo count($ranks); ?></strong>
                        </div>
                    </div>

                    <div class="card section">
                        <h2>Wyniki</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Miejsce</th>
                                    <th>Wygrana na osobę</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($counts as $rank => $count): ?>
                                    <tr>
                                        <td><?php echo e((string) $count); ?> <?php echo persons_label((int) $count); ?> na msc. <?php echo e((string) $rank); ?></td>
                                        <td><?php echo format_pln($prizes[$rank]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <script>
        const settingsDialog = document.querySelector('#settings-dialog');
        const openSettings = document.querySelector('[data-open-settings]');
        const closeSettings = document.querySelector('[data-close-settings]');

        openSettings?.addEventListener('click', () => {
            settingsDialog?.showModal();
        });

        closeSettings?.addEventListener('click', () => {
            settingsDialog?.close();
        });

        settingsDialog?.addEventListener('click', (event) => {
            if (event.target === settingsDialog) {
                settingsDialog.close();
            }
        });

        const rankCountSelects = Array.from(document.querySelectorAll('[data-rank-count]'));
        const placeLabels = Array.from(document.querySelectorAll('[data-place-label]'));
        const modeOptions = Array.from(document.querySelectorAll('[data-mode-option]'));

        function selectedMode() {
            return modeOptions.find((option) => option.checked)?.value ?? '<?php echo MODE_SOCIAL; ?>';
        }

        function syncPlaceLabels() {
            let occupiedPlaces = 0;
            const isRankingMode = selectedMode() === '<?php echo MODE_RANKING; ?>';

            placeLabels.forEach((label, index) => {
                const rank = isRankingMode ? occupiedPlaces + 1 : index + 1;
                label.textContent = `${rank}. miejsce`;
                label.classList.toggle('is-outside-podium', rank > 3);
                occupiedPlaces += Number.parseInt(rankCountSelects[index]?.value ?? '0', 10);
            });
        }

        function syncRankCountSelects() {
            rankCountSelects.forEach((select, index) => {
                const previousSelect = rankCountSelects[index - 1];
                const shouldDisable = index > 0 && previousSelect?.value === '0';

                if (shouldDisable) {
                    select.value = '0';
                }

                select.disabled = shouldDisable;
            });

            syncPlaceLabels();
        }

        rankCountSelects.forEach((select) => {
            select.addEventListener('change', syncRankCountSelects);
        });

        modeOptions.forEach((option) => {
            option.addEventListener('change', syncRankCountSelects);
        });

        syncRankCountSelects();
    </script>
</body>
</html>
