<?php
/**
 * 数据库管理类 - SQLite
 */
class CatDatabase {
    private $db;
    private $dbPath;
    private $lockFile;

    public function __construct($dataDir) {
        $this->dbPath = $dataDir . '/cat_game.db';
        $this->lockFile = $dataDir . '/db.lock';
        $this->initDatabase();
    }
    
    // 获取文件锁（用于并发控制，带过期机制）
    private function acquireLock($waitSeconds = 5) {
        $start = time();
        while (file_exists($this->lockFile)) {
            // 检查锁文件是否过期（超过5秒视为过期，防止崩溃残留）
            if (filemtime($this->lockFile) < time() - 5) {
                $this->releaseLock(); // 删除过期锁
                break;
            }
            if (time() - $start >= $waitSeconds) {
                return false; // 等待超时
            }
            usleep(100000); // 等待100ms
        }
        touch($this->lockFile);
        return true;
    }
    
    // 释放文件锁
    private function releaseLock() {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    // 初始化数据库
    private function initDatabase() {
        $this->db = new SQLite3($this->dbPath);
        $this->db->busyTimeout(5000);
        
        // 启用 WAL 模式以支持更好的并发访问
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA synchronous = NORMAL');

        // 创建猫咪状态表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS cat_state (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                hunger REAL DEFAULT 80,
                thirst REAL DEFAULT 80,
                sleep REAL DEFAULT 80,
                happy REAL DEFAULT 80,
                cleanliness REAL DEFAULT 80,
                money INTEGER DEFAULT 0,
                last_update INTEGER DEFAULT 0,
                is_sleeping INTEGER DEFAULT 0,
                is_working INTEGER DEFAULT 0,
                is_bathing INTEGER DEFAULT 0,
                work_start_time INTEGER DEFAULT NULL,
                cat_position_x REAL DEFAULT 50,
                cat_position_y REAL DEFAULT 50,
                total_poops INTEGER DEFAULT 0,
                current_room TEXT DEFAULT "living_room"
            )
        ');

        // 检查并添加 current_room 列（如果不存在）
        $result = $this->db->query("PRAGMA table_info(cat_state)");
        $hasCurrentRoom = false;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'current_room') {
                $hasCurrentRoom = true;
                break;
            }
        }
        if (!$hasCurrentRoom) {
            $this->db->exec('ALTER TABLE cat_state ADD COLUMN current_room TEXT DEFAULT "living_room"');
        }

        // 创建便便表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS poops (
                id TEXT PRIMARY KEY,
                x REAL DEFAULT 0,
                y REAL DEFAULT 0,
                created INTEGER DEFAULT 0
            )
        ');

        // 创建交易记录表（赚钱/花钱记录）
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                amount INTEGER NOT NULL,
                description TEXT,
                created_at INTEGER DEFAULT 0
            )
        ');

        // 创建仓库/道具表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS inventory (
                item_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                quantity INTEGER DEFAULT 0,
                description TEXT
            )
        ');

        // 创建商店商品表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS shop_items (
                item_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                price INTEGER NOT NULL,
                description TEXT,
                icon TEXT DEFAULT "🎁"
            )
        ');

        // 创建家具表（记录已购买的家具）
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS furniture (
                item_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                purchased_at INTEGER DEFAULT 0
            )
        ');

        // 创建投资表（记录已购买的投资项目）
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS investments (
                item_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                purchased_at INTEGER DEFAULT 0,
                last_claim_time INTEGER DEFAULT 0
            )
        ');

        // 创建技能表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS skills (
                skill_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                level INTEGER DEFAULT 0,
                max_level INTEGER DEFAULT 10
            )
        ');

        // 创建打工效率表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS work_efficiency (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                bonus_percent INTEGER DEFAULT 0
            )
        ');

        // 创建学习状态表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS study_state (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                is_studying INTEGER DEFAULT 0,
                study_skill_id TEXT DEFAULT NULL,
                study_start_time INTEGER DEFAULT NULL
            )
        ');

        // 创建健康状态表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS health_state (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                is_sick INTEGER DEFAULT 0,
                sick_start_time INTEGER DEFAULT NULL
            )
        ');

        // 创建衣柜表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS wardrobe (
                item_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                is_equipped INTEGER DEFAULT 0
            )
        ');

        // 创建成就定义表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS achievements (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                icon TEXT DEFAULT "🏆",
                category TEXT DEFAULT "general",
                condition_type TEXT NOT NULL,
                condition_value INTEGER DEFAULT 1
            )
        ');

        // 创建已解锁成就表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS user_achievements (
                achievement_id TEXT PRIMARY KEY,
                unlocked_at INTEGER DEFAULT 0
            )
        ');

        // 创建统计数据表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS statistics (
                key TEXT PRIMARY KEY,
                value INTEGER DEFAULT 0,
                updated_at INTEGER DEFAULT 0
            )
        ');

        // 创建随机事件定义表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS random_events (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                icon TEXT DEFAULT "🎲",
                effects TEXT,
                probability REAL DEFAULT 0.1,
                category TEXT DEFAULT "general",
                min_interval INTEGER DEFAULT 300
            )
        ');

        // 创建事件日志表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS event_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT,
                happened_at INTEGER DEFAULT 0
            )
        ');

        // 创建房间表
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS rooms (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                icon TEXT DEFAULT "🏠",
                description TEXT,
                sort_order INTEGER DEFAULT 0
            )
        ');

        // 初始化商店商品
        $this->initShopItems();

        // 初始化技能、效率、学习状态和健康状态
        $this->initSkills();
        $this->initEfficiency();
        $this->initStudyState();
        $this->initHealthState();

        // 初始化默认数据
        $this->initDefaultData();
        $this->initInventory();

        // 初始化新功能
        $this->initAchievements();
        $this->initStatistics();
        $this->initRandomEvents();
        $this->initRooms();
    }

    // 初始化技能
    private function initSkills() {
        $skills = [
            ['chinese', '语文', 0, 10],
            ['math', '数学', 0, 10],
            ['english', '英语', 0, 10],
            ['physics', '物理', 0, 10],
            ['chemistry', '化学', 0, 10],
        ];

        foreach ($skills as $skill) {
            $stmt = $this->db->prepare('
                INSERT OR IGNORE INTO skills (skill_id, name, level, max_level)
                VALUES (:id, :name, :level, :max)
            ');
            $stmt->bindValue(':id', $skill[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $skill[1], SQLITE3_TEXT);
            $stmt->bindValue(':level', $skill[2], SQLITE3_INTEGER);
            $stmt->bindValue(':max', $skill[3], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // 初始化打工效率
    private function initEfficiency() {
        $this->db->exec('
            INSERT OR IGNORE INTO work_efficiency (id, bonus_percent)
            VALUES (1, 0)
        ');
    }

    // 初始化学习状态
    private function initStudyState() {
        $this->db->exec('
            INSERT OR IGNORE INTO study_state (id, is_studying, study_skill_id, study_start_time)
            VALUES (1, 0, NULL, NULL)
        ');
    }

    // 初始化健康状态
    private function initHealthState() {
        $this->db->exec('
            INSERT OR IGNORE INTO health_state (id, is_sick, sick_start_time)
            VALUES (1, 0, NULL)
        ');
    }

    // 初始化商店商品
    private function initShopItems() {
        $items = [
            // 食物 - 基础食物
            ['cat_food', '猫粮', 2, '普通猫粮，填饱肚子', '🍲', 'food'],
            ['fish_dry', '小鱼干', 10, '美味小鱼干，猫咪最爱', '🐟', 'food'],
            // 食物 - 中级食物
            ['chicken', '烤鸡腿', 25, '香喷喷的烤鸡腿，恢复大量饥饿', '🍗', 'food'],
            ['sushi', '三文鱼寿司', 40, '新鲜三文鱼，贵族猫咪的享受', '🍣', 'food'],
            ['steak', '牛排大餐', 80, '顶级牛排，土豪猫咪专属', '🥩', 'food'],
            // 食物 - 高级食物
            ['lobster', '波士顿龙虾', 200, '海鲜盛宴，全属性大幅提升', '🦞', 'food'],
            ['golden_food', '黄金猫粮', 500, '传说中的黄金猫粮，恢复所有属性', '✨', 'food'],

            // 饮料 - 基础饮料
            ['milk', '牛奶', 2, '新鲜牛奶，补充水分', '🥛', 'drink'],
            ['cola', '可乐', 5, '快乐水，猫咪也爱喝', '🥤', 'drink'],
            ['coconut_water', '高级椰子水', 10, '高级椰子水，恢复活力', '🥥', 'drink'],
            // 饮料 - 中级饮料
            ['orange_juice', '鲜榨橙汁', 20, '维C满满，口渴+快乐双恢复', '🧃', 'drink'],
            ['coffee', '猫屎咖啡', 50, '顶级咖啡，提神醒脑但减少睡眠', '☕', 'drink'],
            ['energy_drink', '能量饮料', 80, '快速补充体力，恢复大量口渴', '⚡', 'drink'],
            // 饮料 - 高级饮料
            ['magic_potion', '魔法药水', 300, '神秘配方，恢复所有数值', '🔮', 'drink'],

            // 玩具 - 基础玩具
            ['cat_stick', '逗猫棒', 50, '猫咪最喜欢的玩具，玩耍时消耗', '🪄', 'toy'],
            // 玩具 - 中级玩具
            ['ball', '毛线球', 100, '经典玩具，可以玩很久', '🧶', 'toy'],
            ['feather', '羽毛棒', 200, '轻盈羽毛，激发捕猎本能', '🪶', 'toy'],
            ['laser', '激光笔', 500, '红色光点，猫咪疯狂追逐', '🔴', 'toy'],
            // 玩具 - 高级玩具
            ['robot_mouse', '电子机器鼠', 1000000, '高科技玩具，可无限次使用', '🐭', 'toy'],
            ['drone', '遥控无人机', 5000, '空中飞行器，猫咪的新宠', '🚁', 'toy'],
            ['vr_headset', 'VR眼镜', 20000, '虚拟现实游戏，极致体验', '🥽', 'toy'],

            // 衣服
            ['dress', '连衣裙', 3000, '可爱的粉色连衣裙', '👗', 'clothes'],
            ['suit', '西装', 10000, '帅气的黑色西装', '👔', 'clothes'],
            ['gown', '礼服', 100000, '华丽的晚礼服', '👘', 'clothes'],
            ['tshirt', 'T恤', 500, '舒适的休闲T恤', '👕', 'clothes'],
            ['sweater', '毛衣', 1500, '温暖的针织毛衣', '🧥', 'clothes'],
            ['pajamas', '睡衣', 800, '可爱的猫咪睡衣', '😺', 'clothes'],
            ['superman', '超人披风', 50000, '超级英雄披风', '🦸', 'clothes'],
            ['ninja', '忍者装', 30000, '神秘的忍者服装', '🥷', 'clothes'],
            ['princess', '公主裙', 80000, '梦幻公主裙', '👸', 'clothes'],
            ['wizard', '巫师袍', 60000, '魔法巫师袍', '🧙', 'clothes'],
            ['astronaut', '宇航服', 150000, '太空宇航服', '👨‍🚀', 'clothes'],
            ['pirate', '海盗装', 25000, '霸气海盗装', '🏴‍☠️', 'clothes'],

            // 家具
            ['sofa', '豪华沙发', 50000, '舒适的沙发，猫咪可以躺在上面休息', '🛋️', 'furniture'],
            ['tv', '大屏幕电视', 100000, '可以播放节目，让猫咪开心', '📺', 'furniture'],
            ['bookshelf', '智能书柜', 80000, '拥有后学习时间减半', '📚', 'furniture'],
            ['computer', '游戏电脑', 200000, '可以玩游戏，快乐值立即满', '💻', 'furniture'],
            ['mystery_device', '神秘设备', 5000000, '传说中的神秘装置，可以恢复所有数值', '🔮', 'furniture'],
            // 投资
            ['game_company', '游戏公司', 1000000, '每5秒获得1元收益', '🎮', 'investment'],
            ['data_company', '数据公司', 10000000, '每5秒获得10元收益', '💾', 'investment'],
            ['ai_company', 'AI公司', 100000000, '每5秒获得100元收益', '🤖', 'investment'],
        ];

        // 检查并添加 category 列（如果不存在）
        $result = $this->db->query("PRAGMA table_info(shop_items)");
        $hasCategory = false;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'category') {
                $hasCategory = true;
                break;
            }
        }
        if (!$hasCategory) {
            $this->db->exec('ALTER TABLE shop_items ADD COLUMN category TEXT DEFAULT "other"');
        }

        foreach ($items as $item) {
            // 使用 INSERT OR REPLACE 避免冲突
            $stmt = $this->db->prepare('
                INSERT OR REPLACE INTO shop_items (item_id, name, price, description, icon, category)
                VALUES (:id, :name, :price, :desc, :icon, :category)
            ');
            $stmt->bindValue(':id', $item[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $item[1], SQLITE3_TEXT);
            $stmt->bindValue(':price', $item[2], SQLITE3_INTEGER);
            $stmt->bindValue(':desc', $item[3], SQLITE3_TEXT);
            $stmt->bindValue(':icon', $item[4], SQLITE3_TEXT);
            $stmt->bindValue(':category', $item[5], SQLITE3_TEXT);
            $stmt->execute();
        }
    }

    // 初始化仓库（确保有默认道具条目）
    private function initInventory() {
        $items = [
            ['cat_stick', '逗猫棒', '猫咪最喜欢的玩具'],
            ['cat_food', '猫粮', '普通猫粮'],
            ['fish_dry', '小鱼干', '美味小鱼干'],
            ['milk', '牛奶', '新鲜牛奶'],
            ['cola', '可乐', '快乐水'],
            ['coconut_water', '高级椰子水', '恢复活力'],
            ['robot_mouse', '电子机器鼠', '高科技玩具，无限次使用'],
        ];

        foreach ($items as $item) {
            $stmt = $this->db->prepare('
                INSERT OR IGNORE INTO inventory (item_id, name, quantity, description)
                VALUES (:id, :name, 0, :desc)
            ');
            $stmt->bindValue(':id', $item[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $item[1], SQLITE3_TEXT);
            $stmt->bindValue(':desc', $item[2], SQLITE3_TEXT);
            $stmt->execute();
        }
    }

    // 初始化默认数据
    private function initDefaultData() {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM cat_state WHERE id = 1');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row['count'] == 0) {
            $this->db->exec('
                INSERT INTO cat_state (id, hunger, thirst, sleep, happy, cleanliness, money, last_update, is_sleeping, is_working, is_bathing, work_start_time, cat_position_x, cat_position_y, total_poops)
                VALUES (1, 80, 80, 80, 80, 80, 0, ' . time() . ', 0, 0, 0, NULL, 50, 50, 0)
            ');
        }
    }

    // 加载猫咪状态
    public function loadCatState() {
        $stmt = $this->db->prepare('SELECT * FROM cat_state WHERE id = 1');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            return $this->getDefaultState();
        }

        return [
            'hunger' => (float)$row['hunger'],
            'thirst' => (float)($row['thirst'] ?? 80),
            'sleep' => (float)$row['sleep'],
            'happy' => (float)$row['happy'],
            'cleanliness' => (float)$row['cleanliness'],
            'money' => (int)$row['money'],
            'lastUpdate' => (int)$row['last_update'],
            'isSleeping' => (bool)$row['is_sleeping'],
            'isWorking' => (bool)$row['is_working'],
            'isBathing' => (bool)$row['is_bathing'],
            'workStartTime' => $row['work_start_time'] ? (int)$row['work_start_time'] : null,
            'catPosition' => [
                'x' => (float)$row['cat_position_x'],
                'y' => (float)$row['cat_position_y']
            ],
            'totalPoops' => (int)$row['total_poops'],
            'currentRoom' => isset($row['current_room']) ? $row['current_room'] : 'living_room'
        ];
    }

    // 保存猫咪状态
    public function saveCatState($state) {
        $stmt = $this->db->prepare('
            UPDATE cat_state SET
                hunger = :hunger,
                thirst = :thirst,
                sleep = :sleep,
                happy = :happy,
                cleanliness = :cleanliness,
                money = :money,
                last_update = :last_update,
                is_sleeping = :is_sleeping,
                is_working = :is_working,
                is_bathing = :is_bathing,
                work_start_time = :work_start_time,
                cat_position_x = :cat_position_x,
                cat_position_y = :cat_position_y,
                total_poops = :total_poops,
                current_room = :current_room
            WHERE id = 1
        ');

        $stmt->bindValue(':hunger', $state['hunger'], SQLITE3_FLOAT);
        $stmt->bindValue(':thirst', $state['thirst'] ?? 80, SQLITE3_FLOAT);
        $stmt->bindValue(':sleep', $state['sleep'], SQLITE3_FLOAT);
        $stmt->bindValue(':happy', $state['happy'], SQLITE3_FLOAT);
        $stmt->bindValue(':cleanliness', $state['cleanliness'], SQLITE3_FLOAT);
        $stmt->bindValue(':money', $state['money'], SQLITE3_INTEGER);
        $stmt->bindValue(':last_update', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':is_sleeping', $state['isSleeping'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':is_working', $state['isWorking'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':is_bathing', $state['isBathing'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':work_start_time', $state['workStartTime'], $state['workStartTime'] ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':cat_position_x', $state['catPosition']['x'], SQLITE3_FLOAT);
        $stmt->bindValue(':cat_position_y', $state['catPosition']['y'], SQLITE3_FLOAT);
        $stmt->bindValue(':total_poops', $state['totalPoops'], SQLITE3_INTEGER);
        $stmt->bindValue(':current_room', $state['currentRoom'] ?? 'living_room', SQLITE3_TEXT);

        $stmt->execute();
    }

    // 加载便便列表
    public function loadPoops() {
        $poops = [];
        $result = $this->db->query('SELECT * FROM poops ORDER BY created DESC');

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $poops[] = [
                'id' => $row['id'],
                'x' => (float)$row['x'],
                'y' => (float)$row['y'],
                'created' => (int)$row['created']
            ];
        }

        return $poops;
    }

    // 添加便便（带锁防止并发问题）
    public function addPoop($poop) {
        if (!$this->acquireLock()) {
            return false; // 获取锁失败
        }
        try {
            // 再次检查便便数量
            $result = $this->db->query('SELECT COUNT(*) as count FROM poops');
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row['count'] >= 5) {
                return false; // 已达上限
            }
            
            $stmt = $this->db->prepare('
                INSERT INTO poops (id, x, y, created)
                VALUES (:id, :x, :y, :created)
            ');
            $stmt->bindValue(':id', $poop['id'], SQLITE3_TEXT);
            $stmt->bindValue(':x', $poop['x'], SQLITE3_FLOAT);
            $stmt->bindValue(':y', $poop['y'], SQLITE3_FLOAT);
            $stmt->bindValue(':created', $poop['created'], SQLITE3_INTEGER);
            $stmt->execute();
            return true;
        } finally {
            $this->releaseLock();
        }
    }

    // 删除便便（带锁防止并发问题）
    public function removePoop($poopId) {
        if (!$this->acquireLock()) {
            return false;
        }
        try {
            $stmt = $this->db->prepare('DELETE FROM poops WHERE id = :id');
            $stmt->bindValue(':id', $poopId, SQLITE3_TEXT);
            $stmt->execute();
            return true;
        } finally {
            $this->releaseLock();
        }
    }

    // 清空便便（带锁防止并发问题）
    public function clearPoops() {
        if (!$this->acquireLock()) {
            return false;
        }
        try {
            $this->db->exec('DELETE FROM poops');
            return true;
        } finally {
            $this->releaseLock();
        }
    }

    // 添加交易记录
    public function addTransaction($type, $amount, $description = '') {
        $stmt = $this->db->prepare('
            INSERT INTO transactions (type, amount, description, created_at)
            VALUES (:type, :amount, :description, :created_at)
        ');

        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);

        $stmt->execute();
    }

    // 获取交易记录
    public function getTransactions($limit = 50) {
        $transactions = [];
        $stmt = $this->db->prepare('
            SELECT * FROM transactions ORDER BY created_at DESC LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'amount' => $row['amount'],
                'description' => $row['description'],
                'createdAt' => $row['created_at']
            ];
        }

        return $transactions;
    }

    // 获取余额
    public function getBalance() {
        $stmt = $this->db->prepare('SELECT money FROM cat_state WHERE id = 1');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? (int)$row['money'] : 0;
    }

    // 赚钱
    public function earnMoney($amount, $description = '') {
        if ($amount <= 0) {
            return false; // 不允许负数或零收入
        }
        $stmt = $this->db->prepare('
            UPDATE cat_state SET money = money + :amount WHERE id = 1
        ');
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->execute();

        $this->addTransaction('earn', $amount, $description);
        return true;
    }

    // 花钱
    public function spendMoney($amount, $description = '') {
        $currentBalance = $this->getBalance();
        if ($currentBalance < $amount) {
            return false; // 余额不足
        }

        $stmt = $this->db->prepare('
            UPDATE cat_state SET money = money - :amount WHERE id = 1
        ');
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->execute();

        $this->addTransaction('spend', $amount, $description);
        return true;
    }

    // 重置游戏
    public function resetGame() {
        $this->db->exec('DELETE FROM poops');
        $this->db->exec('DELETE FROM transactions');
        $this->db->exec('
            UPDATE cat_state SET
                hunger = 80,
                thirst = 80,
                sleep = 80,
                happy = 80,
                cleanliness = 80,
                money = 0,
                last_update = ' . time() . ',
                is_sleeping = 0,
                is_working = 0,
                is_bathing = 0,
                work_start_time = NULL,
                cat_position_x = 50,
                cat_position_y = 50,
                total_poops = 0
            WHERE id = 1
        ');
    }

    // 获取上次拉屎时间（如果没有便便，返回当前时间，表示刚初始化）
    public function getLastPoopTime() {
        // 返回最后便便的时间，如果没有便便则返回0（表示从未拉过）
        $result = $this->db->query('SELECT MAX(created) as last_poop FROM poops');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['last_poop'] ? (int)$row['last_poop'] : 0;
    }

    // 获取所有技能
    public function getSkills() {
        $skills = [];
        $result = $this->db->query('SELECT * FROM skills ORDER BY skill_id');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $skills[] = [
                'id' => $row['skill_id'],
                'name' => $row['name'],
                'level' => (int)$row['level'],
                'maxLevel' => (int)$row['max_level']
            ];
        }
        return $skills;
    }

    // 学习技能（升级1级）
    public function studySkill($skillId) {
        $stmt = $this->db->prepare('
            UPDATE skills SET level = level + 1
            WHERE skill_id = :id AND level < max_level
        ');
        $stmt->bindValue(':id', $skillId, SQLITE3_TEXT);
        $stmt->execute();

        return $this->db->changes() > 0;
    }

    // 获取打工效率加成
    public function getEfficiencyBonus() {
        $result = $this->db->query('SELECT bonus_percent FROM work_efficiency WHERE id = 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? (int)$row['bonus_percent'] : 0;
    }

    // 升级打工效率
    public function upgradeEfficiency() {
        // 检查是否所有技能都满级
        $skills = $this->getSkills();
        $allMaxed = true;
        foreach ($skills as $skill) {
            if ($skill['level'] < $skill['maxLevel']) {
                $allMaxed = false;
                break;
            }
        }

        if (!$allMaxed) {
            return ['success' => false, 'error' => '技能未满级'];
        }

        // 增加效率
        $stmt = $this->db->prepare('
            UPDATE work_efficiency SET bonus_percent = bonus_percent + 10 WHERE id = 1
        ');
        $stmt->execute();

        // 重置所有技能为0
        $this->db->exec('UPDATE skills SET level = 0');

        $newBonus = $this->getEfficiencyBonus();
        return ['success' => true, 'bonus' => $newBonus];
    }

    // 获取学习状态
    public function getStudyState() {
        $result = $this->db->query('SELECT * FROM study_state WHERE id = 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            $this->initStudyState();
            return ['isStudying' => false, 'skillId' => null, 'startTime' => null];
        }
        return [
            'isStudying' => (bool)$row['is_studying'],
            'skillId' => $row['study_skill_id'],
            'startTime' => $row['study_start_time'] ? (int)$row['study_start_time'] : null
        ];
    }

    // 开始学习
    public function startStudy($skillId) {
        $stmt = $this->db->prepare('
            UPDATE study_state SET
                is_studying = 1,
                study_skill_id = :skill_id,
                study_start_time = :start_time
            WHERE id = 1
        ');
        $stmt->bindValue(':skill_id', $skillId, SQLITE3_TEXT);
        $stmt->bindValue(':start_time', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    // 结束学习
    public function endStudy() {
        $this->db->exec('
            UPDATE study_state SET
                is_studying = 0,
                study_skill_id = NULL,
                study_start_time = NULL
            WHERE id = 1
        ');
    }

    // 获取健康状态
    public function getHealthState() {
        $result = $this->db->query('SELECT * FROM health_state WHERE id = 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            $this->initHealthState();
            return ['isSick' => false, 'sickStartTime' => null];
        }
        return [
            'isSick' => (bool)$row['is_sick'],
            'sickStartTime' => $row['sick_start_time'] ? (int)$row['sick_start_time'] : null
        ];
    }

    // 让猫咪生病
    public function makeSick() {
        $stmt = $this->db->prepare('
            UPDATE health_state SET is_sick = 1, sick_start_time = :time WHERE id = 1
        ');
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    // 治愈猫咪
    public function cureSick() {
        $this->db->exec('
            UPDATE health_state SET is_sick = 0, sick_start_time = NULL WHERE id = 1
        ');
    }
    
    // 检查并自动治愈（生病超过2小时自动恢复）
    public function autoCureIfNeeded() {
        $health = $this->getHealthState();
        if ($health['isSick'] && $health['sickStartTime']) {
            $sickDuration = time() - $health['sickStartTime'];
            $twoHours = 2 * 60 * 60; // 2小时 = 7200秒
            if ($sickDuration >= $twoHours) {
                $this->cureSick();
                return true; // 表示刚刚自动治愈
            }
        }
        return false; // 没有自动治愈
    }

    // 获取衣柜
    public function getWardrobe() {
        $items = [];
        $result = $this->db->query('SELECT * FROM wardrobe ORDER BY item_id');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = [
                'id' => $row['item_id'],
                'name' => $row['name'],
                'isEquipped' => (bool)$row['is_equipped']
            ];
        }
        return $items;
    }

    // 购买衣服（添加到衣柜）
    public function buyClothes($itemId, $itemName) {
        // 检查是否已拥有
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM wardrobe WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row['count'] > 0) {
            return ['success' => false, 'error' => 'already_owned'];
        }

        // 添加到衣柜
        $stmt = $this->db->prepare('
            INSERT INTO wardrobe (item_id, name, is_equipped)
            VALUES (:id, :name, 0)
        ');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->bindValue(':name', $itemName, SQLITE3_TEXT);
        $stmt->execute();

        return ['success' => true];
    }

    // 穿上衣服
    public function equipClothes($itemId) {
        // 先取消所有装备的 clothes
        $this->db->exec('UPDATE wardrobe SET is_equipped = 0');

        // 装备指定的衣服
        if ($itemId) {
            $stmt = $this->db->prepare('UPDATE wardrobe SET is_equipped = 1 WHERE item_id = :id');
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $stmt->execute();
        }

        return ['success' => true];
    }

    // 获取当前装备的衣服
    public function getEquippedClothes() {
        $result = $this->db->query('SELECT item_id FROM wardrobe WHERE is_equipped = 1 LIMIT 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? $row['item_id'] : null;
    }

    // 获取商店商品列表
    public function getShopItems() {
        $items = [];
        $result = $this->db->query('SELECT * FROM shop_items ORDER BY price ASC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = [
                'id' => $row['item_id'],
                'name' => $row['name'],
                'price' => (int)$row['price'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'category' => $row['category'] ?? 'other'
            ];
        }
        return $items;
    }

    // 获取仓库物品
    public function getInventory() {
        $items = [];
        $result = $this->db->query('SELECT * FROM inventory ORDER BY item_id');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $items[] = [
                'id' => $row['item_id'],
                'name' => $row['name'],
                'quantity' => (int)$row['quantity'],
                'description' => $row['description']
            ];
        }
        return $items;
    }

    // 获取跳蚤市场可卖物品
    public function getFleaMarketItems() {
        $items = [];
        $result = $this->db->query('
            SELECT
                i.item_id,
                i.name,
                i.quantity,
                i.description,
                s.price,
                s.icon,
                s.category
            FROM inventory i
            INNER JOIN shop_items s ON s.item_id = i.item_id
            WHERE i.quantity > 0
            ORDER BY s.price ASC, i.item_id ASC
        ');

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $price = (int)$row['price'];
            $items[] = [
                'id' => $row['item_id'],
                'name' => $row['name'],
                'quantity' => (int)$row['quantity'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'category' => $row['category'],
                'originalPrice' => $price,
                'sellPrice' => max(1, (int)floor($price / 2))
            ];
        }

        return $items;
    }

    // 获取特定物品数量
    public function getItemQuantity($itemId) {
        $stmt = $this->db->prepare('SELECT quantity FROM inventory WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? (int)$row['quantity'] : 0;
    }

    // 添加物品到仓库
    public function addItem($itemId, $quantity = 1) {
        // 使用 INSERT OR REPLACE 来避免并发冲突
        // 先从 shop_items 获取物品信息
        $stmt = $this->db->prepare('SELECT name, description FROM shop_items WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);
        
        $name = $item ? $item['name'] : $itemId;
        $desc = $item ? $item['description'] : '';
        
        // 尝试更新现有记录
        $stmt = $this->db->prepare('
            UPDATE inventory SET quantity = quantity + :qty WHERE item_id = :id
        ');
        $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->execute();
        
        // 如果没有更新到记录（物品不存在），则插入新记录
        if ($this->db->changes() === 0) {
            $stmt = $this->db->prepare('
                INSERT OR IGNORE INTO inventory (item_id, name, quantity, description)
                VALUES (:id, :name, :qty, :desc)
            ');
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
            $stmt->bindValue(':desc', $desc, SQLITE3_TEXT);
            $stmt->execute();
            
            // 如果插入被忽略（已存在），则再次尝试更新
            if ($this->db->changes() === 0) {
                $stmt = $this->db->prepare('
                    UPDATE inventory SET quantity = quantity + :qty WHERE item_id = :id
                ');
                $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
    }

    // 消耗物品（带锁防止并发问题）
    public function consumeItem($itemId, $quantity = 1) {
        if (!$this->acquireLock()) {
            return false; // 获取锁失败
        }
        try {
            $currentQty = $this->getItemQuantity($itemId);
            if ($currentQty < $quantity) {
                return false; // 数量不足
            }

            $stmt = $this->db->prepare('
                UPDATE inventory SET quantity = quantity - :qty WHERE item_id = :id
            ');
            $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $stmt->execute();
            return true;
        } finally {
            $this->releaseLock();
        }
    }

    // 卖出仓库物品，价格为商店原价的一半
    public function sellInventoryItem($itemId, $quantity = 1) {
        $quantity = max(1, (int)$quantity);

        if (!$this->acquireLock()) {
            return ['success' => false, 'error' => 'lock_failed', 'message' => '系统繁忙，请稍后再试'];
        }

        try {
            $stmt = $this->db->prepare('
                SELECT
                    i.quantity,
                    i.name,
                    s.price
                FROM inventory i
                INNER JOIN shop_items s ON s.item_id = i.item_id
                WHERE i.item_id = :id
            ');
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $result = $stmt->execute();
            $item = $result->fetchArray(SQLITE3_ASSOC);

            if (!$item) {
                return ['success' => false, 'error' => 'item_not_found', 'message' => '仓库里没有这个道具'];
            }

            $currentQty = (int)$item['quantity'];
            if ($currentQty < $quantity) {
                return ['success' => false, 'error' => 'not_enough_items', 'message' => '道具数量不足'];
            }

            $sellPrice = max(1, (int)floor(((int)$item['price']) / 2));
            $income = $sellPrice * $quantity;

            $stmt = $this->db->prepare('
                UPDATE inventory SET quantity = quantity - :qty WHERE item_id = :id
            ');
            $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $stmt->execute();

            $this->earnMoney($income, '跳蚤市场卖出：' . $item['name']);

            return [
                'success' => true,
                'itemName' => $item['name'],
                'quantity' => $quantity,
                'sellPrice' => $sellPrice,
                'income' => $income
            ];
        } finally {
            $this->releaseLock();
        }
    }

    // 获取单个商品信息
    public function getShopItem($itemId) {
        $stmt = $this->db->prepare('SELECT * FROM shop_items WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($item) {
            return [
                'id' => $item['item_id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'description' => $item['description'],
                'icon' => $item['icon'],
                'category' => $item['category']
            ];
        }
        return null;
    }

    // 购买物品
    public function buyItem($itemId) {
        // 获取商品价格
        $stmt = $this->db->prepare('SELECT price, name FROM shop_items WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);

        if (!$item) {
            return ['success' => false, 'error' => '商品不存在'];
        }

        $price = (int)$item['price'];
        $balance = $this->getBalance();

        if ($balance < $price) {
            return ['success' => false, 'error' => '余额不足'];
        }

        // 扣钱
        $this->spendMoney($price, '购买' . $item['name']);

        // 添加物品
        $this->addItem($itemId, 1);

        return ['success' => true, 'message' => '购买成功'];
    }

    // 购买家具
    public function buyFurniture($itemId) {
        // 检查是否已购买
        if ($this->hasFurniture($itemId)) {
            return ['success' => false, 'error' => '已拥有'];
        }

        // 获取家具信息
        $stmt = $this->db->prepare('SELECT price, name FROM shop_items WHERE item_id = :id AND category = "furniture"');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);

        if (!$item) {
            return ['success' => false, 'error' => '家具不存在'];
        }

        $price = (int)$item['price'];
        $balance = $this->getBalance();

        if ($balance < $price) {
            return ['success' => false, 'error' => '余额不足'];
        }

        // 扣钱
        $this->spendMoney($price, '购买家具：' . $item['name']);

        // 添加到家具表
        $stmt = $this->db->prepare('
            INSERT INTO furniture (item_id, name, purchased_at)
            VALUES (:id, :name, :time)
        ');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->bindValue(':name', $item['name'], SQLITE3_TEXT);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();

        return ['success' => true, 'message' => '购买成功'];
    }

    // 获取已购买的家具
    public function getFurniture() {
        $furniture = [];
        $result = $this->db->query('SELECT * FROM furniture ORDER BY purchased_at DESC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $furniture[] = [
                'id' => $row['item_id'],
                'name' => $row['name'],
                'purchasedAt' => $row['purchased_at']
            ];
        }
        return $furniture;
    }

    // 检查是否拥有某家具
    public function hasFurniture($itemId) {
        $stmt = $this->db->prepare('SELECT 1 FROM furniture WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }

    // 购买投资
    public function buyInvestment($itemId, $itemName) {
        // 检查是否已购买
        if ($this->hasInvestment($itemId)) {
            return ['success' => false, 'error' => '已拥有'];
        }

        // 添加到投资表
        $stmt = $this->db->prepare('
            INSERT INTO investments (item_id, name, purchased_at, last_claim_time)
            VALUES (:id, :name, :time, :time)
        ');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->bindValue(':name', $itemName, SQLITE3_TEXT);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();

        return ['success' => true, 'message' => '购买成功'];
    }

    // 抽奖奖励：不扣钱，按商品类别发放到对应系统
    public function awardShopPrize($item) {
        $itemId = $item['id'];
        $category = $item['category'] ?? 'other';

        switch ($category) {
            case 'clothes':
                $result = $this->buyClothes($itemId, $item['name']);
                return $result['success'] ?? false;

            case 'furniture':
                if ($this->hasFurniture($itemId)) {
                    return false;
                }
                $stmt = $this->db->prepare('
                    INSERT INTO furniture (item_id, name, purchased_at)
                    VALUES (:id, :name, :time)
                ');
                $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
                $stmt->bindValue(':name', $item['name'], SQLITE3_TEXT);
                $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
                $stmt->execute();
                return true;

            case 'investment':
                $result = $this->buyInvestment($itemId, $item['name']);
                return $result['success'] ?? false;

            default:
                $this->addItem($itemId, 1);
                return true;
        }
    }

    // 获取已购买的投资
    public function getInvestments() {
        $investments = [];
        $result = $this->db->query('SELECT * FROM investments ORDER BY purchased_at DESC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $investments[] = [
                'id' => $row['item_id'],
                'name' => $row['name'],
                'purchasedAt' => $row['purchased_at'],
                'lastClaimTime' => $row['last_claim_time']
            ];
        }
        return $investments;
    }

    // 检查是否拥有某投资
    public function hasInvestment($itemId) {
        $stmt = $this->db->prepare('SELECT 1 FROM investments WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }

    // 计算投资收益
    public function calculateInvestmentIncome() {
        $investments = $this->getInvestments();
        $currentTime = time();
        $totalIncome = 0;

        // 投资收益率（每5秒）
        $rates = [
            'game_company' => 1,      // 游戏公司：1元/5秒
            'data_company' => 10,     // 数据公司：10元/5秒
            'ai_company' => 100       // AI公司：100元/5秒
        ];

        foreach ($investments as $inv) {
            $rate = $rates[$inv['id']] ?? 0;
            if ($rate > 0) {
                // 计算从上次领取到现在的时间
                $elapsed = $currentTime - $inv['lastClaimTime'];
                // 每5秒产生收益
                $cycles = floor($elapsed / 5);
                $income = $cycles * $rate;
                $totalIncome += $income;

                // 更新上次领取时间
                if ($cycles > 0) {
                    $newLastClaimTime = $inv['lastClaimTime'] + ($cycles * 5);
                    $stmt = $this->db->prepare('
                        UPDATE investments SET last_claim_time = :time WHERE item_id = :id
                    ');
                    $stmt->bindValue(':time', $newLastClaimTime, SQLITE3_INTEGER);
                    $stmt->bindValue(':id', $inv['id'], SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
        }

        return $totalIncome;
    }

    // ==================== 成就系统 ====================

    // 初始化成就定义
    private function initAchievements() {
        $achievements = [
            // 养育类
            ['first_feed', '初为猫奴', '第一次喂食猫咪', '🍼', 'care', 'feed_count', 1],
            ['feed_10', '小厨师', '累计喂食10次', '🍳', 'care', 'feed_count', 10],
            ['feed_50', '美食家', '累计喂食50次', '👨‍🍳', 'care', 'feed_count', 50],
            ['feed_100', '御膳总管', '累计喂食100次', '🍽️', 'care', 'feed_count', 100],
            ['first_drink', '解渴达人', '第一次给猫咪喝水', '💧', 'care', 'drink_count', 1],
            ['drink_50', '饮品大师', '累计喂水50次', '🥤', 'care', 'drink_count', 50],
            ['first_play', '玩伴', '第一次和猫咪玩耍', '🎮', 'care', 'play_count', 1],
            ['play_50', '最佳玩伴', '累计玩耍50次', '🎾', 'care', 'play_count', 50],
            ['first_bathe', '干净猫咪', '第一次给猫咪洗澡', '🛁', 'care', 'bathe_count', 1],
            ['bathe_30', '洁癖主人', '累计洗澡30次', '✨', 'care', 'bathe_count', 30],
            ['first_sleep', '好梦', '第一次让猫咪睡觉', '💤', 'care', 'sleep_count', 1],
            ['first_clean_poop', '铲屎官', '第一次清理便便', '🧹', 'care', 'clean_poop_count', 1],
            ['clean_poop_50', '资深铲屎官', '累计清理50次便便', '🏅', 'care', 'clean_poop_count', 50],
            // 经济类
            ['first_work', '打工人', '第一次出门打工', '💼', 'money', 'work_count', 1],
            ['work_50', '社畜猫咪', '累计打工50次', '🏢', 'money', 'work_count', 50],
            ['earn_1000', '小康之家', '累计赚取1000元', '💰', 'money', 'total_earned', 1000],
            ['earn_10000', '小富翁', '累计赚取10000元', '💎', 'money', 'total_earned', 10000],
            ['earn_100000', '大富豪', '累计赚取100000元', '👑', 'money', 'total_earned', 100000],
            ['earn_1000000', '百万富翁', '累计赚取1000000元', '🏰', 'money', 'total_earned', 1000000],
            // 学习类
            ['first_study', '好学猫咪', '第一次学习技能', '📖', 'study', 'study_count', 1],
            ['study_20', '学霸猫', '累计学习20次', '🎓', 'study', 'study_count', 20],
            ['max_skill', '满级大师', '任意技能达到10级', '🌟', 'study', 'max_skill_reached', 1],
            ['all_max_skill', '全能天才', '所有技能达到10级', '⭐', 'study', 'all_max_skill', 1],
            // 收集类
            ['buy_clothes', '时尚新手', '购买第一件衣服', '👗', 'collect', 'clothes_count', 1],
            ['buy_furniture', '家居达人', '购买第一件家具', '🛋️', 'collect', 'furniture_count', 1],
            ['buy_investment', '投资人', '购买第一个投资项目', '📈', 'collect', 'investment_count', 1],
            // 特殊类
            ['survive_sick', '坚强猫咪', '生病后恢复健康', '💪', 'special', 'survive_sick', 1],
            ['all_stats_full', '完美状态', '所有属性同时达到100%', '💯', 'special', 'all_stats_full', 1],
            ['days_7', '一周纪念', '养猫满7天', '📅', 'special', 'days_played', 7],
            ['days_30', '一月纪念', '养猫满30天', '🗓️', 'special', 'days_played', 30],
            ['days_100', '百日纪念', '养猫满100天', '🎊', 'special', 'days_played', 100],
        ];

        foreach ($achievements as $a) {
            $stmt = $this->db->prepare('
                INSERT OR IGNORE INTO achievements (id, name, description, icon, category, condition_type, condition_value)
                VALUES (:id, :name, :desc, :icon, :cat, :ctype, :cval)
            ');
            $stmt->bindValue(':id', $a[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $a[1], SQLITE3_TEXT);
            $stmt->bindValue(':desc', $a[2], SQLITE3_TEXT);
            $stmt->bindValue(':icon', $a[3], SQLITE3_TEXT);
            $stmt->bindValue(':cat', $a[4], SQLITE3_TEXT);
            $stmt->bindValue(':ctype', $a[5], SQLITE3_TEXT);
            $stmt->bindValue(':cval', $a[6], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // 获取所有成就定义
    public function getAchievements() {
        $achievements = [];
        $result = $this->db->query('SELECT a.*, ua.unlocked_at FROM achievements a LEFT JOIN user_achievements ua ON a.id = ua.achievement_id');
        // 手动排序
        $order = ['first_feed','feed_10','feed_50','feed_100','first_drink','drink_50','first_play','play_50','first_bathe','bathe_30','first_sleep','first_clean_poop','clean_poop_50','first_work','work_50','earn_1000','earn_10000','earn_100000','earn_1000000','first_study','study_20','max_skill','all_max_skill','buy_clothes','buy_furniture','buy_investment','survive_sick','all_stats_full','days_7','days_30','days_100'];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $achievements[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'category' => $row['category'],
                'conditionType' => $row['condition_type'],
                'conditionValue' => (int)$row['condition_value'],
                'unlocked' => !is_null($row['unlocked_at']),
                'unlockedAt' => $row['unlocked_at'] ? (int)$row['unlocked_at'] : null
            ];
        }
        // 按预定义顺序排序
        usort($achievements, function($a, $b) use ($order) {
            $ia = array_search($a['id'], $order);
            $ib = array_search($b['id'], $order);
            if ($ia === false) $ia = 999;
            if ($ib === false) $ib = 999;
            return $ia - $ib;
        });
        return $achievements;
    }

    // 解锁成就
    public function unlockAchievement($achievementId) {
        // 检查是否已解锁
        $stmt = $this->db->prepare('SELECT 1 FROM user_achievements WHERE achievement_id = :id');
        $stmt->bindValue(':id', $achievementId, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) return false; // 已解锁

        $stmt = $this->db->prepare('
            INSERT INTO user_achievements (achievement_id, unlocked_at)
            VALUES (:id, :time)
        ');
        $stmt->bindValue(':id', $achievementId, SQLITE3_TEXT);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    // 检查并解锁所有符合条件的成就
    public function checkAndUnlockAchievements() {
        $unlocked = [];
        $stats = $this->getAllStatistics();

        $achievements = $this->db->query('SELECT * FROM achievements');
        while ($row = $achievements->fetchArray(SQLITE3_ASSOC)) {
            $ctype = $row['condition_type'];
            $cval = (int)$row['condition_value'];
            $currentVal = isset($stats[$ctype]) ? (int)$stats[$ctype] : 0;

            if ($currentVal >= $cval) {
                if ($this->unlockAchievement($row['id'])) {
                    $unlocked[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'icon' => $row['icon'],
                        'description' => $row['description']
                    ];
                }
            }
        }
        return $unlocked;
    }

    // ==================== 统计系统 ====================

    // 初始化统计数据
    private function initStatistics() {
        $defaults = [
            'feed_count' => 0,
            'drink_count' => 0,
            'play_count' => 0,
            'bathe_count' => 0,
            'sleep_count' => 0,
            'work_count' => 0,
            'study_count' => 0,
            'clean_poop_count' => 0,
            'total_earned' => 0,
            'total_spent' => 0,
            'clothes_count' => 0,
            'furniture_count' => 0,
            'investment_count' => 0,
            'survive_sick' => 0,
            'all_stats_full' => 0,
            'max_skill_reached' => 0,
            'all_max_skill' => 0,
            'days_played' => 1,
            'first_play_time' => time(),
            'random_events_count' => 0,
        ];

        foreach ($defaults as $key => $value) {
            $stmt = $this->db->prepare('
                INSERT OR IGNORE INTO statistics (key, value, updated_at)
                VALUES (:key, :value, :time)
            ');
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_INTEGER);
            $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // 增加统计值
    public function incrementStat($key, $amount = 1) {
        $stmt = $this->db->prepare('
            INSERT INTO statistics (key, value, updated_at)
            VALUES (:key, :value, :time)
            ON CONFLICT(key) DO UPDATE SET value = value + :amount, updated_at = :time
        ');
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', $amount, SQLITE3_INTEGER);
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    // 设置统计值
    public function setStat($key, $value) {
        $stmt = $this->db->prepare('
            INSERT INTO statistics (key, value, updated_at)
            VALUES (:key, :value, :time)
            ON CONFLICT(key) DO UPDATE SET value = :value, updated_at = :time
        ');
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', $value, SQLITE3_INTEGER);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    // 获取单个统计值
    public function getStat($key) {
        $stmt = $this->db->prepare('SELECT value FROM statistics WHERE key = :key');
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? (int)$row['value'] : 0;
    }

    // 获取所有统计
    public function getAllStatistics() {
        $stats = [];
        $result = $this->db->query('SELECT key, value FROM statistics');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $stats[$row['key']] = (int)$row['value'];
        }
        return $stats;
    }

    // 更新天数统计（根据首次游玩时间）
    public function updateDaysPlayed() {
        $firstPlay = $this->getStat('first_play_time');
        if ($firstPlay > 0) {
            $days = max(1, floor((time() - $firstPlay) / 86400) + 1);
            $this->setStat('days_played', $days);
        }
    }

    // 检查全属性满
    public function checkAllStatsFull($state) {
        if ($state['hunger'] >= 100 && $state['thirst'] >= 100 &&
            $state['sleep'] >= 100 && $state['happy'] >= 100 &&
            $state['cleanliness'] >= 100) {
            $this->setStat('all_stats_full', 1);
        }
    }

    // 检查满级技能
    public function checkMaxSkills() {
        $skills = $this->getSkills();
        $anyMax = false;
        $allMax = true;
        foreach ($skills as $skill) {
            if ($skill['level'] >= $skill['maxLevel']) {
                $anyMax = true;
            } else {
                $allMax = false;
            }
        }
        if ($anyMax) $this->setStat('max_skill_reached', 1);
        if ($allMax) $this->setStat('all_max_skill', 1);
    }

    // ==================== 随机事件系统 ====================

    // 初始化随机事件定义
    private function initRandomEvents() {
        $events = [
            // 好事件
            ['event_butterfly', '蝴蝶来访', '一只蝴蝶飞进了房间！猫咪追了半天，虽然没抓到但很开心', '🦋', 'good', json_encode(['happy' => 15, 'hunger' => -5, 'cleanliness' => -5]), 0.08, 600],
            ['event_find_money', '发现零钱', '猫咪在沙发缝里发现了一些零钱！', '💰', 'good', json_encode(['money' => 50]), 0.06, 900],
            ['event_neighbor_cat', '邻居串门', '邻居家的猫来串门了！两只猫玩得很开心', '🐱', 'good', json_encode(['happy' => 20, 'hunger' => -5]), 0.05, 1200],
            ['event_sunbeam', '阳光午后', '一束温暖的阳光照进来，猫咪舒服地晒了会太阳', '☀️', 'good', json_encode(['sleep' => 10, 'happy' => 10]), 0.08, 600],
            ['event_treat', '意外零食', '在角落发现了一包没开封的猫零食！', '🎁', 'good', json_encode(['hunger' => 20, 'happy' => 10]), 0.05, 900],
            ['event_bird_song', '鸟儿歌唱', '窗外的小鸟唱起了歌，猫咪听得入迷', '🐦', 'good', json_encode(['happy' => 12]), 0.07, 600],
            // 中性事件
            ['event_knock', '敲门声', '有人敲门！猫咪警惕地竖起了耳朵', '🚪', 'neutral', json_encode(['happy' => -3]), 0.08, 600],
            ['event_rain', '下雨了', '外面下起了雨，猫咪看着窗外发呆', '🌧️', 'neutral', json_encode(['happy' => -5, 'sleep' => 5]), 0.07, 900],
            ['event_dream', '奇怪的梦', '猫咪做了一个奇怪的梦，醒来后一脸茫然', '💭', 'neutral', json_encode([]), 0.06, 600],
            // 坏事件
            ['event_spill_water', '打翻水杯', '猫咪不小心打翻了水杯，地上湿了一片', '💧', 'bad', json_encode(['thirst' => -10, 'cleanliness' => -10, 'happy' => -5]), 0.06, 900],
            ['event_noise', '突然巨响', '外面传来一声巨响！猫咪被吓了一跳', '💥', 'bad', json_encode(['happy' => -15, 'sleep' => -10]), 0.04, 1200],
            ['event_stomachache', '肚子不舒服', '猫咪好像吃坏了东西，肚子不太舒服', '🤢', 'bad', json_encode(['hunger' => -15, 'happy' => -10]), 0.04, 1200],
            ['event_mess', '弄乱房间', '猫咪把东西碰倒了一地，房间乱糟糟的', '💥', 'bad', json_encode(['cleanliness' => -15, 'happy' => -5]), 0.05, 900],
        ];

        foreach ($events as $e) {
            $stmt = $this->db->prepare('
                INSERT OR IGNORE INTO random_events (id, name, description, icon, category, effects, probability, min_interval)
                VALUES (:id, :name, :desc, :icon, :cat, :effects, :prob, :interval)
            ');
            $stmt->bindValue(':id', $e[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $e[1], SQLITE3_TEXT);
            $stmt->bindValue(':desc', $e[2], SQLITE3_TEXT);
            $stmt->bindValue(':icon', $e[3], SQLITE3_TEXT);
            $stmt->bindValue(':cat', $e[4], SQLITE3_TEXT);
            $stmt->bindValue(':effects', $e[5], SQLITE3_TEXT);
            $stmt->bindValue(':prob', $e[6], SQLITE3_FLOAT);
            $stmt->bindValue(':interval', $e[7], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // 尝试触发随机事件
    public function tryRandomEvent($state) {
        // 猫咪忙碌时不触发
        if ($state['isSleeping'] || $state['isWorking'] || $state['isBathing']) return null;
        $studyState = $this->getStudyState();
        if ($studyState['isStudying']) return null;

        $currentTime = time();

        // 获取所有事件
        $result = $this->db->query('SELECT * FROM random_events');
        $events = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $events[] = $row;
        }

        foreach ($events as $event) {
            // 检查最小间隔
            $stmt = $this->db->prepare('SELECT MAX(happened_at) as last_time FROM event_log WHERE event_id = :id');
            $stmt->bindValue(':id', $event['id'], SQLITE3_TEXT);
            $res = $stmt->execute();
            $row = $res->fetchArray(SQLITE3_ASSOC);
            $lastTime = $row['last_time'] ? (int)$row['last_time'] : 0;

            if ($currentTime - $lastTime < (int)$event['min_interval']) continue;

            // 概率判定
            if (mt_rand() / mt_getrandmax() <= (float)$event['probability']) {
                // 触发事件！
                $effects = json_decode($event['effects'], true);

                // 应用效果
                if ($effects) {
                    foreach ($effects as $key => $value) {
                        if ($key === 'money') {
                            if ($value > 0) {
                                $this->earnMoney($value, $event['name']);
                                $state['money'] = $this->getBalance();
                            } else {
                                $this->spendMoney(abs($value), $event['name']);
                                $state['money'] = $this->getBalance();
                            }
                        } else if (isset($state[$key])) {
                            $state[$key] = max(0, min(100, $state[$key] + $value));
                        }
                    }
                }

                // 记录事件日志
                $stmt = $this->db->prepare('
                    INSERT INTO event_log (event_id, happened_at) VALUES (:id, :time)
                ');
                $stmt->bindValue(':id', $event['id'], SQLITE3_TEXT);
                $stmt->bindValue(':time', $currentTime, SQLITE3_INTEGER);
                $stmt->execute();

                // 更新统计
                $this->incrementStat('random_events_count');

                return [
                    'id' => $event['id'],
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'icon' => $event['icon'],
                    'category' => $event['category'],
                    'effects' => $effects,
                    'state' => $state
                ];
            }
        }

        return null;
    }

    // ==================== 房间系统 ====================

    // 初始化房间
    private function initRooms() {
        $rooms = [
            ['living_room', '客厅', '🏠', '温馨的客厅，猫咪的主要活动区域', 1],
            ['bedroom', '卧室', '🛏️', '舒适的卧室，猫咪可以在这里休息', 2],
            ['bathroom', '浴室', '🛁', '干净的浴室，猫咪可以在这里洗澡', 3],
            ['garden', '花园', '🌿', '阳光花园，猫咪可以在这里晒太阳玩耍', 4],
            ['kitchen', '厨房', '🍳', '厨房，猫咪可以在这里找吃的', 5],
            ['amusement_park', '游乐园', '🎡', '热闹的游乐园，猫咪可以玩抽奖大转盘', 6],
        ];

        foreach ($rooms as $r) {
            $stmt = $this->db->prepare('
                INSERT OR IGNORE INTO rooms (id, name, icon, description, sort_order)
                VALUES (:id, :name, :icon, :desc, :sort)
            ');
            $stmt->bindValue(':id', $r[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $r[1], SQLITE3_TEXT);
            $stmt->bindValue(':icon', $r[2], SQLITE3_TEXT);
            $stmt->bindValue(':desc', $r[3], SQLITE3_TEXT);
            $stmt->bindValue(':sort', $r[4], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // 获取所有房间
    public function getRooms() {
        $rooms = [];
        $result = $this->db->query('SELECT * FROM rooms ORDER BY sort_order');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rooms[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'icon' => $row['icon'],
                'description' => $row['description']
            ];
        }
        return $rooms;
    }

    // 获取默认状态
    private function getDefaultState() {
        return [
            'hunger' => 80,
            'thirst' => 80,
            'sleep' => 80,
            'happy' => 80,
            'cleanliness' => 80,
            'money' => 0,
            'lastUpdate' => time(),
            'isSleeping' => false,
            'isWorking' => false,
            'isBathing' => false,
            'workStartTime' => null,
            'catPosition' => ['x' => 50, 'y' => 50],
            'totalPoops' => 0,
            'currentRoom' => 'living_room'
        ];
    }

    // 关闭数据库
    public function close() {
        $this->db->close();
    }
}
