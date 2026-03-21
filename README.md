# Digital Cat Sim

A virtual pet cat game built with HTML5, PHP, and SQLite.

## Overview

Digital Cat Sim is a web-based virtual pet game where you can take care of your cat. Feed it, bathe it, play with it, and watch it grow. The game features various stats (hunger, thirst, sleep, happiness, cleanliness), a shop system, wardrobe, furniture, investments, and skill learning.

## Architecture & Design

**Architecture designed by:** jwong-art

**AI-Assisted Development:** A portion of the code was generated using AI assistance (Kimi K2.5), with human review and extensive bug fixing to ensure quality.

## Requirements

### Server Requirements

- **Web Server:** Apache/Nginx with PHP support
- **PHP Version:** 8.0 or higher
- **PHP Extensions:** sqlite3, pdo_sqlite
- **SQLite:** 3.x (usually bundled with PHP)

### Recommended Setup

For running on a local server:

```bash
# Install PHP and SQLite (Ubuntu/Debian)
sudo apt update
sudo apt install php-fpm php-sqlite3 sqlite3

# Install Nginx and configure
sudo apt install nginx
```

## Installation

### 1. Clone or Download the Project

```bash
git clone https://github.com/jwong-art/digital-cat-sim.git
cd digital-cat-sim
```

### 2. Configure Web Server

**Nginx Configuration:**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/digital-cat-sim;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

**Apache Configuration (via .htaccess):**

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api.php$ api.php [L]
```

### 3. Set Permissions

```bash
# Create data directory and set permissions
mkdir -p data
chmod 755 data
chown -R www-data:www-data data
```

### 4. Access the Game

Open your browser and navigate to:
```
http://your-server-ip:81/
```

## Game Features

### Stats System

- **Hunger:** Decreases over time, replenish with food
- **Thirst:** Decreases over time, replenish with drinks
- **Sleep:** Decreases over time, cat can sleep to restore
- **Happiness:** Decreases over time, play with toys to increase
- **Cleanliness:** Decreases over time, bathe to restore

### Activities

- **Feed:** Purchase and feed food items
- **Drink:** Purchase and give drinks
- **Bathe:** Clean the cat to restore cleanliness
- **Play:** Use toys to increase happiness (some toys are consumable)
- **Work:** Earn money by working (10% chance to get sick)
- **Study:** Learn skills to increase work efficiency bonus

### Shop System

- Food & Drinks (consumables)
- Toys (some consumable, some unlimited)
- Clothes (for wardrobe customization)
- Furniture (provide passive bonuses)
- Investments (generate passive income)

### Economy

- Earn money through working
- Spend money on items and upgrades
- Investments provide passive income every 5 seconds

### Health System

- Cat can get sick from working (10% chance)
- Sick cats have 10x stat decay rate
- Can heal at hospital or wait 2 hours for auto-recovery

## Project Structure

```
digital-cat-sim/
├── index.html      # Main game UI (HTML5 + JavaScript)
├── api.php        # Backend API endpoints
├── daemon.php     # Background daemon for stat decay
├── database.php   # SQLite database management
├── clothes.js    # Clothing/dressing logic
├── data/          # SQLite database and logs
│   └── cat_game.db
└── README.md
```

## Development Notes

### Daemon Process

The game uses a background daemon (`daemon.php`) to simulate stat decay over time. The daemon:

- Runs as a background process
- Auto-starts when API is called
- Updates cat stats every second
- Handles poop generation, sleeping, bathing, and working

### Database

SQLite database stores all game state:
- Cat stats
- Inventory
- Shop items
- Wardrobe
- Furniture
- Investments
- Skills
- Transaction history

### Concurrency

File-based locking prevents database conflicts during concurrent access:
- Lock expiration: 5 seconds
- Prevents data corruption from race conditions

## License

MIT License

## Author

**jwong-art**

GitHub: https://github.com/jwong-art
