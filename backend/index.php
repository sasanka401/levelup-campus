<?php
// ─── Error Display (remove after fixing) ──────────────────────
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * ─── API Entry Point ──────────────────────────────────────────
 * All requests go through index.php via .htaccess rewrite.
 * URL format: /api/{resource}/{action}
 */

// ─── CORS Headers ─────────────────────────────────────────────
require_once __DIR__ . '/config/env.example.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Autoload ─────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/JwtHelper.php';
require_once __DIR__ . '/utils/BadgeEngine.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ProgressController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/OtherControllers.php';

// ─── Parse route ──────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip any known prefix — works on XAMPP and InfinityFree
$path = preg_replace('#^(/gamified-api)?/api#', '', $path);
$path = rtrim($path, '/');
if ($path === '') $path = '/';
$segments = array_values(array_filter(explode('/', $path)));

// ─── Route table ──────────────────────────────────────────────

// ── Health check ──────────────────────────────────────────────
if ($method === 'GET' && $path === '/health') {
    Response::success('Gamified Platform PHP API is running 🚀', [
        'environment' => APP_ENV,
        'timestamp'   => date('c')
    ]);
}

// ── Auth routes ───────────────────────────────────────────────
if ($method === 'POST' && $path === '/auth/register')   AuthController::register();
if ($method === 'POST' && $path === '/auth/login')       AuthController::login();
if ($method === 'GET'  && $path === '/auth/me')          AuthController::me();

// ── Progress routes ───────────────────────────────────────────
if ($method === 'GET'  && $path === '/progress/dashboard') ProgressController::getDashboard();

if ($method === 'GET' && isset($segments[1], $segments[2]) && $segments[0] === 'progress' && $segments[2] === 'tasks') {
    ProgressController::getLevelTasks((int)$segments[1]);
}

if ($method === 'POST' && isset($segments[1], $segments[2]) && $segments[0] === 'progress' && $segments[1] === 'complete-task') {
    ProgressController::completeTask((int)$segments[2]);
}

// ── User routes ───────────────────────────────────────────────
if ($method === 'GET'   && $path === '/users/peers')           UserController::getPeers();
if ($method === 'PATCH' && $path === '/users/profile')         UserController::updateProfile();
if ($method === 'POST'  && $path === '/users/change-password') UserController::changePassword();

if ($method === 'GET' && isset($segments[0], $segments[1]) && $segments[0] === 'users' && is_numeric($segments[1])) {
    UserController::getProfile((int)$segments[1]);
}

// ── Leaderboard ───────────────────────────────────────────────
if ($method === 'GET' && $path === '/leaderboard') LeaderboardController::getLeaderboard();

// ── Community / Mentorship ────────────────────────────────────
if ($method === 'GET' && $path === '/community/my-connections') CommunityController::getMyConnections();

if ($method === 'POST' && isset($segments[0], $segments[1], $segments[2]) && $segments[0] === 'community' && $segments[1] === 'request') {
    CommunityController::sendRequest((int)$segments[2]);
}

if ($method === 'PATCH' && isset($segments[0], $segments[1], $segments[2]) && $segments[0] === 'community' && $segments[1] === 'request') {
    CommunityController::respondToRequest((int)$segments[2]);
}

// ── Admin routes ──────────────────────────────────────────────
if ($method === 'GET'  && $path === '/admin/stats')  AdminController::getStats();
if ($method === 'GET'  && $path === '/admin/users')  AdminController::getAllUsers();
if ($method === 'POST' && $path === '/admin/levels') AdminController::upsertLevel();
if ($method === 'POST' && $path === '/admin/tasks')  AdminController::createTask();

// /admin/users/:id/toggle
if ($method === 'PATCH' && isset($segments[3]) && $segments[0] === 'admin' && $segments[1] === 'users' && $segments[3] === 'toggle') {
    AdminController::toggleUserStatus((int)$segments[2]);
}

// /admin/users/:id/xp
if ($method === 'POST' && isset($segments[3]) && $segments[0] === 'admin' && $segments[1] === 'users' && $segments[3] === 'xp') {
    AdminController::adjustXP((int)$segments[2]);
}

// /admin/tasks/:id
if ($method === 'PATCH' && isset($segments[2]) && $segments[0] === 'admin' && $segments[1] === 'tasks') {
    AdminController::updateTask((int)$segments[2]);
}

// ── Badges ────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/badges') {
    AuthMiddleware::protect();
    $db     = Database::getInstance()->getConnection();
    $badges = $db->query("SELECT * FROM badges WHERE is_active = 1 ORDER BY rarity, name")->fetchAll();
    Response::success('OK', ['badges' => $badges]);
}

// ── Levels ────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/levels') {
    AuthMiddleware::protect();
    $db     = Database::getInstance()->getConnection();
    $levels = $db->query("SELECT * FROM levels WHERE is_active = 1 ORDER BY level_number")->fetchAll();
    Response::success('OK', ['levels' => $levels]);
}

// ── 404 fallback ──────────────────────────────────────────────
Response::notFound("Route $method $path not found.");