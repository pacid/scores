<?php

declare(strict_types=1);

require_once __DIR__ . '/prize_calculator.php';

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
$podium_rows = $use_scenario_defaults || !isset($_POST['rank_slots'], $_POST['rank_counts'])
    ? podium_rows_from_ranks($scenario['ranks'])
    : normalize_podium_rows((array) $_POST['rank_slots'], (array) $_POST['rank_counts']);

$error = null;
$ranks = [];
$proportions = [];
$prizes = [];
$counts = [];
$total_paid = 0.0;

try {
    $total_prize_number = (float) str_replace(',', '.', (string) $total_prize);
    $ranks = ranks_from_podium_rows($podium_rows);
    $proportions = parse_proportions((string) $proportions_text);
    $prizes = calculate_prizes($total_prize_number, $ranks, $proportions);
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

function normalize_podium_rows(array $rank_slots, array $rank_counts): array
{
    $rows = [];

    for ($index = 0; $index < 3; $index++) {
        $rows[] = [
            'rank' => (string) ($rank_slots[$index] ?? ($index + 1)),
            'count' => (string) ($rank_counts[$index] ?? 0),
        ];
    }

    return $rows;
}

function ranks_from_podium_rows(array $rows): array
{
    $ranks = [];

    foreach ($rows as $row) {
        $rank = (string) ($row['rank'] ?? '');
        $count = (string) ($row['count'] ?? '');

        if (!ctype_digit($rank) || (int) $rank < 1) {
            throw new InvalidArgumentException("Nieprawidłowa wartość miejsca: {$rank}");
        }

        if (!ctype_digit($count)) {
            throw new InvalidArgumentException("Nieprawidłowa liczba osób: {$count}");
        }

        for ($player = 0; $player < (int) $count; $player++) {
            $ranks[] = (int) $rank;
        }
    }

    if ($ranks === []) {
        throw new InvalidArgumentException('Podaj liczbę osób przy przynajmniej jednym miejscu.');
    }

    return $ranks;
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
            margin-bottom: 26px;
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
            max-width: 760px;
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

        <div class="layout">
            <form class="panel" method="post">
                <input type="hidden" name="scenario" value="<?php echo e((string) $selected_scenario); ?>">

                <div class="field">
                    <label for="total_prize">Suma nagród</label>
                    <input id="total_prize" name="total_prize" type="number" step="0.01" min="0.01" value="<?php echo e((string) $total_prize); ?>" required>
                </div>

                <div class="podium" aria-label="Podium">
                    <?php foreach ($podium_rows as $index => $row): ?>
                        <div class="podium-row">
                            <div>
                                <label for="rank_slot_<?php echo $index; ?>">Miejsce</label>
                                <select id="rank_slot_<?php echo $index; ?>" name="rank_slots[]">
                                    <?php for ($rank_option = 1; $rank_option <= 3; $rank_option++): ?>
                                        <option value="<?php echo $rank_option; ?>" <?php echo (string) $row['rank'] === (string) $rank_option ? 'selected' : ''; ?>>
                                            <?php echo $rank_option; ?>. miejsce
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label for="rank_count_<?php echo $index; ?>">Liczba osób</label>
                                <select id="rank_count_<?php echo $index; ?>" name="rank_counts[]">
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
    </script>
</body>
</html>
