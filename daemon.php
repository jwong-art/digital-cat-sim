<?php
/**
 * 猫咪守护进程 - 使用 SQLite 数据库
 */

require_once 'database.php';

$DATA_DIR = __DIR__ . '/data';
$PID_FILE = $DATA_DIR . '/daemon.pid';
$LOG_FILE = $DATA_DIR . '/daemon.log';

if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0755, true);
}

// 初始化数据库
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

    // 检查并自动治愈（生病超过2小时）
    $justAutoCured = $db->autoCureIfNeeded();
    if ($justAutoCured) {
        daemonLog("猫咪生病超过2小时，自动恢复健康！");
    }

    // 如果正在打工
    if ($state['isWorking']) {
        $workElapsed = $currentTime - $state['workStartTime'];
        // 打工时间改为5秒
        if ($workElapsed >= 5) {
            $state['isWorking'] = false;
            $state['workStartTime'] = null;
            // 计算打工收入（基础100元 + 效率加成）
            $baseIncome = 100;
            $efficiencyBonus = $db->getEfficiencyBonus();
            $bonusIncome = round($baseIncome * $efficiencyBonus / 100);
            $totalIncome = $baseIncome + $bonusIncome;
            
            // 10%概率生病
            $gotSick = (mt_rand(1, 100) <= 10);
            if ($gotSick) {
                $db->makeSick();
                daemonLog("打工结束，获得{$totalIncome}元，但生病了！");
            } else {
                daemonLog("打工结束，获得{$totalIncome}元（基础{$baseIncome}元 + 效率{$efficiencyBonus}%）");
            }
            
            $db->earnMoney($totalIncome, '出门打工（+' . $efficiencyBonus . '%效率）' . ($gotSick ? '【生病】' : ''));
            $state['money'] = $db->getBalance();
            daemonLog("打工结束，获得{$totalIncome}元（基础{$baseIncome}元 + 效率{$efficiencyBonus}%）");
        } else {
            // 打工期间数值消耗：饥饿20、口渴15、睡眠15、快乐10、清洁15（5秒内均匀消耗）
            // 每秒消耗：饥饿4、口渴3、睡眠3、快乐2、清洁3
            $state['hunger'] = max(0, $state['hunger'] - ($elapsed * 4));
            $state['thirst'] = max(0, $state['thirst'] - ($elapsed * 3));
            $state['sleep'] = max(0, $state['sleep'] - ($elapsed * 3));
            $state['happy'] = max(0, $state['happy'] - ($elapsed * 2));
            $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * 3));
            $state['lastUpdate'] = $currentTime;
            return $state;
        }
    }

    // 如果正在学习
    $studyState = $db->getStudyState();
    if ($studyState['isStudying']) {
        $studyDuration = 5;
        $hasBookshelf = $db->hasFurniture('bookshelf');
        
        $studyElapsed = $currentTime - $studyState['startTime'];
        if ($studyElapsed >= $studyDuration) {
            // 学习完成，升级技能
            $skillId = $studyState['skillId'];
            $db->studySkill($skillId);
            $db->endStudy();
            daemonLog("学习完成，{$skillId} +1级" . ($hasBookshelf ? "（书柜加成）" : ""));
        } else {
            // 学习期间数值消耗：有书柜则消耗减半
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

    // 如果正在睡觉
    if ($state['isSleeping']) {
        $state['sleep'] = min(100, $state['sleep'] + ($elapsed * 0.5));
        // 睡觉时数值也下降，但速度减半（每10分钟下降0.5%）
        $sleepDecayRate = 0.5 / 600.0;
        
        // 检查是否生病，生病时掉落速度增加10倍
        $health = $db->getHealthState();
        if ($health['isSick']) {
            $sleepDecayRate *= 10; // 生病时10倍掉落速度
        }
        
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $sleepDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $sleepDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $sleepDecayRate));
        $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * $sleepDecayRate));

        if ($state['sleep'] >= 100) {
            $state['isSleeping'] = false;
            daemonLog("猫咪自然醒来");
        }
        $state['lastUpdate'] = $currentTime;
        return $state;
    }

    // 如果正在洗澡
    if ($state['isBathing']) {
        $state['cleanliness'] = min(100, $state['cleanliness'] + ($elapsed * 2));
        // 洗澡时数值也下降（每10分钟下降1%）
        $bathDecayRate = 1.0 / 600.0;
        
        // 检查是否生病，生病时掉落速度增加10倍
        $health = $db->getHealthState();
        if ($health['isSick']) {
            $bathDecayRate *= 10; // 生病时10倍掉落速度
        }
        
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $bathDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $bathDecayRate));
        $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $bathDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $bathDecayRate));

        if ($state['cleanliness'] >= 100) {
            $state['isBathing'] = false;
            $state['cleanliness'] = 100;
            daemonLog("猫咪洗完澡了");
        }
        $state['lastUpdate'] = $currentTime;
        return $state;
    }

    // 正常状态下的数值衰减 - 每10分钟下降1%
    // 10分钟 = 600秒，1% = 1点，所以每秒下降 1/600 = 0.00167
    $decayRate = 1.0 / 600.0; // 每10分钟下降1%
    
    // 检查是否生病，生病时掉落速度增加10倍
    $health = $db->getHealthState();
    if ($health['isSick']) {
        $decayRate *= 10; // 生病时10倍掉落速度
        daemonLog("猫咪生病中，数值掉落速度增加10倍");
    }
    
    $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $decayRate));
    $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $decayRate));
    $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $decayRate));
    $state['happy'] = max(0, $state['happy'] - ($elapsed * $decayRate));

    // 清洁度衰减
    $poops = $db->loadPoops();
    $poopCount = count($poops);
    $cleanlinessDecay = $decayRate + ($poopCount * 0.001);
    $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * $cleanlinessDecay));

    // 太脏或太渴会影响快乐值（额外惩罚）
    if ($state['cleanliness'] < 30 || $state['thirst'] < 30) {
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $decayRate));
    }

    $state['lastUpdate'] = $currentTime;
    return $state;
}

function checkPoopNeed($state, $db) {
    if ($state['isSleeping'] || $state['isWorking'] || $state['isBathing']) return false;
    
    // 检查是否正在学习
    $studyState = $db->getStudyState();
    if ($studyState['isStudying']) return false;

    $poops = $db->loadPoops();
    if (count($poops) >= 5) return false;

    // 获取数据库中最后一次拉屎时间
    $lastPoopTime = $db->getLastPoopTime();
    $timeSinceLastPoop = time() - $lastPoopTime;
    $sixHours = 21600; // 6小时 = 21600秒

    // 如果从未拉过屎（lastPoopTime=0），需要至少6小时
    if ($lastPoopTime == 0) {
        // 用猫咪的lastUpdate作为基准
        $timeSinceLastPoop = time() - $state['lastUpdate'];
        if ($timeSinceLastPoop < $sixHours) return false;
        return true;
    }

    // 必须等满6小时才会拉
    if ($timeSinceLastPoop < $sixHours) return false;

    // 6小时后100%拉屎
    return true;
}

function createPoopWithTime($state, $createdTime) {
    return [
        'id' => 'poop_' . $createdTime . '_' . mt_rand(1000, 9999),
        'x' => max(20, min(80, $state['catPosition']['x'] + (mt_rand(-50, 50) / 10))),
        'y' => max(30, min(80, $state['catPosition']['y'] + (mt_rand(-30, 50) / 10))),
        'created' => $createdTime
    ];
}

function randomMoveCat($state) {
    $state['catPosition']['x'] = max(20, min(80, 20 + mt_rand(0, 600) / 10));
    $state['catPosition']['y'] = max(30, min(80, 30 + mt_rand(0, 500) / 10));
    return $state;
}

function runDaemon($db) {
    daemonLog("猫咪守护进程启动 (PID: " . getmypid() . ")");
    savePid();

    $lastPoopCheck = 0;
    $lastMoveCheck = 0;

    while (true) {
        $currentTime = time();

        $state = $db->loadCatState();
        $poops = $db->loadPoops();

        $state = simulateCatLife($state, $currentTime, $db);

        if ($currentTime - $lastPoopCheck >= 300) {
            // 检查并处理累积的便便（使用应拉时间避免循环问题）
            while (checkPoopNeed($state, $db)) {
                $lastPoopTime = $db->getLastPoopTime();
                $sixHours = 21600;
                $poopCreatedTime = ($lastPoopTime > 0) ? ($lastPoopTime + $sixHours) : time();
                $newPoop = createPoopWithTime($state, $poopCreatedTime);
                $db->addPoop($newPoop);
                $state['totalPoops']++;
                $state['happy'] = max(0, $state['happy'] - 5);
                daemonLog("猫咪拉屎了: {$newPoop['id']}");
            }
            $lastPoopCheck = $currentTime;
        }

        if ($currentTime - $lastMoveCheck >= 600) {
            if (mt_rand(1, 100) <= 50 && !$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
                $state = randomMoveCat($state);
                daemonLog("猫咪移动到: {$state['catPosition']['x']}, {$state['catPosition']['y']}");
            }
            $lastMoveCheck = $currentTime;
        }

        $db->saveCatState($state);
        sleep(1);
    }
}

function runOnce($db) {
    daemonLog("单次运行模式");

    $currentTime = time();
    $state = $db->loadCatState();
    $poops = $db->loadPoops();

    $state = simulateCatLife($state, $currentTime, $db);

    // 检查并处理累积的便便（使用应拉时间避免循环问题）
    while (checkPoopNeed($state, $db)) {
        $lastPoopTime = $db->getLastPoopTime();
        $sixHours = 21600;
        $poopCreatedTime = ($lastPoopTime > 0) ? ($lastPoopTime + $sixHours) : time();
        $newPoop = createPoopWithTime($state, $poopCreatedTime);
        $db->addPoop($newPoop);
        $state['totalPoops']++;
        $state['happy'] = max(0, $state['happy'] - 5);
        daemonLog("猫咪拉屎了: {$newPoop['id']}");
    }

    if (mt_rand(1, 100) <= 30 && !$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
        $state = randomMoveCat($state);
        daemonLog("猫咪移动到: {$state['catPosition']['x']}, {$state['catPosition']['y']}");
    }

    $db->saveCatState($state);
    daemonLog("状态更新完成 - 余额: " . $db->getBalance() . "元");
}

$mode = $argv[1] ?? 'once';

switch ($mode) {
    case 'daemon':
        // 清理可能存在的旧PID文件
        if (file_exists($PID_FILE)) {
            $oldPid = trim(file_get_contents($PID_FILE));
            if (!empty($oldPid) && is_numeric($oldPid)) {
                // 检查是否真的是daemon.php进程
                $cmdline = @file_get_contents("/proc/$oldPid/cmdline");
                if (!$cmdline || strpos($cmdline, 'daemon.php') === false) {
                    // 不是我们的进程，删除PID文件
                    unlink($PID_FILE);
                } else if (file_exists("/proc/$oldPid")) {
                    daemonLog("守护进程已经在运行中 (PID: $oldPid)");
                    exit(1);
                } else {
                    unlink($PID_FILE);
                }
            } else {
                unlink($PID_FILE);
            }
        }

        // 直接运行守护进程（不fork，由调用者后台执行）
        runDaemon($db);
        break;

    case 'stop':
        if (file_exists($PID_FILE)) {
            $pid = file_get_contents($PID_FILE);
            if ($pid && file_exists("/proc/$pid")) {
                posix_kill($pid, SIGTERM);
                daemonLog("守护进程已停止 (PID: $pid)");
            }
            unlink($PID_FILE);
        }
        break;

    case 'status':
        if (isRunning()) {
            $pid = file_get_contents($PID_FILE);
            echo "守护进程正在运行 (PID: $pid)\n";
        } else {
            echo "守护进程未运行\n";
        }

        $state = $db->loadCatState();
        echo "\n猫咪状态:\n";
        echo "  饥饿: {$state['hunger']}%\n";
        echo "  口渴: {$state['thirst']}%\n";
        echo "  睡眠: {$state['sleep']}%\n";
        echo "  快乐: {$state['happy']}%\n";
        echo "  清洁: {$state['cleanliness']}%\n";
        echo "  金钱: {$db->getBalance()}元\n";
        echo "  睡觉中: " . ($state['isSleeping'] ? '是' : '否') . "\n";
        echo "  打工中: " . ($state['isWorking'] ? '是' : '否') . "\n";
        echo "  洗澡中: " . ($state['isBathing'] ? '是' : '否') . "\n";
        echo "  位置: {$state['catPosition']['x']}, {$state['catPosition']['y']}\n";
        echo "  上次更新: " . date('Y-m-d H:i:s', $state['lastUpdate']) . "\n";

        $poops = $db->loadPoops();
        echo "\n便便数量: " . count($poops) . "\n";
        break;

    case 'once':
    default:
        runOnce($db);
        break;
}

$db->close();
