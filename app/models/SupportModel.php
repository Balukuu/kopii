<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Messages to the Kopii team: what people write on the contact page, and the "report this listing"
 * forms on provider, product and opportunity pages. They are stored first and emailed second, so a
 * message is never lost because the mail server had a bad moment; the admin inbox is the record.
 *
 * Written to run unchanged on MySQL and SQLite (one use of each named placeholder per statement,
 * LIKE ... ESCAPE '!').
 */
final class SupportModel
{
    public const CONTACT = 'contact';
    public const REPORT  = 'report';
    public const KINDS   = [self::CONTACT, self::REPORT];

    public const OPEN     = 'open';
    public const HANDLED  = 'handled';
    public const STATUSES = [self::OPEN, self::HANDLED];

    /** What a report can be about. */
    public const TARGETS = ['provider', 'product', 'opportunity'];

    /** Why someone reports a listing: key => label. The label is what the person picks and what the admin reads. */
    public const REPORT_REASONS = [
        'scam'       => 'Looks like a scam or fraud',
        'fake'       => 'Fake, misleading or not what it says',
        'offensive'  => 'Offensive, illegal or unsafe',
        'spam'       => 'Spam or a duplicate',
        'wrong_info' => 'Wrong price, phone or other details',
        'other'      => 'Something else',
    ];

    /** Topics on the contact form: key => label. */
    public const CONTACT_TOPICS = [
        'question'  => 'A question about Kopii',
        'listing'   => 'Help with my listing or account',
        'problem'   => 'A problem with a provider or seller',
        'privacy'   => 'My personal data (access, correction or deletion)',
        'partner'   => 'Partnership or press',
        'other'     => 'Something else',
    ];

    public static function create(array $d): int
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO support_messages
                (kind, user_id, name, email, subject, message, target_type, target_id, target_label, status, created_at)
             VALUES
                (:kind, :uid, :name, :email, :subject, :message, :ttype, :tid, :tlabel, :status, :created)'
        )->execute([
            ':kind'    => in_array($d['kind'] ?? '', self::KINDS, true) ? $d['kind'] : self::CONTACT,
            ':uid'     => !empty($d['user_id']) ? (int) $d['user_id'] : null,
            ':name'    => self::clip((string) ($d['name'] ?? ''), 120),
            ':email'   => self::clip((string) ($d['email'] ?? ''), 190),
            ':subject' => self::clip((string) ($d['subject'] ?? ''), 160),
            ':message' => trim((string) ($d['message'] ?? '')),
            ':ttype'   => in_array($d['target_type'] ?? '', self::TARGETS, true) ? $d['target_type'] : null,
            ':tid'     => !empty($d['target_id']) ? (int) $d['target_id'] : null,
            ':tlabel'  => self::clip((string) ($d['target_label'] ?? ''), 190),
            ':status'  => self::OPEN,
            ':created' => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.*, h.full_name AS handled_by_name
             FROM support_messages m
             LEFT JOIN users h ON h.id = m.handled_by
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * What a report is about: the title as it reads now, where the public page is, and where the admin
     * can act on it. Null when it does not exist. Anyone may report anything that exists, whatever its status.
     *
     * @return array{label: string, public_path: string, admin_path: string}|null
     */
    public static function resolveTarget(string $type, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        switch ($type) {
            case 'provider':
                $row = AdminModel::providerDetail($id);
                return $row === null ? null : [
                    'label'       => (string) $row['business_name'],
                    'public_path' => '/provider/' . $row['slug'],
                    'admin_path'  => '/admin/providers/' . (int) $row['id'],
                ];

            case 'product':
                $row = ProductModel::adminFind($id);
                return $row === null ? null : [
                    'label'       => (string) $row['name'] . ' (' . (string) $row['business_name'] . ')',
                    'public_path' => '/product/' . $row['slug'],
                    'admin_path'  => '/admin/products?q=' . rawurlencode(text_excerpt((string) $row['name'], 60)),
                ];

            case 'opportunity':
                $row = OpportunityModel::find($id);
                return $row === null ? null : [
                    'label'       => (string) $row['title'],
                    'public_path' => '/opportunities/' . (int) $row['id'],
                    'admin_path'  => '/admin/opportunities?q=' . rawurlencode(text_excerpt((string) $row['title'], 60)),
                ];
        }
        return null;
    }

    // ---------------------------------------------------------------------------------------
    // Admin inbox
    // ---------------------------------------------------------------------------------------

    /** Messages still waiting for someone to deal with them. */
    public static function openCount(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
    }

    /** @param array $f kind (contact|report), status (open|handled), q (text) */
    public static function search(array $f, int $limit, int $offset): array
    {
        $params = [];
        $where  = self::where($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT m.*, h.full_name AS handled_by_name
             FROM support_messages m
             LEFT JOIN users h ON h.id = m.handled_by
             WHERE ' . $where . '
             ORDER BY CASE WHEN m.status = \'open\' THEN 0 ELSE 1 END ASC, m.id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $f): int
    {
        $params = [];
        $where  = self::where($f, $params);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM support_messages m WHERE ' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Set the state explicitly (never "flip"), recording who dealt with it and the note they left. */
    public static function setStatus(int $id, string $status, int $adminUserId, string $note = ''): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $handled = $status === self::HANDLED;
        Database::connection()->prepare(
            'UPDATE support_messages
             SET status = :status, handled_by = :by, handled_at = :at, note = :note
             WHERE id = :id'
        )->execute([
            ':status' => $status,
            ':by'     => $handled ? $adminUserId : null,
            ':at'     => $handled ? date('Y-m-d H:i:s') : null,
            ':note'   => $handled ? self::clip($note, 500) : '',
            ':id'     => $id,
        ]);
    }

    private static function where(array $f, array &$params): string
    {
        $where = ['1 = 1'];

        $kind = (string) ($f['kind'] ?? '');
        if (in_array($kind, self::KINDS, true)) {
            $where[]         = 'm.kind = :kind';
            $params[':kind'] = $kind;
        }

        $status = (string) ($f['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $where[]           = 'm.status = :status';
            $params[':status'] = $status;
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . like_escape($q) . '%';
            $where[] = "(m.name LIKE :q1 ESCAPE '!' OR m.email LIKE :q2 ESCAPE '!' OR m.subject LIKE :q3 ESCAPE '!' OR m.message LIKE :q4 ESCAPE '!' OR m.target_label LIKE :q5 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = $params[':q5'] = $like;
        }

        return implode(' AND ', $where);
    }

    private static function clip(string $text, int $max): string
    {
        $text = trim($text);
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}
