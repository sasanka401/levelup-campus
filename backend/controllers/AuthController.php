<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/JwtHelper.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/BadgeEngine.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController {

    // ─── POST /api/auth/register ──────────────────────────────
    public static function register(): void {
        $body = self::getBody();

        // Validate required fields
        $required = ['name', 'email', 'password', 'college', 'branch', 'graduation_year'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                Response::error("Field '$field' is required.");
            }
        }

        $name           = trim($body['name']);
        $email          = strtolower(trim($body['email']));
        $password       = $body['password'];
        $college        = trim($body['college']);
        $branch         = $body['branch'];
        $graduationYear = (int) $body['graduation_year'];

        // Validate
        if (strlen($name) < 2 || strlen($name) > 50)
            Response::error('Name must be 2–50 characters.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            Response::error('Invalid email address.');
        if (strlen($password) < 6)
            Response::error('Password must be at least 6 characters.');
        $validBranches = ['CSE', 'IT', 'ECE', 'EEE', 'ME', 'CE', 'Other'];
        if (!in_array($branch, $validBranches))
            Response::error('Invalid branch selected.');
        if ($graduationYear < 2020 || $graduationYear > 2030)
            Response::error('Invalid graduation year.');

        $db = Database::getInstance()->getConnection();

        // Check duplicate email
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('An account with this email already exists.', 409);
        }

        $passwordHash    = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $weeklyXpReset   = self::getNextSunday();

        $stmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, college, branch, graduation_year, weekly_xp_reset, last_active_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$name, $email, $passwordHash, $college, $branch, $graduationYear, $weeklyXpReset]);
        $userId = (int) $db->lastInsertId();

        // Update streak (first login = 1)
        $db->prepare("UPDATE users SET streak = 1, max_streak = 1 WHERE id = ?")->execute([$userId]);

        // Check badges
        $newBadges = BadgeEngine::checkAndAward($db, $userId, [
            'totalXP' => 0, 'streak' => 1, 'currentLevel' => 1, 'taskCount' => 0
        ]);

        $user  = self::fetchUser($db, $userId);
        $token = JwtHelper::encode(['id' => $userId]);

        Response::success('Account created successfully! Welcome aboard 🎉', [
            'token'     => $token,
            'user'      => $user,
            'newBadges' => $newBadges
        ], 201);
    }

    // ─── POST /api/auth/login ─────────────────────────────────
    public static function login(): void {
        $body  = self::getBody();
        $email = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';

        if (!$email || !$password)
            Response::error('Email and password are required.');

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid email or password.', 401);
        }

        if (!$user['is_active']) {
            Response::forbidden('Your account has been deactivated. Contact admin.');
        }

        // Update streak
        self::updateStreak($db, $user);

        // Reload user after streak update
        $user = self::fetchUser($db, $user['id']);

        // Check badges
        $newBadges = BadgeEngine::checkAndAward($db, $user['id'], [
            'totalXP'      => (int) $user['total_xp'],
            'streak'       => (int) $user['streak'],
            'currentLevel' => (int) $user['current_level'],
            'taskCount'    => self::getTaskCount($db, $user['id'])
        ]);

        $token = JwtHelper::encode(['id' => $user['id']]);

        Response::success("Welcome back, {$user['name']}! 👋", [
            'token'     => $token,
            'user'      => $user,
            'newBadges' => $newBadges
        ]);
    }

    // ─── GET /api/auth/me ─────────────────────────────────────
    public static function me(): void {
        AuthMiddleware::protect();
        $userId = (int) $GLOBALS['auth_user']['id'];
        $db     = Database::getInstance()->getConnection();
        $user   = self::fetchUser($db, $userId);
        Response::success('OK', ['user' => $user]);
    }

    // ─── Helpers ──────────────────────────────────────────────

    /** Fetch full user with badges (no password) */
    private static function fetchUser(PDO $db, int $userId): array {
        $stmt = $db->prepare("
            SELECT id, name, email, college, branch, graduation_year,
                   current_level, total_xp, weekly_xp, streak, max_streak,
                   last_active_date, bio, avatar_url, linkedin_url, github_url,
                   role, is_active, created_at
            FROM users WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // Attach badges
        $bStmt = $db->prepare("
            SELECT b.id, b.name, b.icon_emoji, b.rarity, b.description, ub.earned_at
            FROM user_badges ub
            JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = ?
            ORDER BY ub.earned_at DESC
        ");
        $bStmt->execute([$userId]);
        $user['badges'] = $bStmt->fetchAll();

        return $user;
    }

    /** Update streak based on last_active_date */
    private static function updateStreak(PDO $db, array $user): void {
        $today      = new DateTime('today');
        $lastActive = $user['last_active_date'] ? new DateTime($user['last_active_date']) : null;

        if (!$lastActive) {
            $streak = 1;
        } else {
            $diff = (int) $today->diff($lastActive)->days;
            if ($diff === 0) return;         // Same day, no change
            elseif ($diff === 1) $streak = (int)$user['streak'] + 1;  // Consecutive
            else $streak = 1;               // Gap — reset
        }

        $maxStreak = max($streak, (int)$user['max_streak']);
        $db->prepare("
            UPDATE users SET streak = ?, max_streak = ?, last_active_date = CURDATE() WHERE id = ?
        ")->execute([$streak, $maxStreak, $user['id']]);
    }

    private static function getTaskCount(PDO $db, int $userId): int {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_completed_tasks WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    private static function getBody(): array {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private static function getNextSunday(): string {
        $d = new DateTime('next Sunday');
        return $d->format('Y-m-d');
    }
}
