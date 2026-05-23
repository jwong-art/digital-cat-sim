// 衣柜功能模块
// 包含衣服数据、猫咪换装 SVG 等

const CLOTHES_DATA = {
    tshirt: {
        name: 'T恤',
        price: 500,
        icon: '👕',
        description: '舒适的休闲T恤'
    },
    pajamas: {
        name: '睡衣',
        price: 800,
        icon: '😺',
        description: '可爱的猫咪睡衣'
    },
    sweater: {
        name: '毛衣',
        price: 1500,
        icon: '🧥',
        description: '温暖的针织毛衣'
    },
    dress: {
        name: '连衣裙',
        price: 3000,
        icon: '👗',
        description: '可爱的粉色连衣裙'
    },
    pirate: {
        name: '海盗装',
        price: 25000,
        icon: '🏴‍☠️',
        description: '霸气海盗装'
    },
    ninja: {
        name: '忍者装',
        price: 30000,
        icon: '🥷',
        description: '神秘的忍者服装'
    },
    superman: {
        name: '超人披风',
        price: 50000,
        icon: '🦸',
        description: '超级英雄披风'
    },
    wizard: {
        name: '巫师袍',
        price: 60000,
        icon: '🧙',
        description: '魔法巫师袍'
    },
    princess: {
        name: '公主裙',
        price: 80000,
        icon: '👸',
        description: '梦幻公主裙'
    },
    suit: {
        name: '西装',
        price: 10000,
        icon: '👔',
        description: '帅气的黑色西装'
    },
    gown: {
        name: '礼服',
        price: 100000,
        icon: '👘',
        description: '华丽的晚礼服'
    },
    astronaut: {
        name: '宇航服',
        price: 150000,
        icon: '👨‍🚀',
        description: '太空宇航服'
    }
};

// 基础猫咪 SVG（无衣服）
function getBaseCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 身体 -->
        <ellipse cx="50" cy="75" rx="30" ry="25" fill="#FF8C42"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛 -->
        <ellipse class="cat-eye" cx="40" cy="42" rx="5" ry="6" fill="#333"/>
        <ellipse class="cat-eye" cx="60" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="42" cy="40" r="2" fill="#fff"/>
        <circle cx="62" cy="40" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path class="cat-mouth" d="M50 54 Q45 58 40 55 M50 54 Q55 58 60 55" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="20" y1="56" x2="35" y2="54" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="56" x2="65" y2="54" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="35" cy="95" rx="8" ry="5" fill="#FFB088"/>
        <ellipse cx="65" cy="95" rx="8" ry="5" fill="#FFB088"/>
        
        <!-- 肚子 -->
        <ellipse cx="50" cy="75" rx="15" ry="12" fill="#FFB088" opacity="0.6"/>
    `;
}

// T恤猫咪 SVG
function getTshirtCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- T恤身体 -->
        <ellipse cx="50" cy="75" rx="32" ry="27" fill="#FFFFFF"/>
        <path d="M18 65 Q50 55 82 65 L82 85 Q50 95 18 85 Z" fill="#FFFFFF"/>
        
        <!-- T恤领口 -->
        <path d="M40 55 Q50 60 60 55" fill="none" stroke="#E0E0E0" stroke-width="2"/>
        
        <!-- T恤图案 - 小鱼 -->
        <path d="M45 72 Q50 68 55 72 Q50 76 45 72" fill="#3498DB"/>
        <circle cx="53" cy="72" r="1" fill="#FFF"/>
        
        <!-- 袖子 -->
        <ellipse cx="22" cy="70" rx="8" ry="6" fill="#FFFFFF"/>
        <ellipse cx="78" cy="70" rx="8" ry="6" fill="#FFFFFF"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛 -->
        <ellipse class="cat-eye" cx="40" cy="42" rx="5" ry="6" fill="#333"/>
        <ellipse class="cat-eye" cx="60" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="42" cy="40" r="2" fill="#fff"/>
        <circle cx="62" cy="40" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path class="cat-mouth" d="M50 54 Q45 58 40 55 M50 54 Q55 58 60 55" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="20" y1="56" x2="35" y2="54" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="56" x2="65" y2="54" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="35" cy="98" rx="7" ry="4" fill="#FFB088"/>
        <ellipse cx="65" cy="98" rx="7" ry="4" fill="#FFB088"/>
    `;
}

// 睡衣猫咪 SVG
function getPajamasCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 睡衣身体 -->
        <ellipse cx="50" cy="75" rx="32" ry="27" fill="#E8D4F0"/>
        <path d="M18 65 Q50 55 82 65 L82 90 Q50 100 18 90 Z" fill="#E8D4F0"/>
        
        <!-- 睡衣条纹 -->
        <path d="M20 72 Q50 68 80 72" fill="none" stroke="#D4A5E8" stroke-width="2"/>
        <path d="M20 80 Q50 76 80 80" fill="none" stroke="#D4A5E8" stroke-width="2"/>
        <path d="M20 88 Q50 84 80 88" fill="none" stroke="#D4A5E8" stroke-width="2"/>
        
        <!-- 睡衣图案 - 月亮星星 -->
        <path d="M45 70 Q48 65 50 70 Q48 75 45 70" fill="#FFD700"/>
        <circle cx="55" cy="72" r="1.5" fill="#FFD700"/>
        <circle cx="58" cy="68" r="1" fill="#FFD700"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 睡帽 -->
        <path d="M25 35 Q50 10 75 35 L70 40 Q50 25 30 40 Z" fill="#E8D4F0"/>
        <circle cx="50" cy="18" r="5" fill="#FFD700"/>
        <path d="M30 38 Q50 28 70 38" fill="none" stroke="#D4A5E8" stroke-width="2"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛（ sleepy ） -->
        <path d="M35 42 Q40 44 45 42" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        <path d="M55 42 Q60 44 65 42" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path class="cat-mouth" d="M48 56 Q50 57 52 56" 
              fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="35" cy="98" rx="7" ry="4" fill="#FFB088"/>
        <ellipse cx="65" cy="98" rx="7" ry="4" fill="#FFB088"/>
    `;
}

// 毛衣猫咪 SVG
function getSweaterCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 毛衣身体 -->
        <ellipse cx="50" cy="75" rx="34" ry="29" fill="#D2691E"/>
        <path d="M16 62 Q50 52 84 62 L84 92 Q50 102 16 92 Z" fill="#D2691E"/>
        
        <!-- 毛衣纹理 -->
        <path d="M20 70 Q50 66 80 70" fill="none" stroke="#8B4513" stroke-width="1.5"/>
        <path d="M20 78 Q50 74 80 78" fill="none" stroke="#8B4513" stroke-width="1.5"/>
        <path d="M20 86 Q50 82 80 86" fill="none" stroke="#8B4513" stroke-width="1.5"/>
        
        <!-- 毛衣领口 -->
        <ellipse cx="50" cy="58" rx="15" ry="8" fill="#8B4513"/>
        <ellipse cx="50" cy="58" rx="10" ry="5" fill="#FFB088"/>
        
        <!-- 袖子 -->
        <ellipse cx="18" cy="68" rx="10" ry="8" fill="#D2691E"/>
        <ellipse cx="82" cy="68" rx="10" ry="8" fill="#D2691E"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛 -->
        <ellipse class="cat-eye" cx="40" cy="42" rx="5" ry="6" fill="#333"/>
        <ellipse class="cat-eye" cx="60" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="42" cy="40" r="2" fill="#fff"/>
        <circle cx="62" cy="40" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path class="cat-mouth" d="M50 54 Q45 58 40 55 M50 54 Q55 58 60 55" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="20" y1="56" x2="35" y2="54" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="56" x2="65" y2="54" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="35" cy="100" rx="7" ry="4" fill="#FFB088"/>
        <ellipse cx="65" cy="100" rx="7" ry="4" fill="#FFB088"/>
    `;
}

// 连衣裙猫咪 SVG
function getDressCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 连衣裙身体 -->
        <path d="M35 75 Q30 95 25 100 L75 100 Q70 95 65 75 Q50 85 35 75" fill="#FFB6C1"/>
        <path d="M35 75 Q50 85 65 75 L60 60 Q50 65 40 60 Z" fill="#FF69B4"/>
        
        <!-- 裙子花边 -->
        <circle cx="30" cy="100" r="5" fill="#FFC0CB"/>
        <circle cx="40" cy="102" r="5" fill="#FFC0CB"/>
        <circle cx="50" cy="103" r="5" fill="#FFC0CB"/>
        <circle cx="60" cy="102" r="5" fill="#FFC0CB"/>
        <circle cx="70" cy="100" r="5" fill="#FFC0CB"/>
        
        <!-- 蝴蝶结 -->
        <path d="M45 65 L40 60 L45 55 L50 60 Z" fill="#FF1493"/>
        <path d="M55 65 L60 60 L55 55 L50 60 Z" fill="#FF1493"/>
        <circle cx="50" cy="60" r="3" fill="#FF1493"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛 -->
        <ellipse class="cat-eye" cx="40" cy="42" rx="5" ry="6" fill="#333"/>
        <ellipse class="cat-eye" cx="60" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="42" cy="40" r="2" fill="#fff"/>
        <circle cx="62" cy="40" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path class="cat-mouth" d="M50 54 Q45 58 40 55 M50 54 Q55 58 60 55" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="20" y1="56" x2="35" y2="54" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="56" x2="65" y2="54" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="30" cy="98" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="70" cy="98" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 海盗装猫咪 SVG
function getPirateCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 海盗衣服 -->
        <path d="M30 70 L25 100 L75 100 L70 70 Q50 80 30 70" fill="#4A4A4A"/>
        <path d="M30 70 L50 85 L70 70 L50 60 Z" fill="#5A5A5A"/>
        
        <!-- 腰带 -->
        <rect x="28" y="85" width="44" height="6" fill="#8B4513"/>
        <circle cx="50" cy="88" r="4" fill="#FFD700"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 海盗帽 -->
        <path d="M20 35 L50 15 L80 35 L75 45 L25 45 Z" fill="#1A1A1A"/>
        <path d="M35 25 L50 20 L65 25" fill="none" stroke="#FFD700" stroke-width="2"/>
        <path d="M45 15 L50 5 L55 15" fill="#1A1A1A"/>
        <circle cx="50" cy="12" r="3" fill="#FFD700"/>
        
        <!-- 眼罩 -->
        <path d="M55 38 L65 38 L65 46 L55 46 Z" fill="#1A1A1A"/>
        <line x1="55" y1="38" x2="65" y2="46" stroke="#333" stroke-width="1"/>
        <line x1="65" y1="38" x2="55" y2="46" stroke="#333" stroke-width="1"/>
        
        <!-- 眼睛（独眼） -->
        <ellipse cx="42" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="43" cy="41" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴（坏笑） -->
        <path class="cat-mouth" d="M45 56 Q50 60 55 56" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="28" cy="98" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="72" cy="98" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 忍者装猫咪 SVG
function getNinjaCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#2C3E50" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 忍者服 -->
        <ellipse cx="50" cy="75" rx="30" ry="25" fill="#2C3E50"/>
        <path d="M25 60 L20 100 L80 100 L75 60 Q50 70 25 60" fill="#2C3E50"/>
        
        <!-- 忍者腰带 -->
        <rect x="25" y="82" width="50" height="8" fill="#C0392B"/>
        <rect x="45" y="80" width="10" height="12" fill="#E74C3C"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 忍者头巾 -->
        <path d="M22 40 Q50 30 78 40 L78 50 Q50 45 22 50 Z" fill="#2C3E50"/>
        <path d="M75 35 L85 25 L80 45 Z" fill="#2C3E50"/>
        
        <!-- 只露眼睛 -->
        <ellipse cx="42" cy="45" rx="4" ry="3" fill="#FFF"/>
        <ellipse cx="58" cy="45" rx="4" ry="3" fill="#FFF"/>
        <circle cx="42" cy="45" r="1.5" fill="#333"/>
        <circle cx="58" cy="45" r="1.5" fill="#333"/>
        
        <!-- 鼻子（半遮） -->
        <path d="M48 52 L52 52 L50 55 Z" fill="#FF6B9D" opacity="0.7"/>
        
        <!-- 飞镖 -->
        <path d="M85 70 L88 65 L91 70 L88 75 Z" fill="#95A5A6"/>
        
        <!-- 爪子 -->
        <ellipse cx="28" cy="98" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="72" cy="98" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 超人披风猫咪 SVG
function getSupermanCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 超人服身体 -->
        <ellipse cx="50" cy="75" rx="30" ry="25" fill="#3498DB"/>
        <path d="M25 60 L20 100 L80 100 L75 60 Q50 70 25 60" fill="#3498DB"/>
        
        <!-- 超人标志 -->
        <path d="M40 68 L60 68 L55 82 L45 82 Z" fill="#FFD700"/>
        <path d="M47 72 L53 72 L50 78 Z" fill="#E74C3C"/>
        
        <!-- 披风 -->
        <path d="M25 65 L15 95 Q50 105 85 95 L75 65" fill="#C0392B"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛（坚定） -->
        <ellipse cx="40" cy="42" rx="4" ry="5" fill="#333"/>
        <ellipse cx="60" cy="42" rx="4" ry="5" fill="#333"/>
        <circle cx="41" cy="41" r="1.5" fill="#fff"/>
        <circle cx="61" cy="41" r="1.5" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴（自信） -->
        <path class="cat-mouth" d="M45 56 Q50 59 55 56" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="28" cy="98" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="72" cy="98" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 巫师袍猫咪 SVG
function getWizardCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 巫师袍 -->
        <path d="M30 70 L20 100 L80 100 L70 70 Q50 80 30 70" fill="#4A235A"/>
        <path d="M30 70 L50 85 L70 70 L50 55 Z" fill="#5B2C6F"/>
        
        <!-- 星星图案 -->
        <circle cx="35" cy="85" r="2" fill="#FFD700"/>
        <circle cx="65" cy="90" r="2" fill="#FFD700"/>
        <circle cx="50" cy="95" r="1.5" fill="#FFD700"/>
        
        <!-- 月亮图案 -->
        <path d="M55 75 Q58 72 58 76 Q56 78 55 75" fill="#FFD700"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 巫师帽 -->
        <path d="M30 35 L50 5 L70 35 L65 40 L35 40 Z" fill="#4A235A"/>
        <path d="M35 40 Q50 45 65 40" fill="none" stroke="#5B2C6F" stroke-width="3"/>
        <path d="M45 15 L50 8 L55 15" fill="none" stroke="#FFD700" stroke-width="1"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛（神秘） -->
        <ellipse cx="40" cy="42" rx="5" ry="6" fill="#9B59B6"/>
        <ellipse cx="60" cy="42" rx="5" ry="6" fill="#9B59B6"/>
        <circle cx="42" cy="41" r="2" fill="#fff"/>
        <circle cx="62" cy="41" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path class="cat-mouth" d="M48 56 Q50 57 52 56" 
              fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        
        <!-- 魔法棒 -->
        <line x1="85" y1="70" x2="90" y2="55" stroke="#8B4513" stroke-width="2"/>
        <circle cx="90" cy="53" r="3" fill="#FFD700"/>
        
        <!-- 爪子 -->
        <ellipse cx="28" cy="98" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="72" cy="98" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 公主裙猫咪 SVG
function getPrincessCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 公主裙 -->
        <path d="M30 75 Q20 100 15 105 L85 105 Q80 100 70 75 Q50 85 30 75" fill="#FADBD8"/>
        <path d="M30 75 Q50 85 70 75 L65 60 Q50 65 35 60 Z" fill="#F5B7B1"/>
        
        <!-- 裙子褶皱 -->
        <path d="M25 90 Q35 95 30 105" fill="none" stroke="#F5B7B1" stroke-width="2"/>
        <path d="M40 92 Q50 97 45 105" fill="none" stroke="#F5B7B1" stroke-width="2"/>
        <path d="M55 92 Q65 97 60 105" fill="none" stroke="#F5B7B1" stroke-width="2"/>
        <path d="M70 90 Q80 95 75 105" fill="none" stroke="#F5B7B1" stroke-width="2"/>
        
        <!-- 蝴蝶结 -->
        <path d="M42 62 L38 58 L42 54 L48 58 Z" fill="#E74C3C"/>
        <path d="M58 62 L62 58 L58 54 L52 58 Z" fill="#E74C3C"/>
        <circle cx="50" cy="58" r="3" fill="#C0392B"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 公主皇冠 -->
        <path d="M35 25 L42 15 L50 20 L58 15 L65 25 Z" fill="#FFD700"/>
        <circle cx="42" cy="18" r="2" fill="#E74C3C"/>
        <circle cx="50" cy="16" r="2" fill="#3498DB"/>
        <circle cx="58" cy="18" r="2" fill="#E74C3C"/>
        <path d="M35 25 Q50 30 65 25" fill="none" stroke="#F1C40F" stroke-width="2"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛（闪亮） -->
        <ellipse cx="40" cy="42" rx="5" ry="6" fill="#333"/>
        <ellipse cx="60" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="42" cy="40" r="2" fill="#fff"/>
        <circle cx="62" cy="40" r="2" fill="#fff"/>
        <circle cx="43" cy="39" r="1" fill="#87CEEB"/>
        <circle cx="63" cy="39" r="1" fill="#87CEEB"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴（甜美） -->
        <path class="cat-mouth" d="M47 56 Q50 58 53 56" 
              fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="25" cy="103" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="75" cy="103" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 西装猫咪 SVG
function getSuitCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 西装身体 -->
        <path d="M30 70 L25 100 L75 100 L70 70 Q50 80 30 70" fill="#2C3E50"/>
        <path d="M30 70 L40 100 L60 100 L70 70 L50 75 Z" fill="#34495E"/>
        
        <!-- 白衬衫 -->
        <path d="M45 70 L50 85 L55 70 L50 65 Z" fill="#FFFFFF"/>
        
        <!-- 领带 -->
        <path d="M48 72 L52 72 L51 85 L49 85 Z" fill="#E74C3C"/>
        <circle cx="50" cy="72" r="3" fill="#C0392B"/>
        
        <!-- 西装扣子 -->
        <circle cx="50" cy="90" r="2" fill="#F1C40F"/>
        <circle cx="50" cy="95" r="2" fill="#F1C40F"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛（更帅气） -->
        <ellipse class="cat-eye" cx="40" cy="42" rx="4" ry="5" fill="#333"/>
        <ellipse class="cat-eye" cx="60" cy="42" rx="4" ry="5" fill="#333"/>
        <circle cx="41" cy="41" r="1.5" fill="#fff"/>
        <circle cx="61" cy="41" r="1.5" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴（自信微笑） -->
        <path class="cat-mouth" d="M45 55 Q50 58 55 55" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="20" y1="56" x2="35" y2="54" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="56" x2="65" y2="54" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="28" cy="98" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="72" cy="98" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 礼服猫咪 SVG
function getGownCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FFD700" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 礼服裙摆 -->
        <path d="M20 100 Q50 110 80 100 L70 70 Q50 75 30 70 Z" fill="#8B008B"/>
        <path d="M25 100 Q50 105 75 100" fill="none" stroke="#FFD700" stroke-width="2"/>
        
        <!-- 礼服上身 -->
        <path d="M30 70 L35 50 L65 50 L70 70 Q50 75 30 70" fill="#4B0082"/>
        
        <!-- 金色装饰 -->
        <path d="M35 50 Q50 55 65 50" fill="none" stroke="#FFD700" stroke-width="3"/>
        <circle cx="50" cy="60" r="5" fill="#FFD700"/>
        <circle cx="50" cy="70" r="4" fill="#FFD700"/>
        <circle cx="50" cy="80" r="3" fill="#FFD700"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 皇冠 -->
        <path d="M35 25 L40 15 L50 20 L60 15 L65 25 Z" fill="#FFD700"/>
        <circle cx="40" cy="18" r="2" fill="#E74C3C"/>
        <circle cx="50" cy="16" r="2" fill="#3498DB"/>
        <circle cx="60" cy="18" r="2" fill="#E74C3C"/>
        
        <!-- 耳朵 -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛（高贵） -->
        <ellipse class="cat-eye" cx="40" cy="42" rx="5" ry="6" fill="#333"/>
        <ellipse class="cat-eye" cx="60" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="42" cy="40" r="2" fill="#fff"/>
        <circle cx="62" cy="40" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴（优雅） -->
        <path class="cat-mouth" d="M48 56 Q50 57 52 56" 
              fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="20" y1="56" x2="35" y2="54" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="56" x2="65" y2="54" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="25" cy="98" rx="6" ry="4" fill="#FFB088"/>
        <ellipse cx="75" cy="98" rx="6" ry="4" fill="#FFB088"/>
    `;
}

// 宇航服猫咪 SVG
function getAstronautCatSVG() {
    return `
        <!-- 尾巴 -->
        <path class="cat-tail" d="M70 80 Q85 70 90 50 Q95 30 85 25 Q75 20 70 35" 
              fill="none" stroke="#FF8C42" stroke-width="8" stroke-linecap="round"/>
        
        <!-- 宇航服身体 -->
        <ellipse cx="50" cy="75" rx="34" ry="29" fill="#ECF0F1"/>
        <path d="M18 62 Q50 52 82 62 L82 95 Q50 105 18 95 Z" fill="#ECF0F1"/>
        
        <!-- 宇航服细节 -->
        <rect x="35" y="70" width="30" height="20" rx="5" fill="#BDC3C7"/>
        <rect x="40" y="75" width="20" height="10" rx="2" fill="#34495E"/>
        
        <!-- 美国国旗 -->
        <rect x="60" y="78" width="8" height="5" fill="#E74C3C"/>
        <rect x="60" y="78" width="3" height="3" fill="#3498DB"/>
        
        <!-- 控制面板 -->
        <circle cx="30" cy="85" r="3" fill="#E74C3C"/>
        <circle cx="35" cy="88" r="2" fill="#2ECC71"/>
        <circle cx="32" cy="92" r="2" fill="#F1C40F"/>
        
        <!-- 头 -->
        <circle cx="50" cy="45" r="28" fill="#FF8C42"/>
        
        <!-- 宇航头盔 -->
        <circle cx="50" cy="45" r="32" fill="none" stroke="#BDC3C7" stroke-width="3"/>
        <path d="M25 45 Q50 20 75 45" fill="none" stroke="#95A5A6" stroke-width="2"/>
        
        <!-- 耳朵（从头盔伸出） -->
        <path d="M25 30 L20 10 L40 25 Z" fill="#FF8C42"/>
        <path d="M75 30 L80 10 L60 25 Z" fill="#FF8C42"/>
        <path d="M28 28 L24 15 L35 26 Z" fill="#FFB088"/>
        <path d="M72 28 L76 15 L65 26 Z" fill="#FFB088"/>
        
        <!-- 眼睛 -->
        <ellipse class="cat-eye" cx="40" cy="42" rx="5" ry="6" fill="#333"/>
        <ellipse class="cat-eye" cx="60" cy="42" rx="5" ry="6" fill="#333"/>
        <circle cx="42" cy="40" r="2" fill="#fff"/>
        <circle cx="62" cy="40" r="2" fill="#fff"/>
        
        <!-- 鼻子 -->
        <path d="M47 50 L53 50 L50 54 Z" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path class="cat-mouth" d="M50 54 Q45 58 40 55 M50 54 Q55 58 60 55" 
              fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="20" y1="48" x2="35" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="18" y1="52" x2="35" y2="52" stroke="#333" stroke-width="1"/>
        <line x1="80" y1="48" x2="65" y2="50" stroke="#333" stroke-width="1"/>
        <line x1="82" y1="52" x2="65" y2="52" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 -->
        <ellipse cx="28" cy="100" rx="7" ry="4" fill="#FFB088"/>
        <ellipse cx="72" cy="100" rx="7" ry="4" fill="#FFB088"/>
    `;
}

// 睡觉的猫咪 SVG（蜷缩在床上）
function getSleepingCatSVG() {
    return `
        <!-- 尾巴 - 蜷缩 -->
        <path class="cat-tail" d="M60 85 Q75 80 80 70 Q85 60 75 55" 
              fill="none" stroke="#FF8C42" stroke-width="10" stroke-linecap="round"/>
        
        <!-- 身体 - 蜷缩成球 -->
        <ellipse cx="50" cy="80" rx="25" ry="20" fill="#FF8C42"/>
        
        <!-- 头 - 侧躺 -->
        <ellipse cx="35" cy="70" rx="22" ry="20" fill="#FF8C42"/>
        
        <!-- 耳朵 -->
        <path d="M18 58 L15 42 L30 55 Z" fill="#FF8C42"/>
        <path d="M48 58 L52 42 L38 55 Z" fill="#FF8C42"/>
        <path d="M20 56 L17 45 L27 54 Z" fill="#FFB088"/>
        <path d="M46 56 L49 45 L39 54 Z" fill="#FFB088"/>
        
        <!-- 眼睛 - 闭着 -->
        <path d="M28 68 Q32 70 36 68" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        <path d="M40 68 Q44 70 48 68" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
        
        <!-- 鼻子 -->
        <circle cx="32" cy="75" r="3" fill="#FF6B9D"/>
        
        <!-- 嘴巴 -->
        <path d="M30 78 Q32 80 34 78" fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>
        
        <!-- 胡须 -->
        <line x1="15" y1="72" x2="25" y2="74" stroke="#333" stroke-width="1"/>
        <line x1="15" y1="76" x2="25" y2="76" stroke="#333" stroke-width="1"/>
        <line x1="15" y1="80" x2="25" y2="78" stroke="#333" stroke-width="1"/>
        
        <!-- 爪子 - 蜷缩 -->
        <ellipse cx="45" cy="95" rx="6" ry="4" fill="#FFB088"/>
        
        <!-- 呼噜气泡 -->
        <circle cx="60" cy="50" r="3" fill="none" stroke="#87CEEB" stroke-width="1.5" opacity="0.6"/>
        <circle cx="68" cy="42" r="5" fill="none" stroke="#87CEEB" stroke-width="1.5" opacity="0.5"/>
        <circle cx="78" cy="32" r="7" fill="none" stroke="#87CEEB" stroke-width="1.5" opacity="0.4"/>
    `;
}

// 获取对应衣服的猫咪 SVG
function getCatSVGByClothes(clothesId) {
    switch(clothesId) {
        case 'tshirt':
            return getTshirtCatSVG();
        case 'pajamas':
            return getPajamasCatSVG();
        case 'sweater':
            return getSweaterCatSVG();
        case 'dress':
            return getDressCatSVG();
        case 'pirate':
            return getPirateCatSVG();
        case 'ninja':
            return getNinjaCatSVG();
        case 'superman':
            return getSupermanCatSVG();
        case 'wizard':
            return getWizardCatSVG();
        case 'princess':
            return getPrincessCatSVG();
        case 'suit':
            return getSuitCatSVG();
        case 'gown':
            return getGownCatSVG();
        case 'astronaut':
            return getAstronautCatSVG();
        default:
            return getBaseCatSVG();
    }
}

// 导出函数
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        CLOTHES_DATA,
        getBaseCatSVG,
        getTshirtCatSVG,
        getPajamasCatSVG,
        getSweaterCatSVG,
        getDressCatSVG,
        getPirateCatSVG,
        getNinjaCatSVG,
        getSupermanCatSVG,
        getWizardCatSVG,
        getPrincessCatSVG,
        getSuitCatSVG,
        getGownCatSVG,
        getAstronautCatSVG,
        getSleepingCatSVG,
        getCatSVGByClothes
    };
}
