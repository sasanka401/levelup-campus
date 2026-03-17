<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// ─────────────────────────────────────────────────────────────
// LeaderboardController
// ─────────────────────────────────────────────────────────────
class LeaderboardController {

    // GET /api/leaderboard
    public static function getLeaderboard(): void {
        AuthMiddleware::protect();
        $userId = (int)$GLOBALS['auth_user']['id'];
        $db     = Database::getInstance()->getConnection();

        $stmt = $db->query("
            SELECT id, name, college, branch, current_level, weekly_xp, total_xp, streak, avatar_url
            FROM users
            WHERE is_active = 1 AND role = 'student'
            ORDER BY weekly_xp DESC, total_xp DESC
            LIMIT 20
        ");
        $top = $stmt->fetchAll();

        $leaderboard = array_map(function($u, $index) use ($userId) {
            $u['rank']          = $index + 1;
            $u['isCurrentUser'] = (int)$u['id'] === $userId;
            return $u;
        }, $top, array_keys($top));

        // My rank
        $rankStmt = $db->prepare("
            SELECT COUNT(*) FROM users WHERE weekly_xp > ? AND role = 'student' AND is_active = 1
        ");
        $rankStmt->execute([$GLOBALS['auth_user']['weekly_xp']]);
        $myRank = (int)$rankStmt->fetchColumn() + 1;

        $totalStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1");

        Response::success('OK', [
            'leaderboard'       => $leaderboard,
            'myRank'            => $myRank,
            'myWeeklyXP'        => (int)$GLOBALS['auth_user']['weekly_xp'],
            'totalParticipants' => (int)$totalStmt->fetchColumn()
        ]);
    }
}

// ─────────────────────────────────────────────────────────────
// CommunityController
// ─────────────────────────────────────────────────────────────
class CommunityController {

    // POST /api/community/request/:mentorId
    public static function sendRequest(int $mentorId): void {
        AuthMiddleware::protect();
        $user        = $GLOBALS['auth_user'];
        $requesterId = (int)$user['id'];
        $db          = Database::getInstance()->getConnection();

        if ($mentorId === $requesterId)
            Response::error('You cannot request yourself as mentor.');

        $mStmt = $db->prepare("SELECT id, current_level, name FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
        $mStmt->execute([$mentorId]);
        $mentor = $mStmt->fetch();
        if (!$mentor) Response::notFound('Mentor not found.');

        if ((int)$mentor['current_level'] - (int)$user['current_level'] < 2) {
            Response::error('Mentor must be at least 2 levels ahead of you.');
        }

        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $message = trim($body['message'] ?? '');

        try {
            $db->prepare("
                INSERT INTO connections (requester_id, mentor_id, message) VALUES (?, ?, ?)
            ")->execute([$requesterId, $mentorId, $message]);
        } catch (PDOException $e) {
            Response::error('A request already exists with this mentor.', 409);
        }

        Response::success('Mentorship request sent successfully!', [], 201);
    }

    // PATCH /api/community/request/:connectionId
    public static function respondToRequest(int $connectionId): void {
        AuthMiddleware::protect();
        $userId = (int)$GLOBALS['auth_user']['id'];
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';
        $db     = Database::getInstance()->getConnection();

        if (!in_array($action, ['accept', 'decline']))
            Response::error('Action must be accept or decline.');

        $cStmt = $db->prepare("SELECT * FROM connections WHERE id = ? LIMIT 1");
        $cStmt->execute([$connectionId]);
        $conn = $cStmt->fetch();

        if (!$conn) Response::notFound('Connection request not found.');
        if ((int)$conn['mentor_id'] !== $userId) Response::forbidden('Only the mentor can respond.');
        if ($conn['status'] !== 'pending') Response::error('This request has already been responded to.');

        $newStatus = $action === 'accept' ? 'accepted' : 'declined';
        $db->prepare("UPDATE connections SET status = ? WHERE id = ?")->execute([$newStatus, $connectionId]);

        Response::success($action === 'accept' ? 'Request accepted! 🎉' : 'Request declined.');
    }

    // GET /api/community/my-connections
    public static function getMyConnections(): void {
        AuthMiddleware::protect();
        $userId = (int)$GLOBALS['auth_user']['id'];
        $db     = Database::getInstance()->getConnection();

        // Incoming (I am the mentor)
        $iStmt = $db->prepare("
            SELECT c.*, u.name as requester_name, u.college, u.current_level, u.avatar_url
            FROM connections c JOIN users u ON u.id = c.requester_id
            WHERE c.mentor_id = ? ORDER BY c.created_at DESC
        ");
        $iStmt->execute([$userId]);
        $incoming = $iStmt->fetchAll();

        // Outgoing (I am the requester)
        $oStmt = $db->prepare("
            SELECT c.*, u.name as mentor_name, u.college, u.current_level, u.avatar_url
            FROM connections c JOIN users u ON u.id = c.mentor_id
            WHERE c.requester_id = ? ORDER BY c.created_at DESC
        ");
        $oStmt->execute([$userId]);
        $outgoing = $oStmt->fetchAll();

        Response::success('OK', [
            'incoming'        => $incoming,
            'outgoing'        => $outgoing,
            'acceptedMentors' => array_filter($outgoing, fn($c) => $c['status'] === 'accepted'),
            'acceptedMentees' => array_filter($incoming, fn($c) => $c['status'] === 'accepted'),
        ]);
    }
}

// ─────────────────────────────────────────────────────────────
// AdminController
// ─────────────────────────────────────────────────────────────
class AdminController {

    // GET /api/admin/stats
    public static function getStats(): void {
        AuthMiddleware::protect();
        AuthMiddleware::adminOnly();
        $db = Database::getInstance()->getConnection();

        $totalUsers   = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
        $activeWeek   = $db->query("SELECT COUNT(*) FROM users WHERE role='student' AND last_active_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
        $levelDist    = $db->query("SELECT current_level, COUNT(*) as count FROM users WHERE role='student' GROUP BY current_level ORDER BY current_level")->fetchAll();

        Response::success('OK', ['stats' => [
            'totalUsers'       => (int)$totalUsers,
            'activeThisWeek'   => (int)$activeWeek,
            'levelDistribution'=> $levelDist
        ]]);
    }

    // GET /api/admin/users
    public static function getAllUsers(): void {
        AuthMiddleware::protect();
        AuthMiddleware::adminOnly();
        $db   = Database::getInstance()->getConnection();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit= min(50, (int)($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;

        $stmt = $db->prepare("
            SELECT id, name, email, college, branch, current_level, total_xp, weekly_xp, streak, is_active, created_at, last_active_date
            FROM users WHERE role = 'student'
            ORDER BY created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $users = $stmt->fetchAll();
        $total = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

        Response::success('OK', [
            'users'      => $users,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => (int)$total, 'pages' => ceil($total/$limit)]
        ]);
    }

    // PATCH /api/admin/users/:id/toggle
    public static function toggleUserStatus(int $id): void {
        AuthMiddleware::protect();
        AuthMiddleware::adminOnly();
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT is_active, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user || $user['role'] === 'admin') Response::notFound('User not found.');

        $newStatus = $user['is_active'] ? 0 : 1;
        $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        Response::success('User ' . ($newStatus ? 'activated' : 'deactivated') . '.', ['isActive' => (bool)$newStatus]);
    }

    // POST /api/admin/users/:id/xp
    public static function adjustXP(int $id): void {
        AuthMiddleware::protect();
        AuthMiddleware::adminOnly();
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $amount = (int)($body['amount'] ?? 0);
        if ($amount === 0) Response::error('Valid non-zero amount is required.');

        $db = Database::getInstance()->getConnection();
        $db->prepare("UPDATE users SET total_xp = GREATEST(0, total_xp + ?) WHERE id = ?")->execute([$amount, $id]);

        Response::success(($amount > 0 ? 'Awarded' : 'Deducted') . ' ' . abs($amount) . ' XP.');
    }

    // POST /api/admin/levels
    public static function upsertLevel(): void {
        AuthMiddleware::protect();
        AuthMiddleware::adminOnly();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db   = Database::getInstance()->getConnection();
        $db->prepare("
            INSERT INTO levels (level_number, title, description, xp_required, icon_emoji, color)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description),
            xp_required=VALUES(xp_required), icon_emoji=VALUES(icon_emoji), color=VALUES(color)
        ")->execute([$body['level_number'], $body['title'], $body['description'], $body['xp_required'], $body['icon_emoji'] ?? '🎯', $body['color'] ?? '#2E75B6']);
        Response::success('Level saved.', [], 200);
    }

    // POST /api/admin/tasks
    public static function createTask(): void {
        AuthMiddleware::protect();
        AuthMiddleware::adminOnly();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db   = Database::getInstance()->getConnection();
        $db->prepare("
            INSERT INTO tasks (title, description, level_number, xp_reward, task_type, resource_url, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$body['title'], $body['description'], $body['level_number'], $body['xp_reward'] ?? 50, $body['task_type'] ?? 'other', $body['resource_url'] ?? '', $body['sort_order'] ?? 0]);
        Response::success('Task created.', [], 201);
    }

    // PATCH /api/admin/tasks/:id
    public static function updateTask(int $id): void {
        AuthMiddleware::protect();
        AuthMiddleware::adminOnly();
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $db      = Database::getInstance()->getConnection();
        $allowed = ['title', 'description', 'xp_reward', 'task_type', 'resource_url', 'sort_order', 'is_active'];
        $sets    = []; $values = [];
        foreach ($allowed as $f) {
            if (isset($body[$f])) { $sets[] = "$f = ?"; $values[] = $body[$f]; }
        }
        if (empty($sets)) Response::error('No valid fields provided.');
        $values[] = $id;
        $db->prepare("UPDATE tasks SET " . implode(', ', $sets) . " WHERE id = ?")->execute($values);
        Response::success('Task updated.');
    }
}
