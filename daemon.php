<?php
/**
 * CatDaemon - Use SQLite Database
 */

require_once 'database.php';

$DATA_DIR = __DIR__ . '/data';
$PID_FILE = $DATA_DIR . '/daemon.pid';
$LOG_FILE = $DATA_DIR . '/daemon.log';

if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0755, true);
}

// Initialize database
$db = new CatDatabase($DATA_DIR);

function daemonLog($message) {
    global $LOG_FILE;
    $line = date('Y-m-d H:i:s') . ' - ' . $message . "\n";
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

function isRunning() {
    global $PID_FILE;
    if (!file_exists($PID_FILE)) return false;
    $pid = file_get_contents($PID_FILE);
    if (empty($pid)) return false;
    if (PHP_OS_FAMILY === 'Linux') {
        return file_exists("/proc/$pid");
    }
    return false;
}

function savePid() {
    global $PID_FILE;
    file_put_contents($PID_FILE, getmypid());
}

function simulateCatLife($state, $currentTime, $db) {
    $lastUpdate = $state['lastUpdate'];
    $elapsed = $currentTime - $lastUpdate;

    if ($elapsed <= 0) return $state;

    // Check and auto cure（SickExceed2Hours)
    $justAutoCured = $db->autoCureIfNeeded();
    if ($justAutoCured) {
        daemonLog("Cat was sick for over 2 hours, auto recovered!");
    }

    // If working
    if ($state['isWorking']) {
        $workElapsed = $currentTime - $state['workStartTime'];
        // Work time changed to5Seconds
        if ($workElapsed >= 5) {
            $state['isWorking'] = false;
            $state['workStartTime'] = null;
            // Calculate work income（Base100coins + efficiency Bonus)
            $baseIncome = 100;
            $efficiencyBonus = $db->getEfficiencyBonus();
            $bonusIncome = round($baseIncome * $efficiencyBonus / 100);
            $totalIncome = $baseIncome + $bonusIncome;
            
            // 10%Probability sick
            $gotSick = (mt_rand(1, 100) <= 10);
            if ($gotSick) {
                $db->makeSick();
                logActivity("Work ended, earned {$totalIncome}Yuan，But sick！");
            } else {
                logActivity("Work ended, earned {$totalIncome}coins (base {$baseIncome}coins + efficiency {$efficiencyBonus}%)");
            }
            
            $db->earnMoney($totalIncome, 'Go out to work（+' . $efficiencyBonus . '%Efficiency)' . ($gotSick ? '【Sick】' : ''));
            $state['money'] = $db->getBalance();
            daemonLog("Work ended, earned {$totalIncome}coins (base {$baseIncome}coins + efficiency {$efficiencyBonus}%)");
        } else {
            // Work period stat consumption：Hunger20、Thirst15、Sleep15、Happiness10、Cleanliness15（5SecondsConsumed evenly within)
            // EverySecondsConsumption：Hunger4、Thirst3、Sleep3、Happiness2、Cleanliness3
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
        $studyDuration = 5;
        $hasBookshelf = $db->hasFurniture('bookshelf');
        
        $studyElapsed = $currentTime - $studyState['startTime'];
        if ($studyElapsed >= $studyDuration) {
            // Study completed，Upgrade skill
            $skillId = $studyState['skillId'];
            $db->studySkill($skillId);
            $db->endStudy();
            logActivity("Study completed，{$skillId} +1Lv" . ($hasBookshelf ? "（Bookshelf bonus)" : ""));
        } else {
            // Study period stat consumption：If has bookshelf consumption halved
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
        // Stats also drop during sleep，But speed halved（Every10MinutesDrop0.5%)
        $sleepDecayRate = 0.5 / 600.0;
        
        // CheckIsNoSick，SickWhenDecay speedIncrease10Times
        $health = $db->getHealthState();
        if ($health['isSick']) {
            $sleepDecayRate *= 10; // SickWhen10TimesDecay speed
        }
        
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $sleepDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $sleepDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $sleepDecayRate));
        $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * $sleepDecayRate));

        if ($state['sleep'] >= 100) {
            $state['isSleeping'] = false;
            daemonLog("Cat woke up naturally");
        }
        $state['lastUpdate'] = $currentTime;
        return $state;
    }

    // If bathing
    if ($state['isBathing']) {
        $state['cleanliness'] = min(100, $state['cleanliness'] + ($elapsed * 2));
        // Stats also drop when bathing（Every10MinutesDrop1%)
        $bathDecayRate = 1.0 / 600.0;
        
        // CheckIsNoSick，SickWhenDecay speedIncrease10Times
        $health = $db->getHealthState();
        if ($health['isSick']) {
            $bathDecayRate *= 10; // SickWhen10TimesDecay speed
        }
        
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $bathDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $bathDecayRate));
        $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $bathDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $bathDecayRate));

        if ($state['cleanliness'] >= 100) {
            $state['isBathing'] = false;
            $state['cleanliness'] = 100;
            daemonLog("Cat finished bathing");
        }
        $state['lastUpdate'] = $currentTime;
        return $state;
    }

    // Normal state stat decay - Every10MinutesDrop1%
    // 10Minutes = 600Seconds，1% = 1Point，So drops per second 1/600 = 0.00167
    $decayRate = 1.0 / 600.0; // Every10MinutesDrop1%
    
    // CheckIsNoSick，SickWhenDecay speedIncrease10Times
    $health = $db->getHealthState();
    if ($health['isSick']) {
        $decayRate *= 10; // SickWhen10TimesDecay speed
        daemonLog("Cat is sick, stat decay rate increased 10x");
    }
    
    $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $decayRate));
    $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $decayRate));
    $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $decayRate));
    $state['happy'] = max(0, $state['happy'] - ($elapsed * $decayRate));

    // CleanlinessDeg decay
    $poops = $db->loadPoops();
    $poopCount = count($poops);
    $cleanlinessDecay = $decayRate + ($poopCount * 0.001);
    $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * $cleanlinessDecay));

    // Too dirty or thirsty affects happiness（Extra penalty)
    if ($state['cleanliness'] < 30 || $state['thirst'] < 30) {
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $decayRate));
    }

    $state['lastUpdate'] = $currentTime;
    return $state;
}

function checkPoopNeed($state, $db) {
    if ($state['isSleeping'] || $state['isWorking'] || $state['isBathing']) return false;
    
    // CheckIsNoStudying
    $studyState = $db->getStudyState();
    if ($studyState['isStudying']) return false;

    $poops = $db->loadPoops();
    if (count($poops) >= 5) return false;

    // GetDatabaseLast time inPoopWhenInterval
    $lastPoopTime = $db->getLastPoopTime();
    $timeSinceLastPoop = time() - $lastPoopTime;
    $sixHours = 21600; // 6Hours = 21600Seconds

    // Must wait full6Only poops after hours
    if ($timeSinceLastPoop < $sixHours) return false;

    // 6After hours100%Poop
    return true;
}

function createPoop($state) {
    return [
        'id' => 'poop_' . time() . '_' . mt_rand(1000, 9999),
        'x' => max(20, min(80, $state['catPosition']['x'] + (mt_rand(-50, 50) / 10))),
        'y' => max(30, min(80, $state['catPosition']['y'] + (mt_rand(-30, 50) / 10))),
        'created' => time()
    ];
}

function randomMoveCat($state) {
    $state['catPosition']['x'] = max(20, min(80, 20 + mt_rand(0, 600) / 10));
    $state['catPosition']['y'] = max(30, min(80, 30 + mt_rand(0, 500) / 10));
    return $state;
}

function runDaemon($db) {
    daemonLog("CatDaemonStart (PID: " . getmypid() . ")");
    savePid();

    $lastPoopCheck = 0;
    $lastMoveCheck = 0;

    while (true) {
        $currentTime = time();

        $state = $db->loadCatState();
        $poops = $db->loadPoops();

        $state = simulateCatLife($state, $currentTime, $db);

        if ($currentTime - $lastPoopCheck >= 300) {
            // Check and process accumulated poop
            while (checkPoopNeed($state, $db)) {
                $newPoop = createPoop($state);
                $db->addPoop($newPoop);
                $state['totalPoops']++;
                $state['happy'] = max(0, $state['happy'] - 5);
                daemonLog("Cat pooped: {$newPoop['id']}");
            }
            $lastPoopCheck = $currentTime;
        }

        if ($currentTime - $lastMoveCheck >= 600) {
            if (mt_rand(1, 100) <= 50 && !$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
                $state = randomMoveCat($state);
                daemonLog("Cat moved to: {$state['catPosition']['x']}, {$state['catPosition']['y']}");
            }
            $lastMoveCheck = $currentTime;
        }

        $db->saveCatState($state);
        sleep(1);
    }
}

function runOnce($db) {
    daemonLog("Single execution mode");

    $currentTime = time();
    $state = $db->loadCatState();
    $poops = $db->loadPoops();

    $state = simulateCatLife($state, $currentTime, $db);

    // Check and process accumulated poop
    while (checkPoopNeed($state, $db)) {
        $newPoop = createPoop($state);
        $db->addPoop($newPoop);
        $state['totalPoops']++;
        $state['happy'] = max(0, $state['happy'] - 5);
        daemonLog("Cat pooped: {$newPoop['id']}");
    }

    if (mt_rand(1, 100) <= 30 && !$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
        $state = randomMoveCat($state);
        daemonLog("Cat moved to: {$state['catPosition']['x']}, {$state['catPosition']['y']}");
    }

    $db->saveCatState($state);
    daemonLog("State update completed - Balance: " . $db->getBalance() . "Yuan");
}

$mode = $argv[1] ?? 'once';

switch ($mode) {
    case 'daemon':
        // Clean possible oldPIDFile
        if (file_exists($PID_FILE)) {
            $oldPid = trim(file_get_contents($PID_FILE));
            if (!empty($oldPid) && is_numeric($oldPid)) {
                // CheckIsNoReallyIsdaemon.phpProcess
                $cmdline = @file_get_contents("/proc/$oldPid/cmdline");
                if (!$cmdline || strpos($cmdline, 'daemon.php') === false) {
                    // NotIsOurProcess，DeletePIDFile
                    unlink($PID_FILE);
                } else if (file_exists("/proc/$oldPid")) {
                    daemonLog("Daemon is already running (PID: $oldPid)");
                    exit(1);
                } else {
                    unlink($PID_FILE);
                }
            } else {
                unlink($PID_FILE);
            }
        }

        // Run directlyDaemon（Notfork，Executed by caller in background)
        runDaemon($db);
        break;

    case 'stop':
        if (file_exists($PID_FILE)) {
            $pid = file_get_contents($PID_FILE);
            if ($pid && file_exists("/proc/$pid")) {
                posix_kill($pid, SIGTERM);
                daemonLog("Daemon stopped (PID: $pid)");
            }
            unlink($PID_FILE);
        }
        break;

    case 'status':
        if (isRunning()) {
            $pid = file_get_contents($PID_FILE);
            echo "DaemonRunning (PID: $pid)\n";
        } else {
            echo "DaemonNot running\n";
        }

        $state = $db->loadCatState();
        echo "\nCatState:\n";
        echo "  Hunger: {$state['hunger']}%\n";
        echo "  Thirst: {$state['thirst']}%\n";
        echo "  Sleep: {$state['sleep']}%\n";
        echo "  Happiness: {$state['happy']}%\n";
        echo "  Cleanliness: {$state['cleanliness']}%\n";
        echo "  Money: {$db->getBalance()}Yuan\n";
        echo "  Sleeping: " . ($state['isSleeping'] ? 'Is' : 'No') . "\n";
        echo "  Working: " . ($state['isWorking'] ? 'Is' : 'No') . "\n";
        echo "  Bathing: " . ($state['isBathing'] ? 'Is' : 'No') . "\n";
        echo "  Position: {$state['catPosition']['x']}, {$state['catPosition']['y']}\n";
        echo "  Last update: " . date('Y-m-d H:i:s', $state['lastUpdate']) . "\n";

        $poops = $db->loadPoops();
        echo "\nPoop count: " . count($poops) . "\n";
        break;

    case 'once':
    default:
        runOnce($db);
        break;
}

$db->close();
