<?php
/**
 * 电子小猫后端 API
 * 使用 SQLite 数据库保存数据
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once 'database.php';

// 数据存储路径
$DATA_DIR = __DIR__ . '/data';
$LOG_FILE = $DATA_DIR . '/activity.log';
$PID_FILE = $DATA_DIR . '/daemon.pid';

// 确保数据目录存在
if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0755, true);
}

// 自动启动守护进程（如果未运行）
function ensureDaemonRunning() {
    global $PID_FILE;
    
    // 使用文件锁防止竞态条件
    $lockFile = $PID_FILE . '.lock';
    $lockHandle = fopen($lockFile, 'c');
    if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        return; // 已有其他进程在处理
    }
    
    // 检查守护进程是否已在运行
    $isRunning = false;
    
    // 方法1: 检查 PID 文件和进程
    if (file_exists($PID_FILE)) {
        $pid = trim(file_get_contents($PID_FILE));
        if (!empty($pid) && is_numeric($pid)) {
            // 使用 posix_kill(0) 检查进程是否存在（适用于任何 PID）
            if (function_exists('posix_kill')) {
                $isRunning = @posix_kill(intval($pid), 0);
            } else {
                $isRunning = file_exists("/proc/$pid");
            }
        }
    }
    
    // 方法2: 直接查找 daemon.php 进程（更可靠）
    if (!$isRunning) {
        $output = [];
        exec("pgrep -f 'daemon.php daemon' 2>/dev/null", $output);
        if (!empty($output)) {
            $isRunning = true;
            // 更新 PID 文件
            file_put_contents($PID_FILE, trim($output[0]));
        }
    }
    
    // 如果未运行，启动守护进程
    if (!$isRunning) {
        $daemonScript = __DIR__ . '/daemon.php';
        $logFile = dirname($PID_FILE) . '/daemon.log';
        
        // 清除可能存在的旧PID文件
        if (file_exists($PID_FILE)) {
            unlink($PID_FILE);
        }
        
        // 使用 exec + nohup 启动真正的后台进程
        exec("cd " . escapeshellarg(__DIR__) . " && nohup php $daemonScript daemon > /dev/null 2>&1 &");
        
        // 等待守护进程启动并写入 PID 文件
        usleep(1000000); // 1秒
    }
    
    // 释放文件锁
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

// 每次 API 调用时确保守护进程在运行
ensureDaemonRunning();

// 初始化数据库
$db = new CatDatabase($DATA_DIR);

// 记录日志
function logActivity($message) {
    global $LOG_FILE;
    $line = date('Y-m-d H:i:s') . ' - ' . $message . "\n";
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// 模拟猫咪状态变化（核心函数）
function simulateCatLife($state, $currentTime) {
    $lastUpdate = $state['lastUpdate'];
    $elapsed = $currentTime - $lastUpdate;

    if ($elapsed <= 0) return $state;

    global $db;

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
                logActivity("打工结束，获得{$totalIncome}元，但生病了！");
            } else {
                logActivity("打工结束，获得{$totalIncome}元（基础{$baseIncome}元 + 效率{$efficiencyBonus}%）");
            }

            $db->earnMoney($totalIncome, '出门打工（+' . $efficiencyBonus . '%效率）' . ($gotSick ? '【生病】' : ''));
            $state['money'] = $db->getBalance();
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
        // 学习时间改为5秒（书柜不再减少时间，改为减少消耗）
        $studyDuration = 5;
        $hasBookshelf = $db->hasFurniture('bookshelf');

        $studyElapsed = $currentTime - $studyState['startTime'];
        if ($studyElapsed >= $studyDuration) {
            // 学习完成，升级技能
            $skillId = $studyState['skillId'];
            $db->studySkill($skillId);
            $db->endStudy();
            logActivity("学习完成，{$skillId} +1级" . ($hasBookshelf ? "（书柜加成）" : ""));
        } else {
            // 学习期间数值消耗：饥饿20、口渴15、睡眠15、快乐10、清洁15（5秒内均匀消耗）
            // 有书柜则消耗减半
            // 每秒消耗：饥饿4、口渴3、睡眠3、快乐2、清洁3
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
            $sleepDecayRate *= 10;
        }
        
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $sleepDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $sleepDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $sleepDecayRate));
        $state['cleanliness'] = max(0, $state['cleanliness'] - ($elapsed * $sleepDecayRate));

        if ($state['sleep'] >= 100) {
            $state['isSleeping'] = false;
            logActivity("猫咪自然醒来");
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
            $bathDecayRate *= 10;
        }
        
        $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $bathDecayRate));
        $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $bathDecayRate));
        $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $bathDecayRate));
        $state['happy'] = max(0, $state['happy'] - ($elapsed * $bathDecayRate));

        if ($state['cleanliness'] >= 100) {
            $state['isBathing'] = false;
            $state['cleanliness'] = 100;
            logActivity("猫咪洗完澡了");
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
    }
    
    $state['hunger'] = max(0, $state['hunger'] - ($elapsed * $decayRate));
    $state['thirst'] = max(0, $state['thirst'] - ($elapsed * $decayRate));
    $state['sleep'] = max(0, $state['sleep'] - ($elapsed * $decayRate));
    $state['happy'] = max(0, $state['happy'] - ($elapsed * $decayRate));

    // 清洁度衰减
    global $db;
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

// 检查是否需要拉屎（每6小时一次，可累积）
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

    // 如果从未拉过屎（lastPoopTime=0），用猫咪的lastUpdate作为基准
    if ($lastPoopTime == 0) {
        $timeSinceLastPoop = time() - $state['lastUpdate'];
        if ($timeSinceLastPoop < $sixHours) return false;
        return true;
    }

    // 必须等满6小时才会拉
    if ($timeSinceLastPoop < $sixHours) return false;

    // 6小时后100%拉屎
    return true;
}

// 计算累积的便便数量（离线期间可能累积多个）
// 使用“应拉时间”而非“实际拉时间”来避免累积循环问题
function calculateAccumulatedPoops($db) {
    $poops = $db->loadPoops();
    $currentCount = count($poops);
    if ($currentCount >= 5) return 0; // 已满

    $lastPoopTime = $db->getLastPoopTime();
    $sixHours = 21600;
    $currentTime = time();

    // 如果从未拉过屎，无法计算累积，返回0让checkPoopNeed处理
    if ($lastPoopTime == 0) return 0;

    // 计算从最后便便到现在过了多少个6小时周期
    $timeSinceLastPoop = $currentTime - $lastPoopTime;
    if ($timeSinceLastPoop < $sixHours) return 0;

    // 计算累积数量
    $accumulated = floor($timeSinceLastPoop / $sixHours);
    // 不超过便便上限
    $remaining = 5 - $currentCount;
    return min($accumulated, $remaining);
}

// 创建便便
function createPoop($state) {
    return [
        'id' => 'poop_' . time() . '_' . mt_rand(1000, 9999),
        'x' => max(20, min(80, $state['catPosition']['x'] + (mt_rand(-50, 50) / 10))),
        'y' => max(30, min(80, $state['catPosition']['y'] + (mt_rand(-30, 50) / 10))),
        'created' => time()
    ];
}

// 随机移动猫咪
function randomMoveCat($state) {
    $state['catPosition']['x'] = max(20, min(80, 20 + mt_rand(0, 600) / 10));
    $state['catPosition']['y'] = max(30, min(80, 30 + mt_rand(0, 500) / 10));
    return $state;
}

// 处理API请求
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'status':
        // 获取当前状态
        $state = $db->loadCatState();
        $poops = $db->loadPoops();
        $currentTime = time();

        // 检查并自动治愈（生病超过2小时）
        $justAutoCured = $db->autoCureIfNeeded();
        if ($justAutoCured) {
            logActivity("猫咪生病超过2小时，自动恢复健康！");
            $db->incrementStat('survive_sick');
        }

        // 模拟离线期间的变化
        $state = simulateCatLife($state, $currentTime);

        // 尝试触发随机事件
        $randomEvent = $db->tryRandomEvent($state);
        if ($randomEvent) {
            $state = $randomEvent['state'];
            logActivity("随机事件: {$randomEvent['name']}");
        }

        // 检查是否需要拉屎（累积多个）
        $accumulatedPoops = calculateAccumulatedPoops($db);
        for ($i = 0; $i < $accumulatedPoops; $i++) {
            // 累积便便：created时间用“应拉时间”而非当前时间
            // 这样getLastPoopTime()返回的是最后一个便便的应拉时间，而不是当前时间
            // 避免累积循环中每次添加便便后时间重置的问题
            $lastPoopTime = $db->getLastPoopTime();
            $sixHours = 21600;
            $poopCreatedTime = ($lastPoopTime > 0) ? ($lastPoopTime + $sixHours) : time();
            $newPoop = [
                'id' => 'poop_' . $poopCreatedTime . '_' . mt_rand(1000, 9999),
                'x' => max(20, min(80, $state['catPosition']['x'] + (mt_rand(-50, 50) / 10))),
                'y' => max(30, min(80, $state['catPosition']['y'] + (mt_rand(-30, 50) / 10))),
                'created' => $poopCreatedTime
            ];
            $db->addPoop($newPoop);
            $state['totalPoops']++;
            $state['happy'] = max(0, $state['happy'] - 5);
            logActivity("猫咪拉屎了: {$newPoop['id']}");
        }

        // 随机移动
        $elapsed = $currentTime - $state['lastUpdate'];
        if ($elapsed > 300 && mt_rand(1, 100) <= 30) {
            $state = randomMoveCat($state);
        }

        // 更新天数统计
        $db->updateDaysPlayed();
        // 检查全属性满
        $db->checkAllStatsFull($state);
        // 检查满级技能
        $db->checkMaxSkills();

        // 保存状态
        $db->saveCatState($state);

        // 检查成就
        $newAchievements = $db->checkAndUnlockAchievements();

        echo json_encode([
            'success' => true,
            'state' => $state,
            'poops' => $db->loadPoops(),
            'serverTime' => $currentTime,
            'randomEvent' => $randomEvent ? [
                'id' => $randomEvent['id'],
                'name' => $randomEvent['name'],
                'description' => $randomEvent['description'],
                'icon' => $randomEvent['icon'],
                'category' => $randomEvent['category'],
                'effects' => $randomEvent['effects']
            ] : null,
            'newAchievements' => $newAchievements
        ]);
        break;

    case 'feed':
        // 吃东西 - 需要选择食物
        $foodType = $_POST['foodType'] ?? '';

        if (empty($foodType)) {
            echo json_encode(['success' => false, 'error' => 'no_food_selected', 'message' => '请选择食物']);
            break;
        }

        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        // 检查忙碌状态
        if ($state['isSleeping']) {
            echo json_encode(['success' => false, 'error' => 'sleeping', 'message' => '猫咪正在睡觉，无法喂食']);
            break;
        }
        if ($state['isWorking']) {
            echo json_encode(['success' => false, 'error' => 'working', 'message' => '猫咪正在打工，无法喂食']);
            break;
        }
        if ($state['isBathing']) {
            echo json_encode(['success' => false, 'error' => 'bathing', 'message' => '猫咪正在洗澡，无法喂食']);
            break;
        }
        // 检查是否正在学习
        $studyState = $db->getStudyState();
        if ($studyState['isStudying']) {
            echo json_encode(['success' => false, 'error' => 'studying', 'message' => '猫咪正在学习，无法喂食']);
            break;
        }

        // 检查是否有该食物
        $foodQty = $db->getItemQuantity($foodType);
        if ($foodQty <= 0) {
            echo json_encode(['success' => false, 'error' => 'no_food', 'message' => '没有这种食物了']);
            break;
        }

        // 消耗食物
        if (!$db->consumeItem($foodType, 1)) {
            echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗食物失败']);
            break;
        }

        // 根据食物类型增加不同数值
        switch ($foodType) {
            case 'cat_food': // 猫粮
                $state['hunger'] = min(100, $state['hunger'] + 20);
                $state['happy'] = min(100, $state['happy'] + 2);
                logActivity("吃猫粮，饥饿值+20");
                break;
            case 'fish_dry': // 小鱼干
                $state['hunger'] = min(100, $state['hunger'] + 35);
                $state['happy'] = min(100, $state['happy'] + 10);
                logActivity("吃小鱼干，饥饿值+35，快乐值+10");
                break;
            case 'chicken': // 烤鸡腿
                $state['hunger'] = min(100, $state['hunger'] + 50);
                $state['happy'] = min(100, $state['happy'] + 15);
                $state['thirst'] = max(0, $state['thirst'] - 5); // 有点干
                logActivity("吃烤鸡腿，饥饿值+50，快乐值+15，口渴-5");
                break;
            case 'sushi': // 三文鱼寿司
                $state['hunger'] = min(100, $state['hunger'] + 45);
                $state['happy'] = min(100, $state['happy'] + 20);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 5); // 精致食物
                logActivity("吃三文鱼寿司，饥饿值+45，快乐值+20，清洁+5");
                break;
            case 'steak': // 牛排大餐
                $state['hunger'] = min(100, $state['hunger'] + 70);
                $state['happy'] = min(100, $state['happy'] + 25);
                $state['sleep'] = min(100, $state['sleep'] + 10); // 满足感
                logActivity("吃牛排大餐，饥饿值+70，快乐值+25，睡眠+10");
                break;
            case 'lobster': // 波士顿龙虾
                $state['hunger'] = min(100, $state['hunger'] + 60);
                $state['happy'] = min(100, $state['happy'] + 30);
                $state['thirst'] = min(100, $state['thirst'] + 20);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 10);
                logActivity("吃波士顿龙虾，饥饿值+60，快乐值+30，口渴+20，清洁+10");
                break;
            case 'golden_food': // 黄金猫粮
                $state['hunger'] = 100;
                $state['happy'] = 100;
                $state['thirst'] = min(100, $state['thirst'] + 30);
                $state['sleep'] = min(100, $state['sleep'] + 20);
                $state['cleanliness'] = 100;
                logActivity("吃黄金猫粮，所有属性大幅提升！");
                break;
        }

        $db->saveCatState($state);
        $db->incrementStat('feed_count');
        $db->checkAllStatsFull($state);
        $newAchievements = $db->checkAndUnlockAchievements();
        echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory(), 'newAchievements' => $newAchievements]);
        break;

    case 'drink':
        // 喝东西 - 需要选择饮料
        $drinkType = $_POST['drinkType'] ?? '';

        if (empty($drinkType)) {
            echo json_encode(['success' => false, 'error' => 'no_drink_selected', 'message' => '请选择饮料']);
            break;
        }

        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        // 检查忙碌状态
        if ($state['isSleeping']) {
            echo json_encode(['success' => false, 'error' => 'sleeping', 'message' => '猫咪正在睡觉，无法喝水']);
            break;
        }
        if ($state['isWorking']) {
            echo json_encode(['success' => false, 'error' => 'working', 'message' => '猫咪正在打工，无法喝水']);
            break;
        }
        if ($state['isBathing']) {
            echo json_encode(['success' => false, 'error' => 'bathing', 'message' => '猫咪正在洗澡，无法喝水']);
            break;
        }
        $studyState = $db->getStudyState();
        if ($studyState['isStudying']) {
            echo json_encode(['success' => false, 'error' => 'studying', 'message' => '猫咪正在学习，无法喝水']);
            break;
        }

        // 检查是否有该饮料
        $drinkQty = $db->getItemQuantity($drinkType);
        if ($drinkQty <= 0) {
            echo json_encode(['success' => false, 'error' => 'no_drink', 'message' => '没有这种饮料了']);
            break;
        }

        // 消耗饮料
        if (!$db->consumeItem($drinkType, 1)) {
            echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗饮料失败']);
            break;
        }

        // 根据饮料类型增加不同数值
        switch ($drinkType) {
            case 'milk': // 牛奶
                $state['hunger'] = min(100, $state['hunger'] + 5);
                $state['thirst'] = min(100, $state['thirst'] + 25);
                $state['happy'] = min(100, $state['happy'] + 3);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 2);
                logActivity("喝牛奶，饥饿+5，口渴+25，快乐+3，清洁+2");
                break;
            case 'cola': // 可乐
                $state['hunger'] = min(100, $state['hunger'] + 3);
                $state['thirst'] = min(100, $state['thirst'] + 30);
                $state['happy'] = min(100, $state['happy'] + 15);
                $state['sleep'] = max(0, $state['sleep'] - 5); // 可乐让人兴奋，睡眠-5
                logActivity("喝可乐，饥饿+3，口渴+30，快乐+15，睡眠-5");
                break;
            case 'coconut_water': // 高级椰子水
                $state['hunger'] = min(100, $state['hunger'] + 8);
                $state['thirst'] = min(100, $state['thirst'] + 40);
                $state['happy'] = min(100, $state['happy'] + 8);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 10);
                $state['sleep'] = min(100, $state['sleep'] + 5);
                logActivity("喝高级椰子水，饥饿+8，口渴+40，快乐+8，清洁+10，睡眠+5");
                break;
            case 'orange_juice': // 鲜榨橙汁
                $state['hunger'] = min(100, $state['hunger'] + 5);
                $state['thirst'] = min(100, $state['thirst'] + 35);
                $state['happy'] = min(100, $state['happy'] + 12);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 5);
                logActivity("喝鲜榨橙汁，饥饿+5，口渴+35，快乐+12，清洁+5");
                break;
            case 'coffee': // 猫屎咖啡
                $state['hunger'] = min(100, $state['hunger'] + 2);
                $state['thirst'] = min(100, $state['thirst'] + 20);
                $state['happy'] = min(100, $state['happy'] + 20);
                $state['sleep'] = max(0, $state['sleep'] - 15); // 咖啡提神，睡眠-15
                logActivity("喝猫屎咖啡，饥饿+2，口渴+20，快乐+20，睡眠-15");
                break;
            case 'energy_drink': // 能量饮料
                $state['hunger'] = min(100, $state['hunger'] + 5);
                $state['thirst'] = min(100, $state['thirst'] + 50);
                $state['happy'] = min(100, $state['happy'] + 10);
                $state['sleep'] = min(100, $state['sleep'] + 10); // 恢复体力
                logActivity("喝能量饮料，饥饿+5，口渴+50，快乐+10，睡眠+10");
                break;
            case 'magic_potion': // 魔法药水
                $state['hunger'] = min(100, $state['hunger'] + 30);
                $state['thirst'] = 100;
                $state['happy'] = min(100, $state['happy'] + 25);
                $state['sleep'] = min(100, $state['sleep'] + 20);
                $state['cleanliness'] = min(100, $state['cleanliness'] + 20);
                logActivity("喝魔法药水，所有数值大幅提升！");
                break;
        }

        $db->saveCatState($state);
        $db->incrementStat('drink_count');
        $db->checkAllStatsFull($state);
        $newAchievements = $db->checkAndUnlockAchievements();
        echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory(), 'newAchievements' => $newAchievements]);
        break;

    case 'sleep':
        $state = $db->loadCatState();
        $currentTime = time();
        $wasSleeping = $state['isSleeping'];
        
        // 先模拟状态变化
        $state = simulateCatLife($state, $currentTime);
        if (!$wasSleeping && (($state['currentRoom'] ?? 'living_room') !== 'bedroom')) {
            echo json_encode(['success' => false, 'error' => 'wrong_room', 'message' => '猫咪只能在卧室睡觉']);
            break;
        }
        
        // 如果之前是睡觉状态，且simulateCatLife没有自动醒来（睡眠值未满），则手动切换为醒来
        // 如果之前不是睡觉状态，则切换为睡觉
        if ($wasSleeping && $state['isSleeping']) {
            // 用户主动点击起床
            $state['isSleeping'] = false;
            logActivity("猫咪醒来（用户主动）");
        } elseif (!$wasSleeping) {
            // 用户主动点击睡觉
            $state['isSleeping'] = true;
            logActivity("猫咪开始睡觉");
        }
        // 如果 wasSleeping=true 且 state['isSleeping']=false，说明自然醒来，不需要额外操作

        $db->saveCatState($state);
        if (!$wasSleeping) {
            $db->incrementStat('sleep_count');
        }
        echo json_encode(['success' => true, 'state' => $state]);
        break;

    case 'play':
        // 玩耍 - 需要选择玩具
        $toyType = $_POST['toyType'] ?? '';

        if (empty($toyType)) {
            echo json_encode(['success' => false, 'error' => 'no_toy_selected', 'message' => '请选择玩具']);
            break;
        }

        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if (!$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
            // 检查是否有该玩具
            $toyQty = $db->getItemQuantity($toyType);
            if ($toyQty <= 0 && $toyType !== 'robot_mouse') {
                echo json_encode(['success' => false, 'error' => 'no_toy', 'message' => '没有这种玩具了']);
                break;
            }

            // 电子机器鼠无限使用，但需要确认仓库中确实拥有
            if ($toyType === 'robot_mouse') {
                $toyQty = $db->getItemQuantity('robot_mouse');
                if ($toyQty <= 0) {
                    echo json_encode(['success' => false, 'error' => 'no_toy', 'message' => '没有电子机器鼠了']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 40);
                $state['hunger'] = max(0, $state['hunger'] - 3);
                $state['sleep'] = max(0, $state['sleep'] - 3);
                logActivity("玩耍: ");
                $db->incrementStat('play_count');
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'cat_stick') {
                // 逗猫棒需要消耗
                if (!$db->consumeItem('cat_stick', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗玩具失败']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 30);
                $state['hunger'] = max(0, $state['hunger'] - 3);
                $state['sleep'] = max(0, $state['sleep'] - 3);
                logActivity("玩耍: ");
                $db->incrementStat('play_count');
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'ball') {
                // 毛线球
                if (!$db->consumeItem('ball', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗玩具失败']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 25);
                $state['hunger'] = max(0, $state['hunger'] - 2);
                $state['sleep'] = max(0, $state['sleep'] - 2);
                logActivity("玩毛线球，消耗1个，快乐值+25");
                logActivity("玩毛线球，消耗1个，快乐值+25");
                $db->incrementStat('play_count');
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else if ($toyType === 'feather') {
                // 羽毛棒
                if (!$db->consumeItem('feather', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗玩具失败']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 35);
                $state['hunger'] = max(0, $state['hunger'] - 4);
                $state['sleep'] = max(0, $state['sleep'] - 4);
                logActivity("玩羽毛棒，消耗1个，快乐值+35");
                $db->saveCatState($state);
                logActivity("玩羽毛棒，消耗1个，快乐值+35");
                $db->incrementStat('play_count');
                break;
            } else if ($toyType === 'laser') {
                // 激光笔
                if (!$db->consumeItem('laser', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗玩具失败']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 45);
                $state['hunger'] = max(0, $state['hunger'] - 5);
                $state['sleep'] = max(0, $state['sleep'] - 5);
                logActivity("玩激光笔，消耗1个，快乐值+45");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                logActivity("玩激光笔，消耗1个，快乐值+45");
                $db->incrementStat('play_count');
            } else if ($toyType === 'drone') {
                // 无人机
                if (!$db->consumeItem('drone', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗玩具失败']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 50);
                $state['hunger'] = max(0, $state['hunger'] - 6);
                $state['sleep'] = max(0, $state['sleep'] - 6);
                logActivity("玩无人机，消耗1个，快乐值+50");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
                logActivity("玩无人机，消耗1个，快乐值+50");
                $db->incrementStat('play_count');
                // VR眼镜
                if (!$db->consumeItem('vr_headset', 1)) {
                    echo json_encode(['success' => false, 'error' => 'consume_failed', 'message' => '消耗玩具失败']);
                    break;
                }
                $state['happy'] = min(100, $state['happy'] + 60);
                $state['hunger'] = max(0, $state['hunger'] - 8);
                $state['sleep'] = max(0, $state['sleep'] - 10);
                logActivity("玩VR眼镜，消耗1个，快乐值+60");
                $db->saveCatState($state);
                echo json_encode(['success' => true, 'state' => $state, 'inventory' => $db->getInventory()]);
                break;
            } else {
                logActivity("玩VR眼镜，消耗1个，快乐值+60");
                $db->incrementStat('play_count');
                echo json_encode(['success' => false, 'error' => 'unknown_toy', 'message' => '未知玩具类型']);
                break;
            }
        } else {
            // 忙碌状态
            $reason = '';
            if ($state['isSleeping']) $reason = '猫咪正在睡觉';
            elseif ($state['isWorking']) $reason = '猫咪正在打工';
            elseif ($state['isBathing']) $reason = '猫咪正在洗澡';
            echo json_encode(['success' => false, 'error' => 'busy', 'message' => $reason]);
            break;
        }

    case 'work':
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        // 验证最低数值要求
        $required = ['hunger' => 20, 'thirst' => 15, 'sleep' => 15, 'happy' => 10, 'cleanliness' => 15];
        $shortages = [];
        foreach ($required as $key => $min) {
            if (($state[$key] ?? 0) < $min) {
                $shortages[] = $key . '(' . round($state[$key]) . '/' . $min . ')';
            }
        }
        if (!empty($shortages)) {
            echo json_encode(['success' => false, 'error' => '数值不足: ' . implode('，', $shortages)]);
            break;
        }

        if (!$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
            $state['isWorking'] = true;
            $state['workStartTime'] = time();
            logActivity("开始打工");
        }

        $db->saveCatState($state);
        $db->incrementStat('work_count');
        echo json_encode(['success' => true, 'state' => $state]);
        break;

    case 'bathe':
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if ($state['isBathing']) {
            echo json_encode(['success' => false, 'error' => '已经在洗澡中']);
            break;
        }
        if ($state['isSleeping']) {
            echo json_encode(['success' => false, 'error' => '正在睡觉中']);
            break;
        }
        if ($state['isWorking']) {
            echo json_encode(['success' => false, 'error' => '正在打工中']);
            break;
        }
        if ($state['cleanliness'] >= 100) {
            echo json_encode(['success' => false, 'error' => '已经很干净了']);
            break;
        }

        $state['isBathing'] = true;
        logActivity("开始给猫咪洗澡");
        $db->saveCatState($state);
        $db->incrementStat('bathe_count');
        echo json_encode(['success' => true, 'state' => $state]);
        break;

    case 'clean':
        $poopId = $_POST['poopId'] ?? '';
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        $db->removePoop($poopId);

        $state['happy'] = min(100, $state['happy'] + 10);
        $state['cleanliness'] = min(100, $state['cleanliness'] + 5);
        $db->saveCatState($state);
        logActivity("清理便便: $poopId");
        $db->incrementStat('clean_poop_count');

        echo json_encode(['success' => true, 'state' => $state, 'poops' => $db->loadPoops()]);
        break;

    case 'pet':
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if (!$state['isSleeping'] && !$state['isWorking'] && !$state['isBathing']) {
            // 只有快乐值 <= 50% 时，抚摸才增加快乐值
            if ($state['happy'] <= 50) {
                $state['happy'] = min(100, $state['happy'] + 10);
                logActivity("抚摸猫咪，快乐值+10");
                $message = '抚摸猫咪，快乐值+10';
            } else {
                // 快乐值已经很高了，抚摸只是表达爱意
                logActivity("抚摸猫咪，猫咪很开心");
                $message = '猫咪已经很快乐了，继续抚摸表达你的爱意吧';
            }
        }

        $db->saveCatState($state);
        echo json_encode(['success' => true, 'state' => $state, 'message' => $message ?? '']);
        break;

    case 'move':
        $x = floatval($_POST['x'] ?? 50);
        $y = floatval($_POST['y'] ?? 50);

        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());
        $state['catPosition']['x'] = max(20, min(80, $x));
        $state['catPosition']['y'] = max(30, min(80, $y));
        $state['lastUpdate'] = time();
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
        // 获取技能列表、效率和学习状态
        echo json_encode([
            'success' => true,
            'skills' => $db->getSkills(),
            'efficiency' => $db->getEfficiencyBonus(),
            'studyState' => $db->getStudyState()
        ]);
        break;

    case 'study':
        // 开始学习技能
        $skillId = $_POST['skillId'] ?? '';
        if (empty($skillId)) {
            echo json_encode(['success' => false, 'error' => '请选择技能']);
            break;
        }

        // 检查是否已经在学习或打工或睡觉或洗澡
        $studyState = $db->getStudyState();
        $state = $db->loadCatState();
        if ($studyState['isStudying']) {
            echo json_encode(['success' => false, 'error' => '正在学习中']);
            break;
        }
        if ($state['isWorking']) {
            echo json_encode(['success' => false, 'error' => '正在打工中']);
            break;
        }
        if ($state['isSleeping']) {
            echo json_encode(['success' => false, 'error' => '正在睡觉中']);
            break;
        }
        if ($state['isBathing']) {
            echo json_encode(['success' => false, 'error' => '正在洗澡中']);
            break;
        }

        // 检查技能是否已满级
        $skills = $db->getSkills();
        $skill = array_filter($skills, function($s) use ($skillId) {
            return $s['id'] === $skillId;
        });
        $skill = array_values($skill)[0] ?? null;
        if (!$skill || $skill['level'] >= $skill['maxLevel']) {
            echo json_encode(['success' => false, 'error' => '技能已满级']);
            break;
        }

        // 验证最低数值要求
        $studyRequired = ['hunger' => 20, 'thirst' => 15, 'sleep' => 15, 'happy' => 10, 'cleanliness' => 15];
        $studyShortages = [];
        $state = simulateCatLife($state, time());
        foreach ($studyRequired as $key => $min) {
            if (($state[$key] ?? 0) < $min) {
                $studyShortages[] = $key . '(' . round($state[$key]) . '/' . $min . ')';
            }
        }
        if (!empty($studyShortages)) {
            echo json_encode(['success' => false, 'error' => '数值不足: ' . implode('，', $studyShortages)]);
            break;
        }

        // 开始学习
        $db->startStudy($skillId);
        $db->incrementStat('study_count');
        logActivity("开始学习: $skillId");
        echo json_encode([
            'success' => true,
            'message' => '开始学习',
            'studyState' => $db->getStudyState()
        ]);
        break;

    case 'upgrade':
        // 升级打工效率
        $result = $db->upgradeEfficiency();
        if ($result['success']) {
            logActivity("升级打工效率至 +{$result['bonus']}%");
        }
        echo json_encode($result);
        break;

    case 'health':
        // 获取健康状态
        echo json_encode([
            'success' => true,
            'health' => $db->getHealthState()
        ]);
        break;

    case 'hospital':
        // 去医院看病
        $health = $db->getHealthState();

        if (!$health['isSick']) {
            echo json_encode(['success' => false, 'error' => 'not_sick', 'message' => '小猫咪很健康，无需上医院！']);
            break;
        }

        // 检查余额
        $balance = $db->getBalance();
        if ($balance < 500) {
            echo json_encode(['success' => false, 'error' => 'no_money', 'message' => '余额不足，需要500元！']);
            break;
        }

        // 扣钱并治愈
        $db->spendMoney(500, '去医院看病');
        $db->cureSick();
        logActivity("去医院看病，花费500元，恢复健康！");

        echo json_encode([
            'success' => true,
            'message' => '小猫咪恢复健康了！',
            'balance' => $db->getBalance(),
            'health' => $db->getHealthState()
        ]);
        break;

    case 'wardrobe':
        // 获取衣柜
        echo json_encode([
            'success' => true,
            'items' => $db->getWardrobe(),
            'equipped' => $db->getEquippedClothes()
        ]);
        break;

    case 'buy_clothes':
        // 购买衣服
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => 'no_item']);
            break;
        }

        // 获取商品信息
        $item = $db->getShopItem($itemId);

        if (!$item || $item['category'] !== 'clothes') {
            echo json_encode(['success' => false, 'error' => 'item_not_found']);
            break;
        }

        // 检查是否已拥有
        $wardrobe = $db->getWardrobe();
        foreach ($wardrobe as $w) {
            if ($w['id'] === $itemId) {
                echo json_encode(['success' => false, 'error' => 'already_owned', 'message' => '已拥有该衣服']);
                break 2;
            }
        }

        // 检查余额
        $balance = $db->getBalance();
        if ($balance < $item['price']) {
            echo json_encode(['success' => false, 'error' => 'no_money', 'message' => '余额不足']);
            break;
        }

        // 扣钱并购买
        $db->spendMoney($item['price'], '购买' . $item['name']);
        $result = $db->buyClothes($itemId, $item['name']);

        if ($result['success']) {
            logActivity("购买衣服：{$item['name']}");
            echo json_encode([
                'success' => true,
                'message' => '购买成功！',
                'balance' => $db->getBalance(),
                'wardrobe' => $db->getWardrobe()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
        break;

    case 'equip':
        // 穿上衣服
        $itemId = $_POST['itemId'] ?? '';
        $result = $db->equipClothes($itemId);
        echo json_encode([
            'success' => $result['success'],
            'equipped' => $db->getEquippedClothes()
        ]);
        break;

    case 'reset':
        // 重置游戏 - 需要确认参数防止误操作
        $confirm = $_POST['confirm'] ?? $_GET['confirm'] ?? '';
        if ($confirm !== 'RESET_GAME_CONFIRM') {
            echo json_encode(['success' => false, 'error' => 'confirmation_required', 'message' => '请传入 confirm=RESET_GAME_CONFIRM 参数确认重置']);
            break;
        }
        $db->resetGame();
        logActivity("游戏重置");
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
        // 获取已购买的家具
        echo json_encode(['success' => true, 'furniture' => $db->getFurniture()]);
        break;

    case 'buy_furniture':
        // 购买家具
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => '家具ID不能为空']);
            break;
        }
        $result = $db->buyFurniture($itemId);
        $result['balance'] = $db->getBalance();
        $result['furniture'] = $db->getFurniture();
        echo json_encode($result);
        break;

    case 'use_sofa':
        // 使用沙发 - 只有卧室允许恢复睡眠，避免客厅变相睡觉
        if (!$db->hasFurniture('sofa')) {
            echo json_encode(['success' => false, 'error' => 'no_sofa', 'message' => '没有沙发']);
            break;
        }
        $state = $db->loadCatState();
        if (($state['currentRoom'] ?? 'living_room') !== 'bedroom') {
            echo json_encode(['success' => false, 'error' => 'wrong_room', 'message' => '睡觉和恢复睡眠只能在卧室进行']);
            break;
        }
        $state['sleep'] = 100;
        $db->saveCatState($state);
        logActivity("在卧室休息，睡眠值恢复100%");
        echo json_encode(['success' => true, 'state' => $state, 'message' => '睡眠值恢复100%！']);
        break;

    case 'use_computer':
        // 使用电脑 - 快乐值立即100%
        if (!$db->hasFurniture('computer')) {
            echo json_encode(['success' => false, 'error' => 'no_computer', 'message' => '没有电脑']);
            break;
        }
        $state = $db->loadCatState();
        $state['happy'] = 100;
        $db->saveCatState($state);
        logActivity("玩电脑游戏，快乐值恢复100%");
        echo json_encode(['success' => true, 'state' => $state, 'message' => '快乐值恢复100%！']);
        break;

    case 'use_mystery_device':
        // 使用神秘设备 - 所有数值全满
        if (!$db->hasFurniture('mystery_device')) {
            echo json_encode(['success' => false, 'error' => 'no_device', 'message' => '没有神秘设备']);
            break;
        }
        $state = $db->loadCatState();
        $state['hunger'] = 100;
        $state['thirst'] = 100;
        $state['sleep'] = 100;
        $state['happy'] = 100;
        $state['cleanliness'] = 100;
        $db->saveCatState($state);
        logActivity("使用神秘设备，所有数值恢复全满！");
        echo json_encode(['success' => true, 'state' => $state, 'message' => '所有数值恢复全满！']);
        break;

    case 'investments':
        // 获取已购买的投资项目
        echo json_encode(['success' => true, 'investments' => $db->getInvestments()]);
        break;

    case 'buy_investment':
        // 购买投资
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => '投资ID不能为空']);
            break;
        }

        // 获取投资信息
        $item = $db->getShopItem($itemId);
        if (!$item || $item['category'] !== 'investment') {
            echo json_encode(['success' => false, 'error' => '投资不存在']);
            break;
        }

        // 检查是否已拥有
        if ($db->hasInvestment($itemId)) {
            echo json_encode(['success' => false, 'error' => 'already_owned', 'message' => '已拥有该投资']);
            break;
        }

        // 检查余额
        $balance = $db->getBalance();
        if ($balance < $item['price']) {
            echo json_encode(['success' => false, 'error' => 'no_money', 'message' => '余额不足']);
            break;
        }

        // 扣钱并购买
        $db->spendMoney($item['price'], '购买投资：' . $item['name']);
        $result = $db->buyInvestment($itemId, $item['name']);

        if ($result['success']) {
            logActivity("购买投资：{$item['name']}");
            echo json_encode([
                'success' => true,
                'message' => '购买成功！',
                'balance' => $db->getBalance(),
                'investments' => $db->getInvestments()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
        break;

    case 'claim_investment':
        // 领取投资收益（计算并领取自上次以来的所有收益）
        $income = $db->calculateInvestmentIncome();
        if ($income > 0) {
            $db->earnMoney($income, '投资收益');
            logActivity("领取投资收益：{$income}元");
            $state = $db->loadCatState();
            $state['money'] = $db->getBalance();
            $db->saveCatState($state);
            echo json_encode([
                'success' => true,
                'income' => $income,
                'balance' => $db->getBalance(),
                'message' => "领取投资收益 {$income} 元！"
            ]);
        } else {
            echo json_encode(['success' => true, 'income' => 0, 'message' => '暂无收益可领取']);
        }
        break;

    case 'shop':
        // 获取商店商品列表
        echo json_encode(['success' => true, 'items' => $db->getShopItems()]);
        break;

    case 'lottery_spin':
        $cost = 100;
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());

        if (($state['currentRoom'] ?? 'living_room') !== 'amusement_park') {
            echo json_encode(['success' => false, 'error' => 'wrong_room', 'message' => '只有在游乐园才能玩抽奖大转盘']);
            break;
        }
        if ($state['isSleeping'] || $state['isWorking'] || $state['isBathing']) {
            echo json_encode(['success' => false, 'error' => 'busy', 'message' => '猫咪正在忙碌，暂时不能抽奖']);
            break;
        }
        $studyState = $db->getStudyState();
        if ($studyState['isStudying']) {
            echo json_encode(['success' => false, 'error' => 'busy', 'message' => '猫咪正在学习，暂时不能抽奖']);
            break;
        }

        if ($db->getBalance() < $cost) {
            echo json_encode(['success' => false, 'error' => 'no_money', 'message' => '余额不足，抽奖需要 100 元']);
            break;
        }

        $db->spendMoney($cost, '游乐园抽奖大转盘');
        $state['money'] = $db->getBalance();

        // 50% 固定未中奖。
        $isMiss = random_int(1, 1000000) <= 500000;
        if ($isMiss) {
            $db->saveCatState($state);
            logActivity("游乐园抽奖：未中奖");
            echo json_encode([
                'success' => true,
                'won' => false,
                'message' => '未中奖，谢谢惠顾',
                'cost' => $cost,
                'state' => $state,
                'balance' => $db->getBalance()
            ]);
            break;
        }

        $wardrobeIds = array_column($db->getWardrobe(), 'id');
        $furnitureIds = array_column($db->getFurniture(), 'id');
        $investmentIds = array_column($db->getInvestments(), 'id');
        $pool = [];
        $totalWeight = 0.0;

        foreach ($db->getShopItems() as $item) {
            $category = $item['category'] ?? 'other';
            if ($category === 'clothes' && in_array($item['id'], $wardrobeIds, true)) {
                continue;
            }
            if ($category === 'furniture' && in_array($item['id'], $furnitureIds, true)) {
                continue;
            }
            if ($category === 'investment' && in_array($item['id'], $investmentIds, true)) {
                continue;
            }

            // 指数衰减：价格越高，权重按 e^(-price/20000) 快速下降。
            $weight = exp(-((float)$item['price']) / 20000.0);
            if ($weight <= 0) {
                continue;
            }
            $item['lotteryWeight'] = $weight;
            $pool[] = $item;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0 || empty($pool)) {
            $db->saveCatState($state);
            logActivity("游乐园抽奖：奖池为空，未中奖");
            echo json_encode([
                'success' => true,
                'won' => false,
                'message' => '未中奖，谢谢惠顾',
                'cost' => $cost,
                'state' => $state,
                'balance' => $db->getBalance()
            ]);
            break;
        }

        $roll = (random_int(0, 1000000) / 1000000.0) * $totalWeight;
        $selected = $pool[count($pool) - 1];
        foreach ($pool as $item) {
            $roll -= $item['lotteryWeight'];
            if ($roll <= 0) {
                $selected = $item;
                break;
            }
        }

        $awarded = $db->awardShopPrize($selected);
        if (!$awarded) {
            $db->saveCatState($state);
            logActivity("游乐园抽奖：奖品发放失败，未中奖");
            echo json_encode([
                'success' => true,
                'won' => false,
                'message' => '未中奖，谢谢惠顾',
                'cost' => $cost,
                'state' => $state,
                'balance' => $db->getBalance()
            ]);
            break;
        }

        $state['money'] = $db->getBalance();
        $state['happy'] = min(100, ($state['happy'] ?? 80) + 3);
        $db->saveCatState($state);
        logActivity("游乐园抽奖中奖：{$selected['name']}");

        echo json_encode([
            'success' => true,
            'won' => true,
            'message' => "恭喜中奖：{$selected['name']}！",
            'cost' => $cost,
            'prize' => [
                'id' => $selected['id'],
                'name' => $selected['name'],
                'price' => $selected['price'],
                'description' => $selected['description'],
                'icon' => $selected['icon'],
                'category' => $selected['category']
            ],
            'state' => $state,
            'balance' => $db->getBalance(),
            'inventory' => $db->getInventory(),
            'wardrobe' => $db->getWardrobe(),
            'furniture' => $db->getFurniture(),
            'investments' => $db->getInvestments()
        ]);
        break;

    case 'inventory':
        // 获取仓库物品
        echo json_encode(['success' => true, 'items' => $db->getInventory()]);
        break;

    case 'flea_market':
        echo json_encode([
            'success' => true,
            'items' => $db->getFleaMarketItems(),
            'balance' => $db->getBalance()
        ]);
        break;

    case 'sell_item':
        $itemId = $_POST['itemId'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 1);

        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => 'no_item', 'message' => '请选择要卖出的道具']);
            break;
        }

        $result = $db->sellInventoryItem($itemId, $quantity);
        if ($result['success']) {
            $state = $db->loadCatState();
            logActivity("跳蚤市场卖出：{$result['itemName']} x{$result['quantity']}，收入 {$result['income']} 元");
            echo json_encode([
                'success' => true,
                'message' => "卖出 {$result['itemName']}，获得 {$result['income']} 元",
                'itemName' => $result['itemName'],
                'quantity' => $result['quantity'],
                'sellPrice' => $result['sellPrice'],
                'income' => $result['income'],
                'balance' => $db->getBalance(),
                'state' => $state,
                'inventory' => $db->getInventory(),
                'items' => $db->getFleaMarketItems()
            ]);
        } else {
            echo json_encode($result);
        }
        break;

    case 'buy':
        // 购买物品 - 根据类别路由到专用接口
        $itemId = $_POST['itemId'] ?? '';
        if (empty($itemId)) {
            echo json_encode(['success' => false, 'error' => '商品ID不能为空']);
            break;
        }
        
        // 获取商品信息以判断类别
        $item = $db->getShopItem($itemId);
        if (!$item) {
            echo json_encode(['success' => false, 'error' => '商品不存在']);
            break;
        }
        
        // 根据类别路由到专用接口
        $category = $item['category'] ?? 'other';
        switch ($category) {
            case 'clothes':
                // 衣服走衣柜逻辑
                $wardrobe = $db->getWardrobe();
                foreach ($wardrobe as $w) {
                    if ($w['id'] === $itemId) {
                        echo json_encode(['success' => false, 'error' => 'already_owned', 'message' => '已拥有该衣服']);
                        break 2;
                    }
                }
                $balance = $db->getBalance();
                if ($balance < $item['price']) {
                    echo json_encode(['success' => false, 'error' => 'no_money', 'message' => '余额不足']);
                    break;
                }
                $db->spendMoney($item['price'], '购买' . $item['name']);
                $result = $db->buyClothes($itemId, $item['name']);
                if ($result['success']) {
                    logActivity("购买衣服：{$item['name']}");
                }
                $result['balance'] = $db->getBalance();
                $result['wardrobe'] = $db->getWardrobe();
                echo json_encode($result);
                break;
            
            case 'furniture':
                // 家具走专用逻辑
                $result = $db->buyFurniture($itemId);
                $result['balance'] = $db->getBalance();
                $result['furniture'] = $db->getFurniture();
                echo json_encode($result);
                break;
            
            case 'investment':
                // 投资走专用逻辑
                if ($db->hasInvestment($itemId)) {
                    echo json_encode(['success' => false, 'error' => 'already_owned', 'message' => '已拥有该投资']);
                    break;
                }
                $balance = $db->getBalance();
                if ($balance < $item['price']) {
                    echo json_encode(['success' => false, 'error' => 'no_money', 'message' => '余额不足']);
                    break;
                }
                $db->spendMoney($item['price'], '购买投资：' . $item['name']);
                $result = $db->buyInvestment($itemId, $item['name']);
                if ($result['success']) {
                    logActivity("购买投资：{$item['name']}");
                }
                $result['balance'] = $db->getBalance();
                $result['investments'] = $db->getInvestments();
                echo json_encode($result);
                break;
            
            default:
                // 食物、饮料、玩具等走通用仓库逻辑
                $result = $db->buyItem($itemId);
                $result['balance'] = $db->getBalance();
                $result['inventory'] = $db->getInventory();
                echo json_encode($result);
                break;
        }
        break;

    case 'daemon_status':
        // 检查守护进程状态
        $PID_FILE = $DATA_DIR . '/daemon.pid';
        $isRunning = false;
        $pid = null;

        if (file_exists($PID_FILE)) {
            $pid = trim(file_get_contents($PID_FILE));
            if (!empty($pid) && is_numeric($pid)) {
                // 使用 posix_kill(0) 检查进程是否存在
                if (function_exists('posix_kill')) {
                    $isRunning = @posix_kill(intval($pid), 0);
                } else {
                    $isRunning = file_exists("/proc/$pid");
                }
            }
        }

        // 如果PID文件检查失败，尝试直接查找进程
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
        // 长轮询：等待状态变化
        $lastUpdate = isset($_GET['lastUpdate']) ? intval($_GET['lastUpdate']) : 0;
        $timeout = 5; // 最大等待5秒，减少PHP-FPM阻塞
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            $state = $db->loadCatState();
            $poops = $db->loadPoops();
            
            // 检查是否有变化（时间戳更新或便便数量变化）
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
            
            // 每秒检查一次
            sleep(1);
        }
        
        // 超时，返回当前状态（再做一次最终检查，防止遗漏）
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

    // ==================== 成就系统 ====================
    case 'achievements':
        $achievements = $db->getAchievements();
        echo json_encode(['success' => true, 'achievements' => $achievements]);
        break;

    // ==================== 统计系统 ====================
    case 'statistics':
        $stats = $db->getAllStatistics();
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());
        $db->saveCatState($state);
        // 更新天数
        $db->updateDaysPlayed();
        // 检查全属性满
        $db->checkAllStatsFull($state);
        // 检查满级技能
        $db->checkMaxSkills();
        // 检查成就
        $newAchievements = $db->checkAndUnlockAchievements();
        echo json_encode([
            'success' => true,
            'statistics' => $stats,
            'newAchievements' => $newAchievements
        ]);
        break;

    // ==================== 房间系统 ====================
    case 'rooms':
        $rooms = $db->getRooms();
        $state = $db->loadCatState();
        echo json_encode([
            'success' => true,
            'rooms' => $rooms,
            'currentRoom' => $state['currentRoom'] ?? 'living_room'
        ]);
        break;

    case 'switch_room':
        $roomId = $_POST['roomId'] ?? '';
        $rooms = $db->getRooms();
        $validRooms = array_column($rooms, 'id');
        if (!in_array($roomId, $validRooms)) {
            echo json_encode(['success' => false, 'error' => 'invalid_room', 'message' => '无效的房间']);
            break;
        }
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());
        // 忙碌时不能切换房间
        if ($state['isSleeping'] || $state['isWorking'] || $state['isBathing']) {
            echo json_encode(['success' => false, 'error' => 'busy', 'message' => '猫咪正在忙碌，无法切换房间']);
            break;
        }
        $studyState = $db->getStudyState();
        if ($studyState['isStudying']) {
            echo json_encode(['success' => false, 'error' => 'busy', 'message' => '猫咪正在学习，无法切换房间']);
            break;
        }
        $state['currentRoom'] = $roomId;
        // 不同房间有不同效果
        switch ($roomId) {
            case 'garden':
                $state['happy'] = min(100, $state['happy'] + 5);
                $state['sleep'] = min(100, $state['sleep'] + 3);
                break;
            case 'kitchen':
                $state['hunger'] = min(100, $state['hunger'] + 3);
                break;
            case 'amusement_park':
                $state['happy'] = min(100, $state['happy'] + 4);
                break;
            case 'bedroom':
                $state['sleep'] = min(100, $state['sleep'] + 5);
                break;
            case 'bathroom':
                $state['cleanliness'] = min(100, $state['cleanliness'] + 3);
                break;
        }
        $db->saveCatState($state);
        $db->incrementStat('room_switch_count');
        logActivity("切换到房间: $roomId");
        echo json_encode([
            'success' => true,
            'state' => $state,
            'message' => '切换房间成功'
        ]);
        break;

    // ==================== 随机事件 ====================
    case 'random_event':
        $state = $db->loadCatState();
        $state = simulateCatLife($state, time());
        $event = $db->tryRandomEvent($state);
        if ($event) {
            $db->saveCatState($event['state']);
            // 检查成就
            $newAchievements = $db->checkAndUnlockAchievements();
            echo json_encode([
                'success' => true,
                'event' => [
                    'id' => $event['id'],
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'icon' => $event['icon'],
                    'category' => $event['category'],
                    'effects' => $event['effects']
                ],
                'state' => $event['state'],
                'newAchievements' => $newAchievements
            ]);
        } else {
            echo json_encode(['success' => true, 'event' => null]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

// 关闭数据库
$db->close();
