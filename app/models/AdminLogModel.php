<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The audit trail of administrator actions. It answers "who verified this listing, and when?",
 * which matters because the site promises visitors that the Kopii team checked a Verified provider.
 *
 * Writing the log never blocks the action that was just taken: a failure is logged and swallowed.
 */
final class AdminLogModel
{
    public const TYPES = ['provider', 'review', 'user'];

    public static function record(string $action, string $targetType, ?int $targetId, string $label, string $note = ''): void
    {
        try {
            $admin = current_user();
            Database::connection()->prepare(
                'INSERT INTO admin_log (admin_user_id, action, target_type, target_id, target_label, note, created_at)
                 VALUES (:admin, :action, :ttype, :tid, :label, :note, :created)'
            )->execute([
                ':admin'   => !empty($admin['id']) ? (int) $admin['id'] : null,
                ':action'  => self::clip($action, 40),
                ':ttype'   => self::clip($targetType, 20),
                ':tid'     => $targetId,
                ':label'   => self::clip($label, 190),
                ':note'    => self::clip($note, 500),
                ':created' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            report_exception($e);
        }
    }

    /**
     * @param string   $targetType '' for everything, otherwise provider | review | user
     * @param int|null $targetId   only entries about this one target
     */
    public static function recent(int $limit = 20, int $offset = 0, string $targetType = '', ?int $targetId = null): array
    {
        [$where, $params] = self::filter($targetType, $targetId);
        $stmt = Database::connection()->prepare(
            'SELECT l.*, u.full_name AS admin_name
             FROM admin_log l
             LEFT JOIN users u ON u.id = l.admin_user_id
             WHERE ' . $where . '
             ORDER BY l.id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(string $targetType = '', ?int $targetId = null): int
    {
        [$where, $params] = self::filter($targetType, $targetId);
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM admin_log l WHERE ' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** One plain sentence for a log row (escape it when printing). */
    public static function describe(array $row): string
    {
        $label = (string) ($row['target_label'] ?? '');
        return match ((string) ($row['action'] ?? '')) {
            'provider.verify'    => 'Verified ' . $label,
            'provider.unverify'  => 'Removed the Verified badge from ' . $label,
            'provider.feature'   => 'Featured ' . $label,
            'provider.unfeature' => 'Removed ' . $label . ' from Featured',
            'provider.pause'     => 'Paused ' . $label,
            'provider.activate'  => 'Reactivated ' . $label,
            'review.approve'     => 'Approved: ' . $label,
            'review.delete'      => 'Deleted: ' . $label,
            'user.suspend'       => 'Suspended ' . $label,
            'user.reinstate'     => 'Reinstated ' . $label,
            'user.resend_verification' => 'Sent a new confirmation email to ' . $label,
            default              => trim((string) ($row['action'] ?? 'Action') . ' ' . $label),
        };
    }

    private static function filter(string $targetType, ?int $targetId): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if (in_array($targetType, self::TYPES, true)) {
            $where[]           = 'l.target_type = :ttype';
            $params[':ttype']  = $targetType;
        }
        if ($targetId !== null) {
            $where[]          = 'l.target_id = :tid';
            $params[':tid']   = $targetId;
        }
        return [implode(' AND ', $where), $params];
    }

    private static function clip(string $text, int $max): string
    {
        $text = trim($text);
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max, 'UTF-8');
        }
        return substr($text, 0, $max);
    }
}
