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
        $player['reward'] = $rewardPerPerson1;
    }
    foreach ($rank2 as &$player) {
        $player['reward'] = $rewardPerPerson2;
    }
    foreach ($rank3 as &$player) {
        $player['reward'] = $rewardPerPerson3;
    }

    // Merge all players back into one array
    $allPlayers = array_merge($rank1, $rank2, $rank3);

    return $allPlayers;
}

// Sample data
$reward1 = 100; // Reward for 1st place
$reward2 = 70; // Initial reward for 2nd place
$reward3 = 40; // Initial reward for 3rd place

$players = [
    ['name' => 'Player1', 'points' => 50, 'rank' => 1],
    ['name' => 'Player2', 'points' => 50, 'rank' => 1],
    ['name' => 'Player3', 'points' => 50, 'rank' => 1],
    ['name' => 'Player4', 'points' => 50, 'rank' => 1],
    ['name' => 'Player5', 'points' => 50, 'rank' => 1],
    ['name' => 'Player6', 'points' => 50, 'rank' => 1],
    ['name' => 'Player7', 'points' => 50, 'rank' => 1],
    ['name' => 'Player8', 'points' => 50, 'rank' => 1],
    ['name' => 'Player9', 'points' => 50, 'rank' => 1],
    ['name' => 'Player10', 'points' => 50, 'rank' => 1],
    ['name' => 'Player11', 'points' => 50, 'rank' => 1],
    ['name' => 'Player12', 'points' => 50, 'rank' => 1],
    ['name' => 'Player13', 'points' => 48, 'rank' => 1],
    ['name' => 'Player14', 'points' => 46, 'rank' => 2],
    ['name' => 'Player15', 'points' => 45, 'rank' => 2],
    ['name' => 'Player16', 'points' => 43, 'rank' => 3],
    ['name' => 'Player17', 'points' => 42, 'rank' => 3]
];

$rewardedPlayers = calculateRewards($reward1, $reward2, $reward3, $players);

// Display the results
foreach ($rewardedPlayers as $player) {
    echo "Name: {$player['name']}, Points: {$player['points']}, Rank: {$player['rank']}, Reward: {$player['reward']} PLN\n";
}
?>
