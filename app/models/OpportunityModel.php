<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The opportunities board: things a client or business needs, which providers answer with a proposal.
 *
 * "Open" is a status AND a date: an opportunity is on the public board only while its status is
 * 'open', its closing date has not passed, and its poster's account is not suspended. Nothing has
 * to run overnight to expire old posts. Every named placeholder appears once per statement and LIKE
 * patterns escape with `!`, so the same queries run on MySQL and SQLite.
 */
final class OpportunityModel
{
    public const OPEN      = 'open';
    public const CLOSED    = 'closed';
    public const FILLED    = 'filled';
    public const SUSPENDED = 'suspended';
    public const STATUSES  = [self::OPEN, self::CLOSED, self::FILLED, self::SUSPENDED];

    public const KINDS = ['project', 'job', 'tender', 'supply'];

    public const NEW_RESPONSE   = 'new';
    public const SHORTLISTED    = 'shortlisted';
    public const DECLINED       = 'declined';
    public const HIRED          = 'hired';
    public const RESPONSE_STATUSES = [self::NEW_RESPONSE, self::SHORTLISTED, self::DECLINED, self::HIRED];

    /** How long a post stays open when the poster does not choose, and the longest they may choose. */
    public const DEFAULT_DAYS = 30;
    public const MAX_DAYS     = 90;

    // ---------------------------------------------------------------------------------------
    // The public board
    // ---------------------------------------------------------------------------------------

    /**
     * @param array $f q, kind, category (service category slug), city
     */
    public static function search(array $f, int $limit = 20, int $offset = 0): array
    {
        $params = [];
        $where  = self::publicWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT o.*, c.name AS category_name, u.full_name AS poster_name,
                    (SELECT COUNT(*) FROM opportunity_responses r WHERE r.opportunity_id = o.id) AS response_count
             FROM opportunities o
             INNER JOIN users u ON u.id = o.user_id
             LEFT JOIN categories c ON c.id = o.category_id
             WHERE ' . $where . '
             ORDER BY o.id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countSearch(array $f): int
    {
        $params = [];
        $where  = self::publicWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM opportunities o
             INNER JOIN users u ON u.id = o.user_id
             LEFT JOIN categories c ON c.id = o.category_id
             WHERE ' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function publicWhere(array $f, array &$params): string
    {
        $where = [
            "o.status = 'open'",
            'o.closes_on >= :today',
            'u.suspended_at IS NULL',
        ];
        $params[':today'] = date('Y-m-d');

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . like_escape($q) . '%';
            $where[] = "(o.title LIKE :q1 ESCAPE '!' OR o.description LIKE :q2 ESCAPE '!' OR c.name LIKE :q3 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }

        $kind = (string) ($f['kind'] ?? '');
        if (in_array($kind, self::KINDS, true)) {
            $where[]        = 'o.kind = :kind';
            $params[':kind'] = $kind;
        }

        if (!empty($f['category'])) {
            $where[]             = 'c.slug = :category';
            $params[':category'] = (string) $f['category'];
        }

        if (!empty($f['city'])) {
            // An opportunity open to "Anywhere in Uganda" matches every city filter.
            $where[]        = "(LOWER(o.city) = LOWER(:city) OR o.city = 'Anywhere in Uganda')";
            $params[':city'] = trim((string) $f['city']);
        }

        return implode(' AND ', $where);
    }

    /** The newest open opportunities, for the home page. */
    public static function latest(int $limit = 4): array
    {
        return self::search([], $limit);
    }

    public static function openCount(): int
    {
        return self::countSearch([]);
    }

    // ---------------------------------------------------------------------------------------
    // One opportunity
    // ---------------------------------------------------------------------------------------

    /** With its category and poster, whatever its status (callers decide who may see it). */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, c.name AS category_name, c.slug AS category_slug,
                    u.full_name AS poster_name, u.email AS poster_email, u.suspended_at AS poster_suspended_at,
                    (SELECT COUNT(*) FROM opportunity_responses r WHERE r.opportunity_id = o.id) AS response_count
             FROM opportunities o
             INNER JOIN users u ON u.id = o.user_id
             LEFT JOIN categories c ON c.id = o.category_id
             WHERE o.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Is this opportunity taking proposals right now? (A row from find() or search().) */
    public static function isOpen(array $o): bool
    {
        return (string) $o['status'] === self::OPEN && (string) $o['closes_on'] >= date('Y-m-d');
    }

    /** Whole days left (0 = closes today). */
    public static function daysLeft(array $o): int
    {
        $closes = strtotime((string) $o['closes_on'] . ' 00:00:00');
        $today  = strtotime(date('Y-m-d') . ' 00:00:00');
        if ($closes === false || $today === false) {
            return 0;
        }
        return max(0, (int) round(($closes - $today) / 86400));
    }

    public static function incrementViews(int $id): void
    {
        $key = 'viewed_opportunity_' . $id;
        if (!empty($_SESSION[$key])) {
            return;
        }
        $_SESSION[$key] = true;
        Database::connection()->prepare('UPDATE opportunities SET views_count = views_count + 1 WHERE id = :id')->execute([':id' => $id]);
    }

    // ---------------------------------------------------------------------------------------
    // Posting and managing (the poster)
    // ---------------------------------------------------------------------------------------

    /**
     * @param array $d category_id (nullable), kind, title, description, budget_min_ugx, budget_max_ugx, city, closes_on (Y-m-d)
     */
    public static function create(int $userId, array $d): int
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');

        $pdo->prepare(
            'INSERT INTO opportunities (user_id, category_id, kind, title, description, budget_min_ugx, budget_max_ugx, city, closes_on, status, views_count, created_at, updated_at)
             VALUES (:uid, :cid, :kind, :title, :desc, :min, :max, :city, :closes, :status, 0, :created, :updated)'
        )->execute([
            ':uid'     => $userId,
            ':cid'     => !empty($d['category_id']) ? (int) $d['category_id'] : null,
            ':kind'    => (string) $d['kind'],
            ':title'   => trim((string) $d['title']),
            ':desc'    => trim((string) $d['description']),
            ':min'     => !empty($d['budget_min_ugx']) ? (int) $d['budget_min_ugx'] : null,
            ':max'     => !empty($d['budget_max_ugx']) ? (int) $d['budget_max_ugx'] : null,
            ':city'    => (string) $d['city'],
            ':closes'  => (string) $d['closes_on'],
            ':status'  => self::OPEN,
            ':created' => $now,
            ':updated' => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** Everything this account has posted, newest first, with proposal counts. */
    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM opportunity_responses r WHERE r.opportunity_id = o.id) AS response_count,
                    (SELECT COUNT(*) FROM opportunity_responses r2 WHERE r2.opportunity_id = o.id AND r2.status = 'new') AS new_count
             FROM opportunities o
             LEFT JOIN categories c ON c.id = o.category_id
             WHERE o.user_id = :uid
             ORDER BY o.id DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** One of this account's own posts, or null. */
    public static function findOwned(int $id, int $userId): ?array
    {
        $opp = self::find($id);
        return $opp !== null && (int) $opp['user_id'] === $userId ? $opp : null;
    }

    public static function countRecentByUser(int $userId, string $since): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM opportunities WHERE user_id = :uid AND created_at >= :since');
        $stmt->execute([':uid' => $userId, ':since' => $since]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * The poster closes their post or marks it filled, or re-opens a closed one. A post an
     * administrator suspended is not the poster's to change.
     */
    public static function setPosterStatus(int $id, int $userId, string $status): bool
    {
        if (!in_array($status, [self::OPEN, self::CLOSED, self::FILLED], true)) {
            return false;
        }
        $stmt = Database::connection()->prepare(
            "UPDATE opportunities SET status = :status, updated_at = :updated WHERE id = :id AND user_id = :uid AND status <> 'suspended'"
        );
        $stmt->execute([':status' => $status, ':updated' => date('Y-m-d H:i:s'), ':id' => $id, ':uid' => $userId]);
        $changed = $stmt->rowCount() > 0;

        // Re-opening a post whose closing date has already passed would leave it closed: give it a fresh window.
        if ($changed && $status === self::OPEN) {
            Database::connection()->prepare('UPDATE opportunities SET closes_on = :closes WHERE id = :id AND closes_on < :today')
                ->execute([
                    ':closes' => date('Y-m-d', strtotime('+' . self::DEFAULT_DAYS . ' days')),
                    ':id'     => $id,
                    ':today'  => date('Y-m-d'),
                ]);
        }
        return $changed;
    }

    // ---------------------------------------------------------------------------------------
    // Proposals
    // ---------------------------------------------------------------------------------------

    /** Proposals on one opportunity, with the provider behind each (the poster's view). */
    public static function responses(int $opportunityId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, p.business_name, p.slug AS provider_slug, p.city AS provider_city, p.phone AS provider_phone,
                    p.whatsapp AS provider_whatsapp, p.is_verified, p.status AS provider_status,
                    p.avatar_path AS provider_avatar, p.starting_price_ugx,
                    u.email AS provider_email, c.name AS provider_category
             FROM opportunity_responses r
             INNER JOIN providers p ON p.id = r.provider_id
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE r.opportunity_id = :oid
             ORDER BY CASE r.status WHEN \'hired\' THEN 0 WHEN \'shortlisted\' THEN 1 WHEN \'new\' THEN 2 ELSE 3 END ASC, r.id DESC'
        );
        $stmt->execute([':oid' => $opportunityId]);
        return $stmt->fetchAll();
    }

    /** This provider's proposal on this opportunity, if they sent one. */
    public static function responseBy(int $opportunityId, int $providerId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM opportunity_responses WHERE opportunity_id = :oid AND provider_id = :pid LIMIT 1');
        $stmt->execute([':oid' => $opportunityId, ':pid' => $providerId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Save a proposal. A provider has one proposal per opportunity: the unique key makes a double
     * click or a second tab fail here instead of creating a duplicate.
     *
     * @param array $d message, quote_ugx (nullable), timeline
     * @return int|null the new id, or null when this provider had already responded
     */
    public static function addResponse(int $opportunityId, int $providerId, array $d): ?int
    {
        if (self::responseBy($opportunityId, $providerId) !== null) {
            return null;
        }

        $pdo = Database::connection();
        try {
            $pdo->prepare(
                'INSERT INTO opportunity_responses (opportunity_id, provider_id, message, quote_ugx, timeline, status, created_at)
                 VALUES (:oid, :pid, :msg, :quote, :timeline, :status, :created)'
            )->execute([
                ':oid'      => $opportunityId,
                ':pid'      => $providerId,
                ':msg'      => trim((string) $d['message']),
                ':quote'    => !empty($d['quote_ugx']) ? (int) $d['quote_ugx'] : null,
                ':timeline' => trim((string) ($d['timeline'] ?? '')),
                ':status'   => self::NEW_RESPONSE,
                ':created'  => date('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            // Lost a race with a second submit of the same proposal: the unique key refused it.
            if (self::responseBy($opportunityId, $providerId) !== null) {
                return null;
            }
            throw $e;
        }
        return (int) $pdo->lastInsertId();
    }

    /** A provider's own proposals across the board, newest first. */
    public static function responsesByProvider(int $providerId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, o.title, o.kind, o.city, o.closes_on, o.status AS opportunity_status
             FROM opportunity_responses r
             INNER JOIN opportunities o ON o.id = r.opportunity_id
             WHERE r.provider_id = :pid
             ORDER BY r.id DESC'
        );
        $stmt->execute([':pid' => $providerId]);
        return $stmt->fetchAll();
    }

    /** A proposal on one of this poster's opportunities, with who sent it (for an email), or null. */
    public static function findResponseForPoster(int $responseId, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, o.title AS opportunity_title, o.user_id AS poster_id,
                    p.business_name, u.email AS provider_email, u.full_name AS provider_owner_name
             FROM opportunity_responses r
             INNER JOIN opportunities o ON o.id = r.opportunity_id
             INNER JOIN providers p ON p.id = r.provider_id
             INNER JOIN users u ON u.id = p.user_id
             WHERE r.id = :id AND o.user_id = :uid
             LIMIT 1'
        );
        $stmt->execute([':id' => $responseId, ':uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function setResponseStatus(int $responseId, string $status): void
    {
        if (!in_array($status, self::RESPONSE_STATUSES, true)) {
            return;
        }
        Database::connection()->prepare('UPDATE opportunity_responses SET status = :status WHERE id = :id')
            ->execute([':status' => $status, ':id' => $responseId]);
    }

    /** A provider withdraws their own proposal. */
    public static function withdrawResponse(int $responseId, int $providerId): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM opportunity_responses WHERE id = :id AND provider_id = :pid');
        $stmt->execute([':id' => $responseId, ':pid' => $providerId]);
        return $stmt->rowCount() > 0;
    }

    /** New proposals waiting across everything this account posted (for the dashboard badge). */
    public static function newResponseCount(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM opportunity_responses r
             INNER JOIN opportunities o ON o.id = r.opportunity_id
             WHERE o.user_id = :uid AND r.status = 'new'"
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    // ---------------------------------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------------------------------

    /** @param array $f q, status, kind */
    public static function adminSearch(array $f, int $limit, int $offset): array
    {
        $params = [];
        $where  = self::adminWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT o.*, c.name AS category_name, u.full_name AS poster_name, u.email AS poster_email,
                    u.suspended_at AS poster_suspended_at,
                    (SELECT COUNT(*) FROM opportunity_responses r WHERE r.opportunity_id = o.id) AS response_count
             FROM opportunities o
             INNER JOIN users u ON u.id = o.user_id
             LEFT JOIN categories c ON c.id = o.category_id
             WHERE ' . $where . '
             ORDER BY o.id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function adminCount(array $f): int
    {
        $params = [];
        $where  = self::adminWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM opportunities o
             INNER JOIN users u ON u.id = o.user_id
             WHERE ' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function adminWhere(array $f, array &$params): string
    {
        $where = ['1 = 1'];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . like_escape($q) . '%';
            $where[] = "(o.title LIKE :q1 ESCAPE '!' OR u.full_name LIKE :q2 ESCAPE '!' OR u.email LIKE :q3 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }

        $status = (string) ($f['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $where[]           = 'o.status = :status';
            $params[':status'] = $status;
        }

        $kind = (string) ($f['kind'] ?? '');
        if (in_array($kind, self::KINDS, true)) {
            $where[]        = 'o.kind = :kind';
            $params[':kind'] = $kind;
        }

        return implode(' AND ', $where);
    }

    /** Set the status explicitly (never "flip"). */
    public static function adminSetStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        Database::connection()->prepare('UPDATE opportunities SET status = :status, updated_at = :updated WHERE id = :id')
            ->execute([':status' => $status, ':updated' => date('Y-m-d H:i:s'), ':id' => $id]);
    }
}
