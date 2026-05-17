<?php
// ============================================================

define('ADMIN_USER',          'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$tJ4x151vX5Wf5lUxhYLpY.G4mzgUsnBgQh7B5mqoF4kmwTw1FZoOy');

// ============================================================
// BASE URL — leave empty if the site is in the root directory
// E.g. if the site is at /swim, enter '/swim'
// ============================================================
define('BASE_URL', '');

// Directory with competition JSON files
define('ZAWODY_DIR', __DIR__ . '/../zawody');

// Maximum JSON file size (5 MB)
define('MAX_JSON_SIZE', 5 * 1024 * 1024);

// CSS version — bump when updating style.css or admin.css
define('CSS_VERSION', '14');

// Directory with athlete profiles
define('ZAWODNICY_DIR', __DIR__ . '/../zawodnicy');

// Live config file (active competition)
define('LIVE_CONFIG_FILE', __DIR__ . '/../live_config.json');

// Delay after start before fetching results (seconds)
define('RESULT_DELAY_SECONDS', 300); // 5 minutes

// File with competition announcements (without start list)
define('ZAPOWIEDZI_FILE', __DIR__ . '/../zapowiedzi.json');
