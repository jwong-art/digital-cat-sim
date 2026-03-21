<?php
/**
 * Database Management Class - SQLite
 * Digital Cat Sim - A virtual pet game
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
    
    // Acquire file lock (for concurrency control, with expiration mechanism)
    private function acquireLock($waitSeconds = 5) {
        $start = time();
        while (file_exists($this->lockFile)) {
            if (filemtime($this->lockFile) < time() - 5) {
                $this->releaseLock();
                break;
            }
            if (time() - $start >= $waitSeconds) {
                return false;
            }
            usleep(100000);
        }
        touch($this->lockFile);
        return true;
    }
    
    // Release file lock
    private function releaseLock() {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    // Initialize database
    private function initDatabase() {
        $this->db = new SQLite3($this->dbPath);
        $this->db->busyTimeout(5000);
        
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA synchronous = NORMAL');

        $this->db->exec('CREATE TABLE IF NOT EXISTS cat_state (id INTEGER PRIMARY KEY CHECK (id = 1), hunger REAL DEFAULT 80, thirst REAL DEFAULT 80, sleep REAL DEFAULT 80, happy REAL DEFAULT 80, cleanliness REAL DEFAULT 80, money INTEGER DEFAULT 0, last_update INTEGER DEFAULT 0, is_sleeping INTEGER DEFAULT 0, is_working INTEGER DEFAULT 0, is_bathing INTEGER DEFAULT 0, work_start_time INTEGER DEFAULT NULL, cat_position_x REAL DEFAULT 50, cat_position_y REAL DEFAULT 50, total_poops INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS poops (id TEXT PRIMARY KEY, x REAL DEFAULT 0, y REAL DEFAULT 0, created INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT NOT NULL, amount INTEGER NOT NULL, description TEXT, created_at INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS inventory (item_id TEXT PRIMARY KEY, name TEXT NOT NULL, quantity INTEGER DEFAULT 0, description TEXT)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS shop_items (item_id TEXT PRIMARY KEY, name TEXT NOT NULL, price INTEGER NOT NULL, description TEXT, icon TEXT DEFAULT "🎁")');
        $this->db->exec('CREATE TABLE IF NOT EXISTS furniture (item_id TEXT PRIMARY KEY, name TEXT NOT NULL, purchased_at INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS investments (item_id TEXT PRIMARY KEY, name TEXT NOT NULL, purchased_at INTEGER DEFAULT 0, last_claim_time INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS skills (skill_id TEXT PRIMARY KEY, name TEXT NOT NULL, level INTEGER DEFAULT 0, max_level INTEGER DEFAULT 10)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS work_efficiency (id INTEGER PRIMARY KEY CHECK (id = 1), bonus_percent INTEGER DEFAULT 0)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS study_state (id INTEGER PRIMARY KEY CHECK (id = 1), is_studying INTEGER DEFAULT 0, study_skill_id TEXT DEFAULT NULL, study_start_time INTEGER DEFAULT NULL)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS health_state (id INTEGER PRIMARY KEY CHECK (id = 1), is_sick INTEGER DEFAULT 0, sick_start_time INTEGER DEFAULT NULL)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS wardrobe (item_id TEXT PRIMARY KEY, name TEXT NOT NULL, is_equipped INTEGER DEFAULT 0)');

        $this->initShopItems();
        $this->initSkills();
        $this->initEfficiency();
        $this->initStudyState();
        $this->initHealthState();
        $this->initDefaultData();
        $this->initInventory();
    }

    private function initSkills() {
        $skills = [['chinese', 'Chinese', 0, 10], ['math', 'Math', 0, 10], ['english', 'English', 0, 10], ['physics', 'Physics', 0, 10], ['chemistry', 'Chemistry', 0, 10]];
        foreach ($skills as $skill) {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO skills (skill_id, name, level, max_level) VALUES (:id, :name, :level, :max)');
            $stmt->bindValue(':id', $skill[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $skill[1], SQLITE3_TEXT);
            $stmt->bindValue(':level', $skill[2], SQLITE3_INTEGER);
            $stmt->bindValue(':max', $skill[3], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    private function initEfficiency() { $this->db->exec('INSERT OR IGNORE INTO work_efficiency (id, bonus_percent) VALUES (1, 0)'); }
    private function initStudyState() { $this->db->exec('INSERT OR IGNORE INTO study_state (id, is_studying, study_skill_id, study_start_time) VALUES (1, 0, NULL, NULL)'); }
    private function initHealthState() { $this->db->exec('INSERT OR IGNORE INTO health_state (id, is_sick, sick_start_time) VALUES (1, 0, NULL)'); }

    private function initShopItems() {
        $items = [
            ['cat_food', 'Cat Food', 2, 'Ordinary cat food, fills the stomach', '🍲', 'food'],
            ['fish_dry', 'Dried Fish', 10, 'Delicious dried fish, cats love it', '🐟', 'food'],
            ['chicken', 'Roast Chicken Leg', 25, 'Fragrant roast chicken leg, restores lots of hunger', '🍗', 'food'],
            ['sushi', 'Salmon Sushi', 40, 'Fresh salmon, a treat for noble cats', '🍣', 'food'],
            ['steak', 'Steak Dinner', 80, 'Premium steak, exclusive for wealthy cats', '🥩', 'food'],
            ['lobster', 'Boston Lobster', 200, 'Seafood feast, greatly boosts all stats', '🦞', 'food'],
            ['golden_food', 'Golden Cat Food', 500, 'Legendary golden cat food, restores all stats', '✨', 'food'],
            ['milk', 'Milk', 2, 'Fresh milk, replenishes hydration', '🥛', 'drink'],
            ['cola', 'Cola', 5, 'Happy water, cats love it too', '🥤', 'drink'],
            ['coconut_water', 'Premium Coconut Water', 10, 'Premium coconut water, restores energy', '🥥', 'drink'],
            ['orange_juice', 'Fresh Orange Juice', 20, 'Full of Vitamin C, restores thirst and happiness', '🧃', 'drink'],
            ['coffee', 'Cat Poop Coffee', 50, 'Premium coffee, refreshes but reduces sleep', '☕', 'drink'],
            ['energy_drink', 'Energy Drink', 80, 'Quick energy boost, restores lots of thirst', '⚡', 'drink'],
            ['magic_potion', 'Magic Potion', 300, 'Mysterious formula, restores all stats', '🔮', 'drink'],
            ['cat_stick', 'Cat Teaser', 50, 'Cats favorite toy, consumed when playing', '🪄', 'toy'],
            ['ball', 'Yarn Ball', 100, 'Classic toy, can play for a long time', '🧶', 'toy'],
            ['feather', 'Feather Wand', 200, 'Light feathers, triggers hunting instinct', '🪶', 'toy'],
            ['laser', 'Laser Pointer', 500, 'Red dot, cats chase it frantically', '🔴', 'toy'],
            ['robot_mouse', 'Electronic Mouse', 1000000, 'High-tech toy, unlimited uses', '🐭', 'toy'],
            ['drone', 'Remote Drone', 5000, 'Aerial vehicle, cats new favorite', '🚁', 'toy'],
            ['vr_headset', 'VR Headset', 20000, 'Virtual reality game, ultimate experience', '🥽', 'toy'],
            ['dress', 'Dress', 3000, 'Cute pink dress', '👗', 'clothes'],
            ['suit', 'Suit', 10000, 'Handsome black suit', '👔', 'clothes'],
            ['gown', 'Gown', 100000, 'Magnificent evening gown', '👘', 'clothes'],
            ['tshirt', 'T-Shirt', 500, 'Comfortable casual T-shirt', '👕', 'clothes'],
            ['sweater', 'Sweater', 1500, 'Warm knit sweater', '🧥', 'clothes'],
            ['pajamas', 'Pajamas', 800, 'Cute cat pajamas', '😺', 'clothes'],
            ['superman', 'Superman Cape', 50000, 'Superhero cape', '🦸', 'clothes'],
            ['ninja', 'Ninja Outfit', 30000, 'Mysterious ninja costume', '🥷', 'clothes'],
            ['princess', 'Princess Dress', 80000, 'Dreamy princess dress', '👸', 'clothes'],
            ['wizard', 'Wizard Robe', 60000, 'Magic wizard robe', '🧙', 'clothes'],
            ['astronaut', 'Astronaut Suit', 150000, 'Space astronaut suit', '👨‍🚀', 'clothes'],
            ['pirate', 'Pirate Costume', 25000, 'Awesome pirate outfit', '🏴‍☠️', 'clothes'],
            ['sofa', 'Luxury Sofa', 50000, 'Comfortable sofa, cat can rest on it', '🛋️', 'furniture'],
            ['tv', 'Large Screen TV', 100000, 'Can play programs, makes cat happy', '📺', 'furniture'],
            ['bookshelf', 'Smart Bookshelf', 80000, 'Cuts study time in half when owned', '📚', 'furniture'],
            ['computer', 'Gaming Computer', 200000, 'Can play games, happiness instantly maxed', '💻', 'furniture'],
            ['mystery_device', 'Mystery Device', 5000000, 'Legendary device, restores all stats', '🔮', 'furniture'],
            ['game_company', 'Game Company', 1000000, 'Earn 1 coin every 5 seconds', '🎮', 'investment'],
            ['data_company', 'Data Company', 10000000, 'Earn 10 coins every 5 seconds', '💾', 'investment'],
            ['ai_company', 'AI Company', 100000000, 'Earn 100 coins every 5 seconds', '🤖', 'investment'],
        ];
        $result = $this->db->query("PRAGMA table_info(shop_items)");
        $hasCategory = false;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { if ($row['name'] === 'category') { $hasCategory = true; break; } }
        if (!$hasCategory) { $this->db->exec('ALTER TABLE shop_items ADD COLUMN category TEXT DEFAULT "other"'); }
        foreach ($items as $item) {
            $stmt = $this->db->prepare('INSERT OR REPLACE INTO shop_items (item_id, name, price, description, icon, category) VALUES (:id, :name, :price, :desc, :icon, :category)');
            $stmt->bindValue(':id', $item[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $item[1], SQLITE3_TEXT);
            $stmt->bindValue(':price', $item[2], SQLITE3_INTEGER);
            $stmt->bindValue(':desc', $item[3], SQLITE3_TEXT);
            $stmt->bindValue(':icon', $item[4], SQLITE3_TEXT);
            $stmt->bindValue(':category', $item[5], SQLITE3_TEXT);
            $stmt->execute();
        }
    }

    private function initInventory() {
        $items = [['cat_stick', 'Cat Teaser', 'Cats favorite toy'], ['cat_food', 'Cat Food', 'Ordinary cat food'], ['fish_dry', 'Dried Fish', 'Delicious dried fish'], ['milk', 'Milk', 'Fresh milk'], ['cola', 'Cola', 'Happy water'], ['coconut_water', 'Premium Coconut Water', 'Restores energy'], ['robot_mouse', 'Electronic Mouse', 'High-tech toy, unlimited uses']];
        foreach ($items as $item) {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO inventory (item_id, name, quantity, description) VALUES (:id, :name, 0, :desc)');
            $stmt->bindValue(':id', $item[0], SQLITE3_TEXT);
            $stmt->bindValue(':name', $item[1], SQLITE3_TEXT);
            $stmt->bindValue(':desc', $item[2], SQLITE3_TEXT);
            $stmt->execute();
        }
    }

    private function initDefaultData() {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM cat_state WHERE id = 1');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row['count'] == 0) { $this->db->exec('INSERT INTO cat_state (id, hunger, thirst, sleep, happy, cleanliness, money, last_update, is_sleeping, is_working, is_bathing, work_start_time, cat_position_x, cat_position_y, total_poops) VALUES (1, 80, 80, 80, 80, 80, 0, ' . time() . ', 0, 0, 0, NULL, 50, 50, 0)'); }
    }

    public function loadCatState() {
        $stmt = $this->db->prepare('SELECT * FROM cat_state WHERE id = 1');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!$row) return $this->getDefaultState();
        return ['hunger' => (float)$row['hunger'], 'thirst' => (float)($row['thirst'] ?? 80), 'sleep' => (float)$row['sleep'], 'happy' => (float)$row['happy'], 'cleanliness' => (float)$row['cleanliness'], 'money' => (int)$row['money'], 'lastUpdate' => (int)$row['last_update'], 'isSleeping' => (bool)$row['is_sleeping'], 'isWorking' => (bool)$row['is_working'], 'isBathing' => (bool)$row['is_bathing'], 'workStartTime' => $row['work_start_time'] ? (int)$row['work_start_time'] : null, 'catPosition' => ['x' => (float)$row['cat_position_x'], 'y' => (float)$row['cat_position_y']], 'totalPoops' => (int)$row['total_poops']];
    }

    public function saveCatState($state) {
        $stmt = $this->db->prepare('UPDATE cat_state SET hunger=:h, thirst=:t, sleep=:s, happy=:hp, cleanliness=:c, money=:m, last_update=:lu, is_sleeping=:isl, is_working=:iw, is_bathing=:ib, work_start_time=:wst, cat_position_x=:cpx, cat_position_y=:cpy, total_poops=:tp WHERE id=1');
        $stmt->bindValue(':h', $state['hunger'], SQLITE3_FLOAT);
        $stmt->bindValue(':t', $state['thirst'] ?? 80, SQLITE3_FLOAT);
        $stmt->bindValue(':s', $state['sleep'], SQLITE3_FLOAT);
        $stmt->bindValue(':hp', $state['happy'], SQLITE3_FLOAT);
        $stmt->bindValue(':c', $state['cleanliness'], SQLITE3_FLOAT);
        $stmt->bindValue(':m', $state['money'], SQLITE3_INTEGER);
        $stmt->bindValue(':lu', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':isl', $state['isSleeping'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':iw', $state['isWorking'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':ib', $state['isBathing'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':wst', $state['workStartTime'], $state['workStartTime'] ? SQLITE3_INTEGER : SQLITE3_NULL);
        $stmt->bindValue(':cpx', $state['catPosition']['x'], SQLITE3_FLOAT);
        $stmt->bindValue(':cpy', $state['catPosition']['y'], SQLITE3_FLOAT);
        $stmt->bindValue(':tp', $state['totalPoops'], SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function loadPoops() {
        $poops = [];
        $result = $this->db->query('SELECT * FROM poops ORDER BY created DESC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $poops[] = ['id' => $row['id'], 'x' => (float)$row['x'], 'y' => (float)$row['y'], 'created' => (int)$row['created']]; }
        return $poops;
    }

    public function addPoop($poop) {
        if (!$this->acquireLock()) return false;
        try {
            $result = $this->db->query('SELECT COUNT(*) as count FROM poops');
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row['count'] >= 5) return false;
            $stmt = $this->db->prepare('INSERT INTO poops (id, x, y, created) VALUES (:id, :x, :y, :created)');
            $stmt->bindValue(':id', $poop['id'], SQLITE3_TEXT);
            $stmt->bindValue(':x', $poop['x'], SQLITE3_FLOAT);
            $stmt->bindValue(':y', $poop['y'], SQLITE3_FLOAT);
            $stmt->bindValue(':created', $poop['created'], SQLITE3_INTEGER);
            $stmt->execute();
            return true;
        } finally { $this->releaseLock(); }
    }

    public function removePoop($poopId) {
        if (!$this->acquireLock()) return false;
        try {
            $stmt = $this->db->prepare('DELETE FROM poops WHERE id = :id');
            $stmt->bindValue(':id', $poopId, SQLITE3_TEXT);
            $stmt->execute();
            return true;
        } finally { $this->releaseLock(); }
    }

    public function clearPoops() {
        if (!$this->acquireLock()) return false;
        try { $this->db->exec('DELETE FROM poops'); return true; } finally { $this->releaseLock(); }
    }

    public function addTransaction($type, $amount, $description = '') {
        $stmt = $this->db->prepare('INSERT INTO transactions (type, amount, description, created_at) VALUES (:type, :amount, :desc, :created_at)');
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function getTransactions($limit = 50) {
        $transactions = [];
        $stmt = $this->db->prepare('SELECT * FROM transactions ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $transactions[] = ['id' => $row['id'], 'type' => $row['type'], 'amount' => $row['amount'], 'description' => $row['description'], 'createdAt' => $row['created_at']]; }
        return $transactions;
    }

    public function getBalance() {
        $stmt = $this->db->prepare('SELECT money FROM cat_state WHERE id = 1');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? (int)$row['money'] : 0;
    }

    public function earnMoney($amount, $description = '') {
        if ($amount <= 0) return false;
        $stmt = $this->db->prepare('UPDATE cat_state SET money = money + :amount WHERE id = 1');
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->execute();
        $this->addTransaction('earn', $amount, $description);
        return true;
    }

    public function spendMoney($amount, $description = '') {
        if ($this->getBalance() < $amount) return false;
        $stmt = $this->db->prepare('UPDATE cat_state SET money = money - :amount WHERE id = 1');
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->execute();
        $this->addTransaction('spend', $amount, $description);
        return true;
    }

    public function resetGame() {
        $this->db->exec('DELETE FROM poops');
        $this->db->exec('DELETE FROM transactions');
        $this->db->exec('UPDATE cat_state SET hunger=80, thirst=80, sleep=80, happy=80, cleanliness=80, money=0, last_update=' . time() . ', is_sleeping=0, is_working=0, is_bathing=0, work_start_time=NULL, cat_position_x=50, cat_position_y=50, total_poops=0 WHERE id=1');
    }

    public function getLastPoopTime() {
        $result = $this->db->query('SELECT MAX(created) as last_poop FROM poops');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['last_poop'] ? (int)$row['last_poop'] : time();
    }

    public function getSkills() {
        $skills = [];
        $result = $this->db->query('SELECT * FROM skills ORDER BY skill_id');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $skills[] = ['id' => $row['skill_id'], 'name' => $row['name'], 'level' => (int)$row['level'], 'maxLevel' => (int)$row['max_level']]; }
        return $skills;
    }

    public function studySkill($skillId) {
        $stmt = $this->db->prepare('UPDATE skills SET level = level + 1 WHERE skill_id = :id AND level < max_level');
        $stmt->bindValue(':id', $skillId, SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function getEfficiencyBonus() {
        $result = $this->db->query('SELECT bonus_percent FROM work_efficiency WHERE id = 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? (int)$row['bonus_percent'] : 0;
    }

    public function upgradeEfficiency() {
        $skills = $this->getSkills();
        $allMaxed = true;
        foreach ($skills as $skill) if ($skill['level'] < $skill['maxLevel']) { $allMaxed = false; break; }
        if (!$allMaxed) return ['success' => false, 'error' => 'Skills not maxed'];
        $this->db->exec('UPDATE work_efficiency SET bonus_percent = bonus_percent + 10 WHERE id = 1');
        $this->db->exec('UPDATE skills SET level = 0');
        return ['success' => true, 'bonus' => $this->getEfficiencyBonus()];
    }

    public function getStudyState() {
        $result = $this->db->query('SELECT * FROM study_state WHERE id = 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!$row) { $this->initStudyState(); return ['isStudying' => false, 'skillId' => null, 'startTime' => null]; }
        return ['isStudying' => (bool)$row['is_studying'], 'skillId' => $row['study_skill_id'], 'startTime' => $row['study_start_time'] ? (int)$row['study_start_time'] : null];
    }

    public function startStudy($skillId) {
        $stmt = $this->db->prepare('UPDATE study_state SET is_studying=1, study_skill_id=:sid, study_start_time=:sst WHERE id=1');
        $stmt->bindValue(':sid', $skillId, SQLITE3_TEXT);
        $stmt->bindValue(':sst', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function endStudy() { $this->db->exec('UPDATE study_state SET is_studying=0, study_skill_id=NULL, study_start_time=NULL WHERE id=1'); }

    public function getHealthState() {
        $result = $this->db->query('SELECT * FROM health_state WHERE id = 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!$row) { $this->initHealthState(); return ['isSick' => false, 'sickStartTime' => null]; }
        return ['isSick' => (bool)$row['is_sick'], 'sickStartTime' => $row['sick_start_time'] ? (int)$row['sick_start_time'] : null];
    }

    public function makeSick() {
        $stmt = $this->db->prepare('UPDATE health_state SET is_sick=1, sick_start_time=:t WHERE id=1');
        $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function cureSick() { $this->db->exec('UPDATE health_state SET is_sick=0, sick_start_time=NULL WHERE id=1'); }

    public function autoCureIfNeeded() {
        $health = $this->getHealthState();
        if ($health['isSick'] && $health['sickStartTime']) {
            if (time() - $health['sickStartTime'] >= 7200) { $this->cureSick(); return true; }
        }
        return false;
    }

    public function getWardrobe() {
        $items = [];
        $result = $this->db->query('SELECT * FROM wardrobe ORDER BY item_id');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $items[] = ['id' => $row['item_id'], 'name' => $row['name'], 'isEquipped' => (bool)$row['is_equipped']]; }
        return $items;
    }

    public function buyClothes($itemId, $itemName) {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM wardrobe WHERE item_id=:id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row['count'] > 0) return ['success' => false, 'error' => 'already_owned'];
        $stmt = $this->db->prepare('INSERT INTO wardrobe (item_id, name, is_equipped) VALUES (:id, :name, 0)');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->bindValue(':name', $itemName, SQLITE3_TEXT);
        $stmt->execute();
        return ['success' => true];
    }

    public function equipClothes($itemId) {
        $this->db->exec('UPDATE wardrobe SET is_equipped = 0');
        if ($itemId) {
            $stmt = $this->db->prepare('UPDATE wardrobe SET is_equipped = 1 WHERE item_id = :id');
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $stmt->execute();
        }
        return ['success' => true];
    }

    public function getEquippedClothes() {
        $result = $this->db->query('SELECT item_id FROM wardrobe WHERE is_equipped = 1 LIMIT 1');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? $row['item_id'] : null;
    }

    public function getShopItems() {
        $items = [];
        $result = $this->db->query('SELECT * FROM shop_items ORDER BY price ASC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $items[] = ['id' => $row['item_id'], 'name' => $row['name'], 'price' => (int)$row['price'], 'description' => $row['description'], 'icon' => $row['icon'], 'category' => $row['category'] ?? 'other']; }
        return $items;
    }

    public function getInventory() {
        $items = [];
        $result = $this->db->query('SELECT * FROM inventory ORDER BY item_id');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $items[] = ['id' => $row['item_id'], 'name' => $row['name'], 'quantity' => (int)$row['quantity'], 'description' => $row['description']]; }
        return $items;
    }

    public function getItemQuantity($itemId) {
        $stmt = $this->db->prepare('SELECT quantity FROM inventory WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ? (int)$row['quantity'] : 0;
    }

    public function addItem($itemId, $quantity = 1) {
        $stmt = $this->db->prepare('SELECT name, description FROM shop_items WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);
        $name = $item ? $item['name'] : $itemId;
        $desc = $item ? $item['description'] : '';
        $stmt = $this->db->prepare('UPDATE inventory SET quantity = quantity + :qty WHERE item_id = :id');
        $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->execute();
        if ($this->db->changes() === 0) {
            $stmt = $this->db->prepare('INSERT OR IGNORE INTO inventory (item_id, name, quantity, description) VALUES (:id, :name, :qty, :desc)');
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
            $stmt->bindValue(':desc', $desc, SQLITE3_TEXT);
            $stmt->execute();
            if ($this->db->changes() === 0) {
                $stmt = $this->db->prepare('UPDATE inventory SET quantity = quantity + :qty WHERE item_id = :id');
                $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
    }

    public function consumeItem($itemId, $quantity = 1) {
        if (!$this->acquireLock()) return false;
        try {
            if ($this->getItemQuantity($itemId) < $quantity) return false;
            $stmt = $this->db->prepare('UPDATE inventory SET quantity = quantity - :qty WHERE item_id = :id');
            $stmt->bindValue(':qty', $quantity, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
            $stmt->execute();
            return true;
        } finally { $this->releaseLock(); }
    }

    public function getShopItem($itemId) {
        $stmt = $this->db->prepare('SELECT * FROM shop_items WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);
        if ($item) return ['id' => $item['item_id'], 'name' => $item['name'], 'price' => $item['price'], 'description' => $item['description'], 'icon' => $item['icon'], 'category' => $item['category']];
        return null;
    }

    public function buyItem($itemId) {
        $stmt = $this->db->prepare('SELECT price, name FROM shop_items WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);
        if (!$item) return ['success' => false, 'error' => 'Item not found'];
        $price = (int)$item['price'];
        if ($this->getBalance() < $price) return ['success' => false, 'error' => 'Insufficient balance'];
        $this->spendMoney($price, 'Purchase: ' . $item['name']);
        $this->addItem($itemId, 1);
        return ['success' => true, 'message' => 'Purchase successful'];
    }

    public function buyFurniture($itemId) {
        if ($this->hasFurniture($itemId)) return ['success' => false, 'error' => 'Already owned'];
        $stmt = $this->db->prepare('SELECT price, name FROM shop_items WHERE item_id = :id AND category = "furniture"');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $item = $result->fetchArray(SQLITE3_ASSOC);
        if (!$item) return ['success' => false, 'error' => 'Furniture not found'];
        $price = (int)$item['price'];
        if ($this->getBalance() < $price) return ['success' => false, 'error' => 'Insufficient balance'];
        $this->spendMoney($price, 'Purchase furniture: ' . $item['name']);
        $stmt = $this->db->prepare('INSERT INTO furniture (item_id, name, purchased_at) VALUES (:id, :name, :time)');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->bindValue(':name', $item['name'], SQLITE3_TEXT);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
        return ['success' => true, 'message' => 'Purchase successful'];
    }

    public function getFurniture() {
        $furniture = [];
        $result = $this->db->query('SELECT * FROM furniture ORDER BY purchased_at DESC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $furniture[] = ['id' => $row['item_id'], 'name' => $row['name'], 'purchasedAt' => $row['purchased_at']]; }
        return $furniture;
    }

    public function hasFurniture($itemId) {
        $stmt = $this->db->prepare('SELECT 1 FROM furniture WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }

    public function buyInvestment($itemId, $itemName) {
        if ($this->hasInvestment($itemId)) return ['success' => false, 'error' => 'Already owned'];
        $stmt = $this->db->prepare('INSERT INTO investments (item_id, name, purchased_at, last_claim_time) VALUES (:id, :name, :time, :time)');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $stmt->bindValue(':name', $itemName, SQLITE3_TEXT);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
        return ['success' => true, 'message' => 'Purchase successful'];
    }

    public function getInvestments() {
        $investments = [];
        $result = $this->db->query('SELECT * FROM investments ORDER BY purchased_at DESC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $investments[] = ['id' => $row['item_id'], 'name' => $row['name'], 'purchasedAt' => $row['purchased_at'], 'lastClaimTime' => $row['last_claim_time']]; }
        return $investments;
    }

    public function hasInvestment($itemId) {
        $stmt = $this->db->prepare('SELECT 1 FROM investments WHERE item_id = :id');
        $stmt->bindValue(':id', $itemId, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }

    public function calculateInvestmentIncome() {
        $investments = $this->getInvestments();
        $currentTime = time();
        $totalIncome = 0;
        $rates = ['game_company' => 1, 'data_company' => 10, 'ai_company' => 100];
        foreach ($investments as $inv) {
            $rate = $rates[$inv['id']] ?? 0;
            if ($rate > 0) {
                $elapsed = $currentTime - $inv['lastClaimTime'];
                $cycles = floor($elapsed / 5);
                $income = $cycles * $rate;
                $totalIncome += $income;
                if ($cycles > 0) {
                    $newLastClaimTime = $inv['lastClaimTime'] + ($cycles * 5);
                    $stmt = $this->db->prepare('UPDATE investments SET last_claim_time = :time WHERE item_id = :id');
                    $stmt->bindValue(':time', $newLastClaimTime, SQLITE3_INTEGER);
                    $stmt->bindValue(':id', $inv['id'], SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
        }
        return $totalIncome;
    }

    private function getDefaultState() {
        return ['hunger' => 80, 'thirst' => 80, 'sleep' => 80, 'happy' => 80, 'cleanliness' => 80, 'money' => 0, 'lastUpdate' => time(), 'isSleeping' => false, 'isWorking' => false, 'isBathing' => false, 'workStartTime' => null, 'catPosition' => ['x' => 50, 'y' => 50], 'totalPoops' => 0];
    }

    public function close() { $this->db->close(); }
}
