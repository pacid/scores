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
$ranks_text = $use_scenario_defaults ? $scenario['ranks'] : ($_POST['ranks'] ?? $scenario['ranks']);
$proportions_text = $use_scenario_defaults ? $scenario['proportions'] : ($_POST['proportions'] ?? $scenario['proportions']);

$error = null;
$ranks = [];
$proportions = [];
$prizes = [];
$counts = [];
$total_paid = 0.0;

try {
    $total_prize_number = (float) str_replace(',', '.', (string) $total_prize);
    $ranks = parse_ranks((string) $ranks_text);
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
?>
<!doctype html>
<html lang="en">
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
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: end;
            margin-bottom: 26px;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        h1 {
            font-size: clamp(2rem, 4vw, 4.3rem);
            line-height: 0.96;
            letter-spacing: 0;
            max-width: 760px;
        }

        .lead {
            color: var(--muted);
            font-size: 1rem;
            max-width: 440px;
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
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--ink);
            font: inherit;
            padding: 12px 13px;
        }

        textarea {
            min-height: 128px;
            resize: vertical;
        }

        .field {
            margin-bottom: 16px;
        }

        .hint {
            color: var(--muted);
            font-size: 0.86rem;
            margin-top: 6px;
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

        .results {
            display: grid;
            gap: 18px;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
            header,
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
    </style>
</head>
<body>
    <main class="page">
        <header>
            <div>
                <h1>Symulator podziału nagród</h1>
            </div>
            <p class="lead">Testuj remisy, różne pule nagród i własne wagi rang. Domyślnie: 1. miejsce 9, 2. miejsce 4, 3. miejsce 3.</p>
        </header>

        <div class="layout">
            <form class="panel" method="post">
                <h2>Przypadki</h2>
                <div class="scenario-grid">
                    <?php foreach ($scenarios as $key => $item): ?>
                        <button class="scenario <?php echo $selected_scenario === $key ? 'is-active' : ''; ?>" type="submit" name="load_scenario" value="<?php echo e($key); ?>">
                            <?php echo e($item['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="scenario" value="<?php echo e((string) $selected_scenario); ?>">

                <div class="field">
                    <label for="total_prize">Suma nagród</label>
                    <input id="total_prize" name="total_prize" type="number" step="0.01" min="0.01" value="<?php echo e((string) $total_prize); ?>" required>
                </div>

                <div class="field">
                    <label for="ranks">Rangi</label>
                    <textarea id="ranks" name="ranks" required><?php echo e((string) $ranks_text); ?></textarea>
                    <p class="hint">Użyj przecinków, spacji albo nowych linii. Przykład: 1, 1, 2, 3.</p>
                </div>

                <div class="field">
                    <label for="proportions">Proporcje rang</label>
                    <input id="proportions" name="proportions" value="<?php echo e((string) $proportions_text); ?>" required>
                    <p class="hint">Użyj par ranga:waga. Przykład: 1:9, 2:4, 3:3.</p>
                </div>

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
                        <div class="card metric">
                            <span>Wyliczona suma</span>
                            <strong><?php echo format_pln($total_paid); ?></strong>
                        </div>
                    </div>

                    <div class="card section">
                        <h2>Podział według rang</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Ranga</th>
                                    <th>Gracze</th>
                                    <th>Waga</th>
                                    <th>Nagroda na osobę</th>
                                    <th>Suma dla rangi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($counts as $rank => $count): ?>
                                    <tr>
                                        <td>Ranga <?php echo e((string) $rank); ?></td>
                                        <td><?php echo e((string) $count); ?></td>
                                        <td><?php echo e((string) $proportions[$rank]); ?></td>
                                        <td><?php echo format_pln($prizes[$rank]); ?></td>
                                        <td><?php echo format_pln($prizes[$rank] * $count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card section">
                        <h2>Gracze</h2>
                        <div class="players">
                            <?php foreach ($ranks as $player => $rank): ?>
                                <div class="player">
                                    <span>Gracz <?php echo $player + 1; ?> · Ranga <?php echo e((string) $rank); ?></span>
                                    <strong><?php echo format_pln($prizes[$rank]); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
