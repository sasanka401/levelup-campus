<?php
// ─────────────────────────────────────────────────────────────
// UserController
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class UserController {

    // GET /api/users/peers
    public static function getPeers(): void {
        AuthMiddleware::protect();
        $userLevel = (int)$GLOBALS['auth_user']['current_level'];
        $userId    = (int)$GLOBALS['auth_user']['id'];
        $db        = Database::getInstance()->getConnection();

        $minLevel = max(1, $userLevel - 1);
        $maxLevel = min(7, $userLevel + 2);

        $stmt = $db->prepare("
            SELECT id, name, college, branch, current_level, total_xp, weekly_xp, streak, avatar_url, bio
            FROM users
            WHERE current_level BETWEEN ? AND ?
              AND id != ?
              AND is_active = 1
              AND role = 'student'
            ORDER BY current_level DESC, total_xp DESC
            LIMIT 50
        ");
        $stmt->execute([$minLevel, $maxLevel, $userId]);
        $peers = $stmt->fetchAll();

        Response::success('OK', ['peers' => $peers, 'userLevel' => $userLevel]);
    }

    // GET /api/users/:id
    public static function getProfile(int $id): void {
        AuthMiddleware::protect();
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, name, email, college, branch, current_level, total_xp,
                   weekly_xp, streak, max_streak, bio, avatar_url, linkedin_url, github_url, created_at
            FROM users WHERE id = ? AND is_active = 1 LIMIT 1
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) Response::notFound('User not found.');

        $bStmt = $db->prepare("
            SELECT b.name, b.icon_emoji, b.rarity, ub.earned_at
            FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = ? ORDER BY ub.earned_at DESC
        ");
        $bStmt->execute([$id]);
        $user['badges'] = $bStmt->fetchAll();

        Response::success('OK', ['user' => $user]);
    }

    // PATCH /api/users/profile
    public static function updateProfile(): void {
        AuthMiddleware::protect();
        $userId  = (int)$GLOBALS['auth_user']['id'];
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $db      = Database::getInstance()->getConnection();
        $allowed = ['name', 'bio', 'avatar_url', 'linkedin_url', 'github_url', 'college', 'branch', 'graduation_year'];
        $sets    = []; $values = [];

        foreach ($allowed as $field) {
            if (isset($body[$field])) {
                $sets[]   = "$field = ?";
                $values[] = is_string($body[$field]) ? trim($body[$field]) : $body[$field];
            }
        }

        if (empty($sets)) Response::error('No valid fields provided.');

        $values[] = $userId;
        $db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?")->execute($values);

        Response::success('Profile updated successfully.');
    }

    // POST /api/users/change-password
    public static function changePassword(): void {
        AuthMiddleware::protect();
        $userId = (int)$GLOBALS['auth_user']['id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $db     = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!password_verify($body['current_password'] ?? '', $row['password_hash'])) {
            Response::error('Current password is incorrect.');
        }
        if (strlen($body['new_password'] ?? '') < 6) Response::error('New password must be at least 6 characters.');

        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($body['new_password'], PASSWORD_BCRYPT, ['cost' => 12]), $userId]);

        Response::success('Password changed successfully.');
    }
}
