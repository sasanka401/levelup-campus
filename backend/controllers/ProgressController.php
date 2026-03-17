<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/BadgeEngine.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ProgressController {

    // ─── POST /api/progress/complete-task/:taskId ─────────────
    public static function completeTask(int $taskId): void {
        AuthMiddleware::protect();
        $user   = $GLOBALS['auth_user'];
        $userId = (int) $user['id'];
        $db     = Database::getInstance()->getConnection();

        // Fetch task
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) Response::notFound('Task not found.');

        // Task must belong to an accessible level
        if ($task['level_number'] > (int)$user['current_level']) {
            Response::forbidden('This task belongs to a locked level. Unlock it first!');
        }

        // Check already completed
        $check = $db->prepare("
            SELECT id FROM user_completed_tasks WHERE user_id = ? AND task_id = ? LIMIT 1
        ");
        $check->execute([$userId, $taskId]);
        if ($check->fetch()) {
            Response::error('You have already completed this task.', 409);
        }

        // Insert completion record
        $db->prepare("
            INSERT INTO user_completed_tasks (user_id, task_id, xp_earned) VALUES (?, ?, ?)
        ")->execute([$userId, $taskId, $task['xp_reward']]);

        // Award XP — also handle weekly XP reset
        self::addXP($db, $userId, (int)$task['xp_reward'], $user);

        // Update streak
        self::updateStreakIfNeeded($db, $userId, $user['last_active_date']);

        // Reload user for fresh data
        $freshUser = self::fetchUserRow($db, $userId);

        // ─── Check for Level Up ───────────────────────────────
        $leveledUp    = false;
        $newLevelData = null;

        if ((int)$freshUser['current_level'] < 7) {
            $levelStmt = $db->prepare("SELECT * FROM levels WHERE level_number = ? LIMIT 1");
            $levelStmt->execute([$freshUser['current_level']]);
            $currentLevel = $levelStmt->fetch();

            if ($currentLevel && (int)$freshUser['total_xp'] >= (int)$currentLevel['xp_required']) {
                $newLevelNum = (int)$freshUser['current_level'] + 1;
                $db->prepare("UPDATE users SET current_level = ? WHERE id = ?")->execute([$newLevelNum, $userId]);
                $freshUser['current_level'] = $newLevelNum;
                $leveledUp = true;

                $nlStmt = $db->prepare("SELECT * FROM levels WHERE level_number = ? LIMIT 1");
                $nlStmt->execute([$newLevelNum]);
                $newLevelData = $nlStmt->fetch();
            }
        }

        // ─── Check Badges ─────────────────────────────────────
        $taskCount = self::getTaskCount($db, $userId);
        $newBadges = BadgeEngine::checkAndAward($db, $userId, [
            'totalXP'      => (int)$freshUser['total_xp'],
            'streak'       => (int)$freshUser['streak'],
            'currentLevel' => (int)$freshUser['current_level'],
            'taskCount'    => $taskCount
        ]);

        $msg = $leveledUp
            ? "Task completed! 🎉 You leveled up to Level {$freshUser['current_level']}: {$newLevelData['title']}!"
            : "Task completed! +{$task['xp_reward']} XP earned 🔥";

        Response::success($msg, [
            'xpEarned'           => (int)$task['xp_reward'],
            'totalXP'            => (int)$freshUser['total_xp'],
            'weeklyXP'           => (int)$freshUser['weekly_xp'],
            'currentLevel'       => (int)$freshUser['current_level'],
            'streak'             => (int)$freshUser['streak'],
            'leveledUp'          => $leveledUp,
            'newLevel'           => $newLevelData,
            'newBadges'          => $newBadges,
            'completedTasksCount'=> $taskCount
        ]);
    }

    // ─── GET /api/progress/dashboard ─────────────────────────
    public static function getDashboard(): void {
        AuthMiddleware::protect();
        $userId = (int)$GLOBALS['auth_user']['id'];
        $db     = Database::getInstance()->getConnection();

        $user = self::fetchUserRow($db, $userId);

        // Current level doc
        $lvlStmt = $db->prepare("SELECT * FROM levels WHERE level_number = ? LIMIT 1");
        $lvlStmt->execute([$user['current_level']]);
        $currentLevelDoc = $lvlStmt->fetch();

        // XP progress
        $xpToNextLevel     = $currentLevelDoc ? max(0, (int)$currentLevelDoc['xp_required'] - (int)$user['total_xp']) : 0;
        $xpProgressPercent = ($currentLevelDoc && $currentLevelDoc['xp_required'] > 0)
            ? min(100, round(((int)$user['total_xp'] / (int)$currentLevelDoc['xp_required']) * 100))
            : 100;

        // Leaderboard rank
        $rankStmt = $db->prepare("
            SELECT COUNT(*) FROM users WHERE weekly_xp > ? AND role = 'student' AND is_active = 1
        ");
        $rankStmt->execute([$user['weekly_xp']]);
        $leaderboardRank = (int)$rankStmt->fetchColumn() + 1;

        // Completed tasks count
        $taskCount = self::getTaskCount($db, $userId);

        // Badge count
        $badgeCountStmt = $db->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id = ?");
        $badgeCountStmt->execute([$userId]);
        $badgeCount = (int)$badgeCountStmt->fetchColumn();

        // Recent badges (last 5)
        $badgeStmt = $db->prepare("
            SELECT b.id, b.name, b.icon_emoji, b.rarity, b.description, ub.earned_at
            FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = ?
            ORDER BY ub.earned_at DESC LIMIT 5
        ");
        $badgeStmt->execute([$userId]);
        $recentBadges = $badgeStmt->fetchAll();

        // XP history (last 8 weeks)
        $xpHistStmt = $db->prepare("
            SELECT week, xp FROM xp_history WHERE user_id = ? ORDER BY week DESC LIMIT 8
        ");
        $xpHistStmt->execute([$userId]);
        $xpHistory = array_reverse($xpHistStmt->fetchAll());

        // All levels with status + tasks completed per level
        $allLevels = $db->query("SELECT * FROM levels WHERE is_active = 1 ORDER BY level_number")->fetchAll();
        $completedByLevel = [];
        $clStmt = $db->prepare("
            SELECT t.level_number, COUNT(*) as cnt
            FROM user_completed_tasks uct
            JOIN tasks t ON t.id = uct.task_id
            WHERE uct.user_id = ?
            GROUP BY t.level_number
        ");
        $clStmt->execute([$userId]);
        foreach ($clStmt->fetchAll() as $row) {
            $completedByLevel[$row['level_number']] = (int)$row['cnt'];
        }

        $levelsWithStatus = array_map(function($lvl) use ($user, $completedByLevel) {
            $num = (int)$lvl['level_number'];
            $lvl['status'] = $num < (int)$user['current_level'] ? 'completed'
                : ($num === (int)$user['current_level'] ? 'active' : 'locked');
            $lvl['tasks_completed_in_level'] = $completedByLevel[$num] ?? 0;
            return $lvl;
        }, $allLevels);

        Response::success('OK', [
            'dashboard' => [
                'user'          => $user,
                'gamification'  => [
                    'currentLevel'       => (int)$user['current_level'],
                    'totalXP'            => (int)$user['total_xp'],
                    'weeklyXP'           => (int)$user['weekly_xp'],
                    'xpToNextLevel'      => $xpToNextLevel,
                    'xpProgressPercent'  => $xpProgressPercent,
                    'streak'             => (int)$user['streak'],
                    'maxStreak'          => (int)$user['max_streak'],
                    'leaderboardRank'    => $leaderboardRank,
                    'badgeCount'         => $badgeCount,
                    'tasksCompleted'     => $taskCount,
                ],
                'recentBadges'  => $recentBadges,
                'xpHistory'     => $xpHistory,
                'levels'        => $levelsWithStatus
            ]
        ]);
    }

    // ─── GET /api/progress/level/:n/tasks ────────────────────
    public static function getLevelTasks(int $levelNumber): void {
        AuthMiddleware::protect();
        $user   = $GLOBALS['auth_user'];
        $userId = (int)$user['id'];
        $db     = Database::getInstance()->getConnection();

        if ($levelNumber > (int)$user['current_level']) {
            Response::forbidden('This level is locked. Keep earning XP!');
        }

        $stmt = $db->prepare("
            SELECT * FROM tasks WHERE level_number = ? AND is_active = 1 ORDER BY sort_order
        ");
        $stmt->execute([$levelNumber]);
        $tasks = $stmt->fetchAll();

        // Get completed task IDs for this user
        $doneStmt = $db->prepare("
            SELECT task_id FROM user_completed_tasks WHERE user_id = ?
        ");
        $doneStmt->execute([$userId]);
        $doneTasks = array_column($doneStmt->fetchAll(), 'task_id');

        $tasksWithStatus = array_map(function($task) use ($doneTasks) {
            $task['is_completed'] = in_array((int)$task['id'], array_map('intval', $doneTasks));
            return $task;
        }, $tasks);

        $completed = count(array_filter($tasksWithStatus, fn($t) => $t['is_completed']));
        $total     = count($tasks);

        Response::success('OK', [
            'levelNumber' => $levelNumber,
            'tasks'       => $tasksWithStatus,
            'progress'    => [
                'completed' => $completed,
                'total'     => $total,
                'percent'   => $total > 0 ? round(($completed / $total) * 100) : 0
            ]
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private static function addXP(PDO $db, int $userId, int $amount, array $user): void {
        // Reset weekly XP if past reset date
        $resetDate = $user['weekly_xp_reset'] ?? null;
        $today     = date('Y-m-d');

        if ($resetDate && $today >= $resetDate) {
            // Archive old weekly XP to history
            $week = self::getWeekLabel(new DateTime($resetDate));
            $db->prepare("
                INSERT INTO xp_history (user_id, week, xp) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE xp = VALUES(xp)
            ")->execute([$userId, $week, $user['weekly_xp']]);

            // Reset weekly XP and set new reset date
            $nextSunday = (new DateTime('next Sunday'))->format('Y-m-d');
            $db->prepare("
                UPDATE users
                SET total_xp = total_xp + ?, weekly_xp = ?, weekly_xp_reset = ?
                WHERE id = ?
            ")->execute([$amount, $amount, $nextSunday, $userId]);
        } else {
            $db->prepare("
                UPDATE users SET total_xp = total_xp + ?, weekly_xp = weekly_xp + ? WHERE id = ?
            ")->execute([$amount, $amount, $userId]);
        }
    }

    private static function updateStreakIfNeeded(PDO $db, int $userId, ?string $lastActiveDate): void {
        $today = date('Y-m-d');
        if ($lastActiveDate === $today) return;

        $streak = 1;
        if ($lastActiveDate) {
            $diff = (int)(new DateTime($today))->diff(new DateTime($lastActiveDate))->days;
            $streak = ($diff === 1) ? self::fetchUserRow($db, $userId)['streak'] + 1 : 1;
        }

        $db->prepare("
            UPDATE users SET streak = ?, max_streak = GREATEST(max_streak, ?), last_active_date = ? WHERE id = ?
        ")->execute([$streak, $streak, $today, $userId]);
    }

    private static function fetchUserRow(PDO $db, int $userId): array {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    private static function getTaskCount(PDO $db, int $userId): int {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_completed_tasks WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    private static function getWeekLabel(DateTime $date): string {
        return $date->format('Y') . '-W' . str_pad($date->format('W'), 2, '0', STR_PAD_LEFT);
    }
}
