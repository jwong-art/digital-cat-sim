<?php
/**
 * Digital Cat Backend API
 * MakePlaying with SQLite DatabaseStore data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once 'database.php';

// Data storage path
$DATA_DIR = __DIR__ . '/data';
$LOG_FILE = $DATA_DIR . '/activity.log';
$PID_FILE = $DATA_DIR . '/daemon.pid';

// Ensure data directory exists
if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0755, true);
}

// Auto-start daemon（If not running）
function ensureDaemonRunning() {
    global $PID_FILE;
    
    // MakePlaying withFileLockPrevent race condition
    $lockFile = $PID_FILE . '.lock';
    $lockHandle = fopen($lockFile, 'c');
    if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        return; // Already otherProcessProcessing
    }
    
    // Check if daemon is already running
    $isRunning = false;
    
    // Method1: Check PID FileAndProcess
    if (file_exists($PID_FILE)) {
        $pid = trim(file_get_contents($PID_FILE));
        if (!empty($pid) && is_numeric($pid)) {
            // MakePlaying with posix_kill(0) CheckProcessExists or not（FitPlaying withFor any PID）
            if (function_exists('posix_kill')) {
                $isRunning = @posix_kill(intval($pid), 0);
            } else {
                $isRunning = file_exists("/proc/$pid");
            }
        }
    }
    
    // Method2: Directly search daemon.php Process（More reliable）
    if (!$isRunning) {
        $output = [];
        exec("pgrep -f 'daemon.php daemon' 2>/dev/null", $output);
        if (!empty($output)) {
            $isRunning = true;
            // Update PID File
            file_put_contents($PID_FILE, trim($output[0]));
        }
    }
    
    // If not running，Start daemonProcess
    if (!$isRunning) {
        $daemonScript = __DIR__ . '/daemon.php';
        $logFile = dirname($PID_FILE) . '/daemon.log';
        
        // Clear possible oldPIDFile
        if (file_exists($PID_FILE)) {
            unlink($PID_FILE);
        }
        
        // MakePlaying with exec + nohup Start real backgroundProcess
        exec("cd " . escapeshellarg(__DIR__) . " && nohup php $daemonScript daemon > /dev/null 2>&1 &");
        
        // Waiting for daemonProcessStart and write PID File
        usleep(1000000); // 1seconds
    }
    
    // ReleaseFileLock
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

// Every time API CallPlaying withEnsure daemon whenProcessRunning
ensureDaemonRunning();

// InitializeDatabase
$db = new CatDatabase($DATA_DIR);

// Log
function logActivity($message) {
    global $LOG_FILE;
    $line = date('Y-m-d H:i:s') . ' - ' . $message . "\n";
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// SimulateCat stateChange（Core function）
function simulateCatLife($state, $currentTime) {
    $lastUpdate = $state['lastUpdate'];
    $elapsed = $currentTime - $lastUpdate;

    if ($elapsed <= 0) return $state;

    global $db;

    // If working
    if ($state['isWorking']) {
        $workElapsed = $currentTime - $state['workStartTime'];
        // Work time changed to5seconds
        if ($workElapsed >= 5) {
            $state['isWorking'] = false;
            $state['workStartTime'] = null;
            // Calculate work income（Base100coins + EfficiencyBonus）
            $baseIncome = 100;
            $efficiencyBonus = $db->getEfficiencyBonus();
            $bonusIncome = round($baseIncome * $efficiencyBonus / 100);
            $totalIncome = $baseIncome + $bonusIncome;
            // 10%Probability sick
            $gotSick = (mt_rand(1, 100) <= 10);
            if ($gotSick) {
                $db->makeSick();
                logActivity("Work ended, Received{$totalIncome}coins, , but got sick!");
            } else {
                logActivity("Work ended, Received{$totalIncome}coins（Base{$baseIncome}coins + Efficiency{$efficiencyBonus}%）");
            }

            $db->earnMoney($totalIncome, 'Go out to work（+' . $efficiencyBonus . '%Efficiency）' . ($gotSick ? '【Sick】' : ''));
            $state['money'] = $db->getBalance();
        } else {
            // Work period statsconsumed：Hunger20、Thirst15、Sleep15、Happiness10、Cleanliness15（5secondsInside evenlyconsumed）
            // Everysecondsconsumed：Hunger4、Thirst3、Sleep3、Happiness2、Cleanliness3
            $state['hunger'] = max(0, $state['hunger'] - ($elapsed * 4));
            $state['thirst'] = max(0, $state['thirst'] - ($elapsed * 3));
            $state['sleep'] = max(0, $state['sleep'] - ($elapsed * 3));
            $state['happy'] = max(0, $state['happy'] - ($elapsed * 2));
            $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * 3));
            $state['lastUpdate'] = $currentTime;
            return $state;
        }
    }

    // If studying
    $studyState = $db->getStudyState();
    if ($studyState['isStudying']) {
        // Study time changed to5seconds（BookshelfNo longer reduces time，Changed to reduceconsumed）
        $studyDuration = 5;
        $hasBookshelf = $db->hasFurniture('bookshelf');

        $studyElapsed = $currentTime - $studyState['startTime'];
        if ($studyElapsed >= $studyDuration) {
            // Study completed，LvLvSkill
            $skillId = $studyState['skillId'];
            $db->studySkill($skillId);
            $db->endStudy();
            logActivity("Study completed，{$skillId} +1Lv" . ($hasBookshelf ? "（BookshelfBonus）" : ""));
        } else {
            // Study period statsconsumed：Hunger20、Thirst15、Sleep15、Happiness10、Cleanliness15（5secondsInside evenlyconsumed）
            // HasBookshelfThenconsumedHalved
            // Everysecondsconsumed：Hunger4、Thirst3、Sleep3、Happiness2、Cleanliness3
            $multiplier = $hasBookshelf ? 0.5 : 1.0;
            $state['hunger'] = max(0, $state['hunger'] - ($elapsed * 4 * $multiplier));
            $state['thirst'] = max(0, $state['thirst'] - ($elapsed * 3 * $multiplier));
            $state['sleep'] = max(0, $state['sleep'] - ($elapsed * 3 * $multiplier));
            $state['happy'] = max(0, $state['happy'] - ($elapsed * 2 * $multiplier));
            $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * 3 * $multiplier));
            $state['lastUpdate'] = $currentTime;
            return $state;
        }
    }

    // If sleeping
    if ($state['isSleeping']) {
        $state['sleep'] = min(100, $state['sleep'] + ($elapsed * 0.5));
        // SleepWhenNumStats alsoDrop, butSpeedHalved（Every10MinutesDrop0.5%）
        $sleepDecayRate = 0.5 / 600.0;
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $sleepDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $sleepDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $sleepDecayRate));
        $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * $sleepDecayRate));

        if ($state['sleep'] >= 100) {
            $state['isSleeping'] = false;
            logActivity("The cat woke up naturally");
        }
        $state['lastUpdate'] = $currentTime;
        return $state;
    }

    // If bathing
    if ($state['isBathing']) {
        $state['cleanliness'] = min(100, $state['cleanliness'] + ($elapsed * 2));
        // BathWhenNumStats alsoDrop（Every10MinutesDrop1%）
        $bathDecayRate = 1.0 / 600.0;
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $bathDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $bathDecayRate));
        $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $bathDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $bathDecayRate));

        if ($state['cleanliness'] >= 100) {
            $state['isBathing'] = false;
            $state['cleanliness'] = 100;
            logActivity("The cat finished bathing");
        }
        $state['lastUpdate'] = $currentTime;
        return $state;
    }

    // Normal state stat decay - Every10MinutesDrop1%
    // 10Minutes = 600seconds，1% = 1Point，BecauseEverysecondsDrop 1/600 = 0.00167
    $decayRate = 1.0 / 600.0; // Every10MinutesDrop1%
    
    $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $decayRate));
    $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $decayRate));
    $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $decayRate));
    $state['happy'] = max(0, $state['happy'] - ($elapsed * $decayRate));

    // CleanlinessDeg decay
    global $db;
    $poops = $db->loadPoops();
    $poopCount = count($poops);
    $cleanlinessDecay = $decayRate + ($poopCount * 0.001);
    $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * $cleanlinessDecay));

    // Too dirty or thirsty affectsHappiness（Extra penalty）
    if ($state['cleanliness'] < 30 || $state['thirst'] < 30) {
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $decayRate));
    }

    $state['lastUpdate'] = $currentTime;
    return $state;
}

// CheckNeed to poop（Every6HoursOnce，Can accumulate）
function checkPoopNeed($state, $db) {
    if ($state['isSleeping'] || $state['isWorking'] || $state['isBathing']) return false;
    
    // CheckIf studying
    $studyState = $db->getStudyState();
    if ($studyState['isStudying']) return false;

    $poops = $db->loadPoops();
    if (count($poops) >= 5) return false;

    // GetDatabaseLast poop time in
    $lastPoopTime = $db->getLastPoopTime();
    $timeSinceLastPoop = time() - $lastPoopTime;
    $sixHours = 21600; // 6Hours = 21600seconds

    // Must wait full6HoursWill only poop
    if ($timeSinceLastPoop < $sixHours) return false;

    // 6HoursAfter100%Poop
    return true;
}

// Create poop
function createPoop($state) {
    return [
        'id' => 'poop_' . time() . '_' . mt_rand(1000, 9999),
        'x' => max(20, min(80, $state['catPosition']['x'] + (mt_rand(-50, 50) / 10))),
        'y' => max(30, min(80, $state['catPosition']['y'] + (mt_rand(-30, 50) / 10))),
        'created' => time()
    ];
}

// Random moveCat
function randomMoveCat($state) {
    $state['catPosition']['x'] = max(20, min(80, 20 + mt_rand(0, 600) / 10));
    $state['catPosition']['y'] = max(30, min(80, 30 + mt_rand(0, 500) / 10));
    return $state;
}

// ProcessAPIRequest
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'status':
        // GetCurrent state
        $state = $db->loadCatState();
        $poops = $db->loadPoops();
        $currentTime = time();

        // CalculateInvestmentIncome（Offline income continues）
        $investmentIncome = $db->calculateInvestmentIncome();
        if ($investmentIncome > 0) {
            $db->earnMoney($investmentIncome, 'Investment income');
            logActivity("Claiming investment income：{$investmentIncome}coins");
            $state['money'] = $db->getBalance();
        }

        // CheckAnd auto cure（SickExceed2Hours）
        $justAutoCured = $db->autoCureIfNeeded();
        if ($justAutoCured) {
            logActivity("Cat was sick for over 2 hours, auto recovered!");
        }

        // SimulateOffline periodChange
        $state = simulateCatLife($state, $currentTime);

        // CheckNeed to poop（Can accumulate multiple）
        while (checkPoopNeed($state, $db)) {
            $newPoop = createPoop($state);
            $db->addPoop($newPoop);
            $state['totalPoops']++;
            $state['happy'] = max(0, $state['happy'] - 5);
            logActivity("Cat pooped: {$newPoop['id']}");
        }

        // Random move
        $elapsed = $currentTime - $state['lastUpdate'];
        if ($elapsed > 300 && mt_rand(1, 100) <= 30) {
            $state = randomMoveCat($state);
        }

        // Save state
        $db->saveCatState($state);

        echo json_encode([
            'success' => true,
            'state' => $state,
            'poops' => $db->loadPoops(),
            'serverTime' => $currentTime
        ]);
        break;

    case 'feed':
        // Eating - NeedSelectFood
        $foodType = $_POST['foodType'] ?? '';

        if (empty($foodType)) {
            echo json_encode(['success' => false, 'error' => 'no_food_selected', 'message' => 'Please selectFood']);
            break;
        }

        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        // CheckWhetherHasThis food
        $foodQty = $db->getItemQuantity($foodType);
        if ($foodQty <= 0) {
            echo json_encode(['success' => false, 'error' => 'no_food', 'message' => 'NoHasThisFood']);
            break;
        }

        // consumedFood
        if (!$db->consumeItem($foodType, 1)) {
            echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedFoodFailed']);
            break;
        }

        // Increase different stats based on food type
        switch ($foodType) {
            case 'cat_food': // Cat food
                $state['hunger'] = min(100, $state['hunger'] + 20);
                $state['happy'] = min(100, $state['happy'] + 2);
                logActivity("Ate cat food, Hunger+20");
                break;
            case 'fish_dry': // Dried fish
                $state['hunger'] = min(100, $state['hunger'] + 35);
                $state['happy'] = min(100, $state['happy'] + 10);
                logActivity("Ate dried fish, Hunger+35, Happiness+10");
                break;
            case 'chicken': // Roast chicken
                $state['hunger'] = min(100, $state['hunger'] + 50);
                $state['happy'] = min(100, $state['happy'] + 15);
                $state['thirst'] = max(0, $state['thirst'] - 5); // HasPointDry
                logActivity("Ate roast chicken, Hunger+50, Happiness+15, Thirst-5");
                break;
            case 'sushi': // Salmon sushi
                $state['hunger'] = min(100, $state['hunger'] + 45);
                $state['happy'] = min(100, $state['happy'] + 20);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 5); // Exquisite food
                logActivity("Ate salmon sushi, Hunger+45, Happiness+20, Cleanliness+5");
                break;
            case 'steak': // Steak dinner
                $state['hunger'] = min(100, $state['hunger'] + 70);
                $state['happy'] = min(100, $state['happy'] + 25);
                $state['sleep'] = min(100, $state['sleep'] + 10); // Satisfaction
                logActivity("Ate steak dinner, Hunger+70, Happiness+25, Sleep+10");
                break;
            case 'lobster': // Boston lobster
                $state['hunger'] = min(100, $state['hunger'] + 60);
                $state['happy'] = min(100, $state['happy'] + 30);
                $state['thirst'] = min(100, $state['thirst'] + 20);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 10);
                logActivity("Ate Boston lobster, Hunger+60, Happiness+30, Thirst+20, Cleanliness+10");
                break;
            case 'golden_food': // GoldenCat food
                $state['hunger'] = 100;
                $state['happy'] = 100;
                $state['thirst'] = min(100, $state['thirst'] + 30);
                $state['sleep'] = min(100, $state['sleep'] + 20);
                $state['cleanliness'] = 100;
                logActivity("Ate golden cat food, all stats greatly increased!");
                break;
        }

        $db->saveCatState($state);
        echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
        break;

    case 'drink':
        // Drinking - NeedSelectDrink
        $drinkType = $_POST['drinkType'] ?? '';

        if (empty($drinkType)) {
            echo json_encode(['success' => false, 'error' => 'no_drink_selected', 'message' => 'Please selectDrink']);
            break;
        }

        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        // CheckWhetherHasThis drink
        $drinkQty = $db->getItemQuantity($drinkType);
        if ($drinkQty <= 0) {
            echo json_encode(['success' => false, 'error' => 'no_drink', 'message' => 'NoHasThisDrink']);
            break;
        }

        // consumedDrink
        if (!$db->consumeItem($drinkType, 1)) {
            echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedDrinkFailed']);
            break;
        }

        // Increase different stats based on drink type
        switch ($drinkType) {
            case 'milk': // Milk
                $state['hunger'] = min(100, $state['hunger'] + 5);
                $state['thirst'] = min(100, $state['thirst'] + 25);
                $state['happy'] = min(100, $state['happy'] + 3);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 2);
                logActivity("Drank milk, Hunger+5, Thirst+25, Happiness+3, Cleanliness+2");
                break;
            case 'cola': // Cola
                $state['hunger'] = min(100, $state['hunger'] + 3);
                $state['thirst'] = min(100, $state['thirst'] + 30);
                $state['happy'] = min(100, $state['happy'] + 15);
                $state['sleep'] = max(0, $state['sleep'] - 5); // ColaMakes excited，Sleep-5
                logActivity("Drank cola, Hunger+3, Thirst+30, Happiness+15, Sleep-5");
                break;
            case 'coconut_water': // HighLvCoconut water
                $state['hunger'] = min(100, $state['hunger'] + 8);
                $state['thirst'] = min(100, $state['thirst'] + 40);
                $state['happy'] = min(100, $state['happy'] + 8);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 10);
                $state['sleep'] = min(100, $state['sleep'] + 5);
                logActivity("Drank premium coconut water, Hunger+8, Thirst+40, Happiness+8, Cleanliness+10, Sleep+5");
                break;
            case 'orange_juice': // Fresh orange juice
                $state['hunger'] = min(100, $state['hunger'] + 5);
                $state['thirst'] = min(100, $state['thirst'] + 35);
                $state['happy'] = min(100, $state['happy'] + 12);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 5);
                logActivity("Drank fresh orange juice, Hunger+5, Thirst+35, Happiness+12, Cleanliness+5");
                break;
            case 'coffee': // Cat poop coffee
                $state['hunger'] = min(100, $state['hunger'] + 2);
                $state['thirst'] = min(100, $state['thirst'] + 20);
                $state['happy'] = min(100, $state['happy'] + 20);
                $state['sleep'] = max(0, $state['sleep'] - 15); // Coffee refreshes，Sleep-15
                logActivity("Drank cat poop coffee, Hunger+2, Thirst+20, Happiness+20, Sleep-15");
                break;
            case 'energy_drink': // Energy drink
                $state['hunger'] = min(100, $state['hunger'] + 5);
                $state['thirst'] = min(100, $state['thirst'] + 50);
                $state['happy'] = min(100, $state['happy'] + 10);
                $state['sleep'] = min(100, $state['sleep'] + 10); // RecoverEnergy
                logActivity("Drank energy drink, Hunger+5, Thirst+50, Happiness+10, Sleep+10");
                break;
            case 'magic_potion': // Magic potion
                $state['hunger'] = min(100, $state['hunger'] + 30);
                $state['thirst'] = 100;
                $state['happy'] = min(100, $state['happy'] + 25);
                $state['sleep'] = min(100, $state['sleep'] + 20);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 20);
                logActivity("Drank magic potion, all stats greatly increased!");
                break;
        }

        $db->saveCatState($state);
        echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
        break;

    case 'sleep':
        $state = $db->loadCatState();
        $currentTime = time();
        $wasSleeping = $state['isSleeping'];
        
        // FirstSimulateStatusChange
        $state = simulateCatLife($state, $currentTime);
        
        // IfWas sleepingStatus，AndsimulateCatLifeNoHasAuto wake（SleepVal not full），ThenManually switch to wake
        // IfWas not sleepingStatus，ThenSwitch to sleep
        if ($wasSleeping && $state['isSleeping']) {
            // interrupted by userPointClick to wake up
            $state['isSleeping'] = false;
            logActivity("Cat woke up (interrupted by user)");
        } elseif (!$wasSleeping) {
            // interrupted by userPointClick to sleep
            $state['isSleeping'] = true;
            logActivity("Cat started sleeping");
        }
        // If wasSleeping=true And state['isSleeping']=false，Woke up naturally，Do not needExtra operation

        $db->saveCatState($state);
        echo json_encode(['success' => true, 'state' => $state]);
        break;

    case 'play':
        // Play - NeedSelectToy
        $toyType = $_POST['toyType'] ?? '';

        if (empty($toyType)) {
            echo json_encode(['success' => false, 'error' => 'no_toy_selected', 'message' => 'Please selectToy']);
            break;
        }

        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if (!$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
            // CheckWhetherHasThis toy
            $toyQty = $db->getItemQuantity($toyType);
            if ($toyQty <= 0 && $toyType !== 'robot_mouse') {
                echo json_encode(['success' => false, 'error' => 'no_toy', 'message' => 'NoHasThisToy']);
                break;
            }

            // Electronic mouse unlimitedMakePlaying with，Do not needconsumed
            if ($toyType === 'robot_mouse') {
                $state['happy'] = min(100, $state['happy'] + 40);
                $state['hunger'] = max(0, $state['hunger'] - 3);
                $state['sleep'] = max(0, $state['sleep'] - 3);
                logActivity("Playing with electronic mouse, Happiness+40");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'cat_stick') {
                // Cat teaser needsconsumed
                if (!$db->consumeItem('cat_stick', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedToyFailed']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 30);
                $state['hunger'] = max(0, $state['hunger'] - 3);
                $state['sleep'] = max(0, $state['sleep'] - 3);
                logActivity("Playing with cat teaser，consumed 1, Happiness+30");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'ball') {
                // Yarn ball
                if (!$db->consumeItem('ball', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedToyFailed']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 25);
                $state['hunger'] = max(0, $state['hunger'] - 2);
                $state['sleep'] = max(0, $state['sleep'] - 2);
                logActivity("Playing with yarn ball, consumed 1, Happiness+25");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'feather') {
                // Feather wand
                if (!$db->consumeItem('feather', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedToyFailed']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 35);
                $state['hunger'] = max(0, $state['hunger'] - 4);
                $state['sleep'] = max(0, $state['sleep'] - 4);
                logActivity("Playing with feather wand, consumed 1, Happiness+35");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'laser') {
                // Laser pointer
                if (!$db->consumeItem('laser', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedToyFailed']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 45);
                $state['hunger'] = max(0, $state['hunger'] - 5);
                $state['sleep'] = max(0, $state['sleep'] - 5);
                logActivity("Playing with laser pointer, consumed 1, Happiness+45");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'drone') {
                // Drone
                if (!$db->consumeItem('drone', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedToyFailed']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 50);
                $state['hunger'] = max(0, $state['hunger'] - 6);
                $state['sleep'] = max(0, $state['sleep'] - 6);
                logActivity("Playing with drone, consumed 1, Happiness+50");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'vr_headset') {
                // VRGlasses
                if (!$db->consumeItem('vr_headset', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => 'consumedToyFailed']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 60);
                $state['hunger'] = max(0, $state['hunger'] - 8);
                $state['sleep'] = max(0, $state['sleep'] - 10);
                logActivity("Playing with VR headset, consumed 1, Happiness+60");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            }
        }

        echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
        break;

    case 'work':
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if (!$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
            $state['isWorking'] = true;
            $state['workStartTime'] = time();
            logActivity("Started working");
        }

        $db->saveCatState($state);
        echo json_encode(['success' => true, 'state' => $state]);
        break;

    case 'bathe':
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if ($state['isBathing']) {
            echo json_encode(['success' => false, 'error' => 'Already bathing']);
            break;
        }
        if ($state['isSleeping']) {
            echo json_encode(['success' => false, 'error' => 'Already sleeping']);
            break;
        }
        if ($state['isWorking']) {
            echo json_encode(['success' => false, 'error' => 'Already working']);
            break;
        }
        if ($state['cleanliness'] >= 100) {
            echo json_encode(['success' => false, 'error' => 'AlreadyDryNet']);
            break;
        }

        $state['isBathing'] = true;
        logActivity("Start bathing the cat");
        $db->saveCatState($state);
        echo json_encode(['success' => true, 'state' => $state]);
        break;

    case 'clean':
        $poopId = $_POST['poopId'] ?? '';
        $state = $db->loadCatState();

        $db->removePoop($poopId);

        $state['happy'] = min(100, $state['happy'] + 10);
        $state['cleanliness'] = min(100, $state['cleanliness'] + 5);
        $db->saveCatState($state);
        logActivity("Cleaning poop: $poopId");

        echo json_encode(['success' => true, 'state' => $state, 'poops' => $db->loadPoops()]);
        break;

    case 'pet':
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if (!$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
            // OnlyHasHappiness <= 50% When，Only petting increasesHappiness
            if ($state['happy'] <= 50) {
                $state['happy'] = min(100, $state['happy'] + 10);
                logActivity("Petted the cat, Happiness+10");
                $message = 'Petted the cat, Happiness+10';
            } else {
                // HappinessAlreadyHigh，PetOnlyExpress love
                logActivity("Petted the cat, cat is very happy");
                $message = 'Cat alreadyHappiness，Continue petting to express your love';
            }
        }

        $db->saveCatState($state);
        echo json_encode(['success' => true, 'state' => $state, 'message' => $message ?? '']);
        break;

    case 'move':
        $x = floatval($_POST['x'] ?? 50);
        $y = floatval($_POST['y'] ?? 50);

        $state = $db->loadCatState();
        $state['catPosition']['x'] = max(20, min(80, $x));
        $state['catPosition']['y'] = max(30, min(80, $y));
        $db->saveCatState($state);

        echo json_encode(['success' => true, 'state' => $state]);
        break;

    case 'balance':
        echo json_encode(['success' => true, 'balance' => $db->getBalance()]);
        break;

    case 'transactions':
        $limit = intval($_GET['limit'] ?? 50);
        echo json_encode(['success' => true, 'transactions' => $db->getTransactions($limit)]);
        break;

    case 'skills':
        // GetSkillList、EfficiencyAndStudy status
        echo json_encode([
            'success' => true,
            'skills' => $db->getSkills(),
            'efficiency' => $db->getEfficiencyBonus(),
            'studyState' => $db->getStudyState()
        ]);
        break;

    case 'study':
        // StartLearning skill
        $skillId = $_POST['skillId'] ?? '';
        if (empty($skillId)) {
            echo json_encode(['success' => false, 'error' => 'Please selectSkill']);
            break;
        }

        // CheckWhetherAlready studying orWorkOr sleeping or bathing
        $studyState = $db->getStudyState();
        $state = $db->loadCatState();
        if ($studyState['isStudying']) {
            echo json_encode(['success' => false, 'error' => 'Already studying']);
            break;
        }
        if ($state['isWorking']) {
            echo json_encode(['success' => false, 'error' => 'Already working']);
            break;
        }
        if ($state['isSleeping']) {
            echo json_encode(['success' => false, 'error' => 'Already sleeping']);
            break;
        }
        if ($state['isBathing']) {
            echo json_encode(['success' => false, 'error' => 'Already bathing']);
            break;
        }

        // CheckSkillWhetherAlready fullLv
        $skills = $db->getSkills();
        $skill = array_filter($skills, function($s) use ($skillId) {
            return $s['id'] === $skillId;
        });
        $skill = array_values($skill)[0] ?? null;
        if (!$skill || $skill['level'] >= $skill['maxLevel']) {
            echo json_encode(['success' => false, 'error' => 'SkillAlready fullLv']);
            break;
        }

        // Start studying
        $db->startStudy($skillId);
        logActivity("Start studying: $skillId");
        echo json_encode([
            'success' => true,
            'message' => 'Start studying',
            'studyState' => $db->getStudyState()
        ]);
        break;

    case 'upgrade':
        // LvLvWorkEfficiency
        $result = $db->upgradeEfficiency();
        if ($result['success']) {
            logActivity("Upgraded work efficiency to +{$result['bonus']}%");
        }
        echo json_encode($result);
        break;

    case 'health':
        // GetHealth status
        echo json_encode([
            'success' => true,
            'health' => $db->getHealthState()
        ]);
        break;

    case 'hospital':
        // Go to hospital
        $health = $db->getHealthState();

        if (!$health['isSick']) {
            echo json_encode(['success' => false, 'error' => 'not_sick', 'message' => 'Cat is healthy，No need to go to hospital！']);
            break;
        }

        // CheckBalance
        $balance = $db->getBalance();
        if ($balance < 500) {
            echo json_encode(['success' => false, 'error' => 'no_money', 'message' => 'Insufficient balance，Need500coins！']);
            break;
        }

        // Spend money and cure
        $db->spendMoney(500, 'Go to hospital');
        $db->cureSick();
        logActivity("Went to hospital, spent 500coins, recovered!");

        echo json_encode([
            'success' => true,
            'message' => 'KittenRecoverHealth！',
            'balance' => $db->getBalance(),
            'health' => $db->getHealthState()
        ]);
        break;

    case 'wardrobe':
        // GetWardrobe
        echo json_encode([
            'success' => true,
            'items' => $db->getWardrobe(),
            'equipped' => $db->getEquippedClothes()
        ]);
        break;

    case 'buy_clothes':
        // PurchaseClothes
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => 'no_item']);
            break;
        }

        // GetProductInfo
        $item = $db->getShopItem($itemId);

        if (!$item || $item['category'] !== 'clothes') {
            echo json_encode(['success' => false, 'error' => 'item_not_found']);
            break;
        }

        // CheckWhetherAlready ownedHas
        $wardrobe = $db->getWardrobe();
        foreach ($wardrobe as $w) {
            if ($w['id'] === $itemId) {
                echo json_encode(['success' => false, 'error' => 'already_owned', 'message' => 'Already ownedHasThis clothes']);
                break 2;
            }
        }

        // CheckBalance
        $balance = $db->getBalance();
        if ($balance < $item['price']) {
            echo json_encode(['success' => false, 'error' => 'no_money', 'message' => 'Insufficient balance']);
            break;
        }

        // Spend money and purchase
        $db->spendMoney($item['price'], 'Purchase' . $item['name']);
        $result = $db->buyClothes($itemId, $item['name']);

        if ($result['success']) {
            logActivity("Purchased clothes: {$item['name']}");
            echo json_encode([
                'success' => true,
                'message' => 'Purchase successful！',
                'balance' => $db->getBalance(),
                'wardrobe' => $db->getWardrobe()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
        break;

    case 'equip':
        // Put on clothes
        $itemId = $_POST['itemId'] ?? '';
        $result = $db->equipClothes($itemId);
        echo json_encode([
            'success' => $result['success'],
            'equipped' => $db->getEquippedClothes()
        ]);
        break;

    case 'reset':
        $db->resetGame();
        logActivity("Game reset");
        echo json_encode(['success' => true, 'state' => $db->loadCatState(), 'poops' => []]);
        break;

    case 'logs':
        $lines = [];
        if (file_exists($LOG_FILE)) {
            $content = file_get_contents($LOG_FILE);
            $lines = array_slice(array_filter(explode("\n", $content)), -50);
        }
        echo json_encode(['success' => true, 'logs' => $lines]);
        break;

    case 'furniture':
        // GetPurchaseOfFurniture
        echo json_encode(['success' => true, 'furniture' => $db->getFurniture()]);
        break;

    case 'buy_furniture':
        // Purchasing furniture
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => 'FurnitureIDCannot be empty']);
            break;
        }
        $result = $db->buyFurniture($itemId);
        $result['balance'] = $db->getBalance();
        $result['furniture'] = $db->getFurniture();
        echo json_encode($result);
        break;

    case 'use_sofa':
        // MakePlaying withSofa - SleepVal immediately restored100%
        if (!$db->hasFurniture('sofa')) {
            echo json_encode(['success' => false, 'error' => 'no_sofa', 'message' => 'NoHasSofa']);
            break;
        }
        $state = $db->loadCatState();
        $state['sleep'] = 100;
        $db->saveCatState($state);
        logActivity("Resting on sofa, Sleep restored to 100%");
        echo json_encode(['success' => true, 'state' => $state, 'message' => 'SleepVal restore100%！']);
        break;

    case 'use_computer':
        // MakePlaying withComputer - HappinessImmediately100%
        if (!$db->hasFurniture('computer')) {
            echo json_encode(['success' => false, 'error' => 'no_computer', 'message' => 'NoHasComputer']);
            break;
        }
        $state = $db->loadCatState();
        $state['happy'] = 100;
        $db->saveCatState($state);
        logActivity("Playing computer games, Happiness restored to 100%");
        echo json_encode(['success' => true, 'state' => $state, 'message' => 'HappinessRecover100%！']);
        break;

    case 'use_mystery_device':
        // MakePlaying withmystery device - AllHasStats full
        if (!$db->hasFurniture('mystery_device')) {
            echo json_encode(['success' => false, 'error' => 'no_device', 'message' => 'NoHasmystery device']);
            break;
        }
        $state = $db->loadCatState();
        $state['hunger'] = 100;
        $state['thirst'] = 100;
        $state['sleep'] = 100;
        $state['happy'] = 100;
        $state['cleanliness'] = 100;
        $db->saveCatState($state);
        logActivity("Used mystery device, all stats fully restored!");
        echo json_encode(['success' => true, 'state' => $state, 'message' => 'AllHasNumVal restoreFull！']);
        break;

    case 'investments':
        // GetPurchased investments
        echo json_encode(['success' => true, 'investments' => $db->getInvestments()]);
        break;

    case 'buy_investment':
        // Purchasing investment
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => 'InvestmentIDCannot be empty']);
            break;
        }

        // GetInvestmentInfo
        $item = $db->getShopItem($itemId);
        if (!$item || $item['category'] !== 'investment') {
            echo json_encode(['success' => false, 'error' => 'InvestmentNot found']);
            break;
        }

        // CheckWhetherAlready ownedHas
        if ($db->hasInvestment($itemId)) {
            echo json_encode(['success' => false, 'error' => 'already_owned', 'message' => 'Already ownedHasThisInvestment']);
            break;
        }

        // CheckBalance
        $balance = $db->getBalance();
        if ($balance < $item['price']) {
            echo json_encode(['success' => false, 'error' => 'no_money', 'message' => 'Insufficient balance']);
            break;
        }

        // Spend money and purchase
        $db->spendMoney($item['price'], 'Purchased investment: ' . $item['name']);
        $result = $db->buyInvestment($itemId, $item['name']);

        if ($result['success']) {
            logActivity("Purchased investment: {$item['name']}");
            echo json_encode([
                'success' => true,
                'message' => 'Purchase successful！',
                'balance' => $db->getBalance(),
                'investments' => $db->getInvestments()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
        break;

    case 'claim_investment':
        // ClaimInvestmentIncome
        $income = $db->calculateInvestmentIncome();
        if ($income > 0) {
            $db->earnMoney($income, 'InvestmentIncome');
            logActivity("Claimed investment income: {$income}coins");
            echo json_encode([
                'success' => true,
                'income' => $income,
                'balance' => $db->getBalance(),
                'message' => "ClaimInvestmentIncome {$income} coins！"
            ]);
        } else {
            echo json_encode(['success' => true, 'income' => 0, 'message' => 'No income to claim']);
        }
        break;

    case 'shop':
        // GetShopProductList
        echo json_encode(['success' => true, 'items' => $db->getShopItems()]);
        break;

    case 'inventory':
        // GetInventory items
        echo json_encode(['success' => true, 'items' => $db->getInventory()]);
        break;

    case 'buy':
        // PurchaseItem
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => 'ProductIDCannot be empty']);
            break;
        }
        $result = $db->buyItem($itemId);
        $result['balance'] = $db->getBalance();
        $result['inventory'] = $db->getInventory();
        echo json_encode($result);
        break;

    case 'daemon_status':
        // CheckDaemonProcessStatus
        $PID_FILE = $DATA_DIR . '/daemon.pid';
        $isRunning = false;
        $pid = null;

        if (file_exists($PID_FILE)) {
            $pid = trim(file_get_contents($PID_FILE));
            if (!empty($pid) && is_numeric($pid)) {
                // MakePlaying with posix_kill(0) CheckProcessExists or not
                if (function_exists('posix_kill')) {
                    $isRunning = @posix_kill(intval($pid), 0);
                } else {
                    $isRunning = file_exists("/proc/$pid");
                }
            }
        }

        // IfPIDFileCheckFailed，TryDirectly searchProcess
        if (!$isRunning) {
            $output = [];
            exec("pgrep -f 'daemon.php daemon' 2>/dev/null", $output);
            if (!empty($output)) {
                $isRunning = true;
                $pid = trim($output[0]);
            }
        }

        echo json_encode([
            'success' => true,
            'running' => $isRunning,
            'pid' => $pid
        ]);
        break;

    case 'wait_for_change':
        // Long polling：Waiting for state change
        $lastUpdate = isset($_GET['lastUpdate']) ? intval($_GET['lastUpdate']) : 0;
        $timeout = 5; // Max wait5seconds，ReducePHP-FPMBlock
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            $state = $db->loadCatState();
            $poops = $db->loadPoops();
            
            // CheckWhetherHasChange（WhenTimestampUpdateOr poop countChange）
            if ($state['lastUpdate'] > $lastUpdate) {
                echo json_encode([
                    'success' => true,
                    'changed' => true,
                    'state' => $state,
                    'poops' => $poops,
                    'serverTime' => time()
                ]);
                break;
            }
            
            // EverysecondsCheckOnce
            sleep(1);
        }
        
        // ExWhen，ReturnCurrent state（Do againOnceFinalCheck，Prevent missing）
        if (time() - $startTime >= $timeout) {
            $finalState = $db->loadCatState();
            $finalPoops = $db->loadPoops();
            echo json_encode([
                'success' => true,
                'changed' => ($finalState['lastUpdate'] > $lastUpdate),
                'state' => $finalState,
                'poops' => $finalPoops,
                'serverTime' => time()
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

// CloseDatabase
$db->close();
