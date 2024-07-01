<?php
function calculateRewards($reward1, $reward2, $reward3, $players) {
    // Initialize arrays for storing players based on their ranks
    $rank1 = [];
    $rank2 = [];
    $rank3 = [];

    // Distribute players based on their rank
    foreach ($players as $player) {
        if ($player['rank'] == 1) {
            $rank1[] = $player;
        } elseif ($player['rank'] == 2) {
            $rank2[] = $player;
        } elseif ($player['rank'] == 3) {
            $rank3[] = $player;
        }
    }

    // Calculate the reward per person for each rank
    $rewardPerPerson1 = $reward1 / count($rank1);
    $rewardPerPerson2 = $reward2 / count($rank2);
    $rewardPerPerson3 = $reward3 / count($rank3);

    // Adjust rewards to maintain the 30% rule
    $isCriteriaMet = false;
    while (!$isCriteriaMet) {
        $isCriteriaMet = true;

        // Check and adjust the reward ratio between rank 1 and rank 2
        if (!($rewardPerPerson1 > $rewardPerPerson2 * 1.3)) {
            $rewardPerPerson1 += $rewardPerPerson2 * 0.2 + $rewardPerPerson3 * 0.1;
            $rewardPerPerson2 -= $rewardPerPerson2 * 0.2;
            $rewardPerPerson3 -= $rewardPerPerson3 * 0.1;
            $isCriteriaMet = false;
        }

        // Check and adjust the reward ratio between rank 2 and rank 3
        if (!($rewardPerPerson2 > $rewardPerPerson3 * 1.3)) {
            $rewardPerPerson2 += $rewardPerPerson3 * 0.1;
            $rewardPerPerson3 -= $rewardPerPerson3 * 0.1;
            $isCriteriaMet = false;
        }
    }

    // Assign rewards to players
    foreach ($rank1 as &$player) {
        $player['reward'] = round($rewardPerPerson1, 2);
    }
    foreach ($rank2 as &$player) {
        $player['reward'] = round($rewardPerPerson2, 2);
    }
    foreach ($rank3 as &$player) {
        $player['reward'] = round($rewardPerPerson3, 2);
    }

    // Merge all players back into one array
    $allPlayers = array_merge($rank1, $rank2, $rank3);

    return $allPlayers;
}

// Sample data
$reward1 = 450; // Reward for 1st place
$reward2 = 200; // Initial reward for 2nd place
$reward3 = 150; // Initial reward for 3rd place

$players = [
    ['name' => 'Player1', 'rank' => 1],
    ['name' => 'Player2', 'rank' => 1],
    ['name' => 'Player3', 'rank' => 1],
    ['name' => 'Player4', 'rank' => 1],
    ['name' => 'Player5', 'rank' => 1],
    ['name' => 'Player6', 'rank' => 1],
    ['name' => 'Player7', 'rank' => 1],
    ['name' => 'Player8', 'rank' => 1],
    ['name' => 'Player9', 'rank' => 1],
    ['name' => 'Player10', 'rank' => 1],
    ['name' => 'Player11', 'rank' => 1],
    ['name' => 'Player12', 'rank' => 1],
    ['name' => 'Player13', 'rank' => 1],
    ['name' => 'Player14', 'rank' => 2],
    ['name' => 'Player15', 'rank' => 2],
    ['name' => 'Player16', 'rank' => 3],
    ['name' => 'Player17', 'rank' => 3]
];

$rewardedPlayers = calculateRewards($reward1, $reward2, $reward3, $players);

// Display the results
foreach ($rewardedPlayers as $player) {
    echo "Name: {$player['name']}, Rank: {$player['rank']}, Reward: {$player['reward']} PLN\n";
}
?>
