<?php
function calculateRewards(float $reward1, float $reward2, float $reward3, $players) {
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



    $i = 0;
    do {
       // echo "rewards full, iteration:" . $i .  " : 1: " . $reward1 . ' 2: ' . $reward2 . ' 3: ' . round($reward3,2) . ' sum:' . round(($reward1+$reward2+$reward3),2) . "\n"; 

        $isCriteriaMet = true;

        $takeFromReward2 = 0;
        $takeFromReward3 = 0;
        
        // Check if the 30% rule is maintained
        if (!($rewardPerPerson1 > ($rewardPerPerson2 * 1.3))) {
            // Adjust rewards if criteria are not met
            $takeFromReward2 = $reward2 * 0.15;
            $takeFromReward3 = $reward3 * 0.10;
          

            $reward2 -= $takeFromReward2; // Reduce 2nd place reward by 20%
            $reward3 -= $takeFromReward3; // Reduce 3rd place reward by 10%
            $reward1 = $reward1 + $takeFromReward2 + $takeFromReward3;
            
            
            $rewardPerPerson1 = $reward1 / count($rank1);
            $rewardPerPerson2 = $reward2 / count($rank2);
            $rewardPerPerson3 = $reward3 / count($rank3);
            $isCriteriaMet = false;
        } 
      $i++;
    } while (!$isCriteriaMet);
    
       $takeFromReward3 = 0;
    
    do {
        // Check if the 30% rule is maintained
        if (!($rewardPerPerson2 > $rewardPerPerson3 * 1.3)) {
            $takeFromReward3 = $reward3 * 0.10;
            // Adjust rewards if criteria are not met
            $reward2 += $takeFromReward3;
            
            $reward3 -=  $takeFromReward3; // Reduce 3rd place reward by 10%
            
            $rewardPerPerson2 = $reward2 / count($rank2);
            $rewardPerPerson3 = $reward3 / count($rank3);
            $isCriteriaMet = false;
        } else {
          $isCriteriaMet = true;
        }
    } while(!$isCriteriaMet);

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
$reward1 = 450; // Reward for 1st place
$reward2 = 200; // Initial reward for 2nd place
$reward3 = 150; // Initial reward for 3rd place

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
    ['name' => 'Player13', 'points' => 50, 'rank' => 1],
    ['name' => 'Player14', 'points' => 46, 'rank' => 2],
    ['name' => 'Player15', 'points' => 46, 'rank' => 2],
    ['name' => 'Player16', 'points' => 43, 'rank' => 3],
    ['name' => 'Player17', 'points' => 43, 'rank' => 3]
];

$rewardedPlayers = calculateRewards($reward1, $reward2, $reward3, $players);

$sum= 0;
// Display the results
foreach ($rewardedPlayers as $player) {
    echo "Name: {$player['name']}, Points: {$player['points']}, Rank: {$player['rank']}, Reward: {$player['reward']} PLN\n";
    $sum += $player['reward'];
}

echo "\n Suma: " . $sum;
