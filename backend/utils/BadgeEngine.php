<?php
/**
 * BadgeEngine — checks all badge conditions for a user
 * and inserts newly earned badges into user_badges.
 * Returns array of newly awarded badge rows.
 */
class BadgeEngine {

    /**
     * Call this after any XP gain, level-up, or streak change.
     * $stats = ['totalXP' => int, 'streak' => int, 'currentLevel' => int, 'taskCount' => int]
     */
    public static function checkAndAward(PDO $db, int $userId, array $stats): array {
        $newlyEarned = [];

        // Get all active badges the user doesn't already have
        $stmt = $db->prepare("
            SELECT b.* FROM badges b
            WHERE b.is_active = 1
            AND b.id NOT IN (
                SELECT badge_id FROM user_badges WHERE user_id = ?
            )
        ");
        $stmt->execute([$userId]);
        $unearnedBadges = $stmt->fetchAll();

        foreach ($unearnedBadges as $badge) {
            $earned = false;

            switch ($badge['trigger_type']) {
                case 'xp':
                    $earned = $stats['totalXP'] >= $badge['trigger_value'];
                    break;
                case 'streak':
                    $earned = $stats['streak'] >= $badge['trigger_value'];
                    break;
                case 'level':
                    $earned = $stats['currentLevel'] >= $badge['trigger_value'];
                    break;
                case 'task_count':
                    $earned = $stats['taskCount'] >= $badge['trigger_value'];
                    break;
                default:
                    break;
            }

            if ($earned) {
                $insert = $db->prepare("
                    INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)
                ");
                $insert->execute([$userId, $badge['id']]);
                $newlyEarned[] = $badge;
            }
        }

        return $newlyEarned;
    }
}
