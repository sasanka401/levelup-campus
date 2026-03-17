<?php
# ─── Copy this file to .env.php and fill in your values ───────
# Never commit .env.php to GitHub

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'gamified_platform');
define('DB_USER', 'root');          // Change in production
define('DB_PASS', '');              // Change in production

define('JWT_SECRET', 'your_super_secret_jwt_key_change_this'); // Use a long random string
define('JWT_EXPIRY', 604800);       // 7 days in seconds

define('CLIENT_URL', 'http://localhost:3000');
define('APP_ENV', 'development');   // 'production' in prod
