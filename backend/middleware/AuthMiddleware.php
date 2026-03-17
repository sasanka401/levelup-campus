<?php
require_once __DIR__ . '/../utils/JwtHelper.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/database.php';

/**
 * AuthMiddleware — call protect() at the top of any protected controller.
 * Sets $GLOBALS['auth_user'] with the current user row from DB.
 */
class AuthMiddleware {

    /**
     * Validates Bearer token and loads current user into $GLOBALS['auth_user'].
     * Exits with 401 if token is missing/invalid.
     */
    public static function protect(): void {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Access denied. No token provided.');
        }

        $token   = substr($authHeader, 7);
        $payload = JwtHelper::decode($token);

        if (!$payload || empty($payload['id'])) {
            Response::unauthorized('Invalid or expired token. Please log in again.');
        }

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, name, email, college, branch, graduation_year,
                   current_level, total_xp, weekly_xp, weekly_xp_reset,
                   streak, max_streak, last_active_date,
                   bio, avatar_url, linkedin_url, github_url,
                   role, is_active, created_at
            FROM users WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$payload['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::unauthorized('User no longer exists.');
        }

        if (!$user['is_active']) {
            Response::forbidden('Your account has been deactivated. Contact admin.');
        }

        $GLOBALS['auth_user'] = $user;
    }

    /**
     * Call after protect() to ensure user is admin.
     */
    public static function adminOnly(): void {
        if (($GLOBALS['auth_user']['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin privileges required.');
        }
    }
}
