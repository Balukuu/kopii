<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Queries that only the admin console uses: needs-attention counts, searchable lists of listings
 * and accounts, and suspending / reinstating an account.
 *
 * Written to run unchanged on MySQL and SQLite: every named placeholder appears once per
 * statement (MySQL's native prepared statements reject repeats), and LIKE patterns use `!` as the
 * escape character because the escape syntax for a backslash differs between the two databases.
 */
final class AdminModel
{
    public const PROVIDER_STATUSES = ['active', 'paused', 'suspended'];
    public const USER_ROLES        = ['admin', 'provider', 'client'];

    // ---------------------------------------------------------------------------------------
    // Overview
    // ---------------------------------------------------------------------------------------

    /** Counts that tell the admin what is waiting for a decision. */
    public static function attentionCounts(): array
    {
        $pdo = Database::connection();
        return [
            'pending_reviews'      => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn(),
            'unverified_providers' => (int) $pdo->query("SELECT COUNT(*) FROM providers WHERE status = 'active' AND is_verified = 0")->fetchColumn(),
            'unconfirmed_emails'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND email_verified_at IS NULL AND suspended_at IS NULL")->fetchColumn(),
            'open_messages'        => (int) $pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn(),
        ];
    }

    /** Listings that are not publicly visible (paused by an admin, or suspended with their account). */
    public static function hiddenListings(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM providers WHERE status <> 'active'")->fetchColumn();
    }

    /** What happened on the platform since the given 'Y-m-d H:i:s' time. */
    public static function activitySince(string $since): array
    {
        $pdo   = Database::connection();
        $count = static function (string $sql) use ($pdo, $since): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':since' => $since]);
            return (int) $stmt->fetchColumn();
        };

        return [
            'accounts'  => $count("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND created_at >= :since"),
            'listings'  => $count('SELECT COUNT(*) FROM providers WHERE created_at >= :since'),
            'products'      => $count('SELECT COUNT(*) FROM products WHERE created_at >= :since'),
            'opportunities' => $count('SELECT COUNT(*) FROM opportunities WHERE created_at >= :since'),
            'proposals'     => $count('SELECT COUNT(*) FROM opportunity_responses WHERE created_at >= :since'),
            'inquiries' => $count('SELECT COUNT(*) FROM inquiries WHERE created_at >= :since'),
            'reviews'   => $count('SELECT COUNT(*) FROM reviews WHERE created_at >= :since'),
        ];
    }

    // ---------------------------------------------------------------------------------------
    // Providers
    // ---------------------------------------------------------------------------------------

    /**
     * @param array $f q (text), status (active|paused|suspended), verified ('1'|'0'), featured ('1'), portfolio ('thin')
     */
    public static function providerList(array $f, int $limit, int $offset): array
    {
        $params = [];
        $where  = self::providerWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.business_name, p.slug, p.city, p.status, p.is_verified, p.is_featured, p.verified_at,
                    p.starting_price_ugx, p.created_at,
                    u.full_name AS owner_name, u.email AS owner_email, u.email_verified_at AS owner_email_verified_at,
                    c.name AS category_name,
                    (SELECT COUNT(*) FROM portfolio_items pi WHERE pi.provider_id = p.id) AS portfolio_count,
                    (SELECT COUNT(*) FROM inquiries i WHERE i.provider_id = p.id) AS inquiry_count
             FROM providers p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE ' . $where . '
             ORDER BY p.id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function providerCount(array $f): int
    {
        $params = [];
        $where  = self::providerWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM providers p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE ' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** One listing with everything an admin needs to decide about it. */
    public static function providerDetail(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*,
                    u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone,
                    u.email_verified_at AS owner_email_verified_at, u.suspended_at AS owner_suspended_at,
                    u.created_at AS owner_created_at,
                    c.name AS category_name,
                    vb.full_name AS verified_by_name,
                    (SELECT COUNT(*) FROM inquiries i WHERE i.provider_id = p.id) AS inquiry_count,
                    (SELECT COUNT(*) FROM reviews r WHERE r.provider_id = p.id AND r.status = \'published\') AS published_reviews,
                    (SELECT COUNT(*) FROM reviews r2 WHERE r2.provider_id = p.id AND r2.status = \'pending\') AS pending_reviews
             FROM providers p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN users vb ON vb.id = p.verified_by
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function providerWhere(array $f, array &$params): string
    {
        $where = ['1 = 1'];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . self::escapeLike($q) . '%';
            $where[] = "(p.business_name LIKE :q1 ESCAPE '!' OR u.full_name LIKE :q2 ESCAPE '!' OR u.email LIKE :q3 ESCAPE '!' OR p.city LIKE :q4 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = $like;
        }

        $status = (string) ($f['status'] ?? '');
        if (in_array($status, self::PROVIDER_STATUSES, true)) {
            $where[]           = 'p.status = :status';
            $params[':status'] = $status;
        }

        $verified = (string) ($f['verified'] ?? '');
        if ($verified === '1' || $verified === '0') {
            $where[] = 'p.is_verified = ' . (int) $verified;
        }

        if ((string) ($f['featured'] ?? '') === '1') {
            $where[] = 'p.is_featured = 1';
        }

        // Thin listings (fewer than 3 photos of work) are where fake or low-effort profiles hide.
        if ((string) ($f['portfolio'] ?? '') === 'thin') {
            $where[] = '(SELECT COUNT(*) FROM portfolio_items pt WHERE pt.provider_id = p.id) < 3';
        }

        return implode(' AND ', $where);
    }

    // ---------------------------------------------------------------------------------------
    // Users
    // ---------------------------------------------------------------------------------------

    /**
     * @param array $f q (text), role (admin|provider|client), email (confirmed|unconfirmed), status (active|suspended)
     */
    public static function userList(array $f, int $limit, int $offset): array
    {
        $params = [];
        $where  = self::userWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.full_name, u.email, u.phone, u.role, u.email_verified_at, u.suspended_at, u.created_at,
                    p.id AS provider_id, p.business_name, p.status AS provider_status
             FROM users u
             LEFT JOIN providers p ON p.user_id = u.id
             WHERE ' . $where . '
             ORDER BY u.id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function userCount(array $f): int
    {
        $params = [];
        $where  = self::userWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM users u LEFT JOIN providers p ON p.user_id = u.id WHERE ' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function userWhere(array $f, array &$params): string
    {
        $where = ['1 = 1'];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . self::escapeLike($q) . '%';
            $where[] = "(u.full_name LIKE :q1 ESCAPE '!' OR u.email LIKE :q2 ESCAPE '!' OR p.business_name LIKE :q3 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }

        $role = (string) ($f['role'] ?? '');
        if (in_array($role, self::USER_ROLES, true)) {
            $where[]         = 'u.role = :role';
            $params[':role'] = $role;
        }

        $email = (string) ($f['email'] ?? '');
        if ($email === 'confirmed') {
            $where[] = 'u.email_verified_at IS NOT NULL';
        } elseif ($email === 'unconfirmed') {
            $where[] = 'u.email_verified_at IS NULL';
        }

        $status = (string) ($f['status'] ?? '');
        if ($status === 'suspended') {
            $where[] = 'u.suspended_at IS NOT NULL';
        } elseif ($status === 'active') {
            $where[] = 'u.suspended_at IS NULL';
        }

        return implode(' AND ', $where);
    }

    /**
     * Block the account from signing in and hide its listing. The listing gets its own 'suspended'
     * status (not 'paused') so reinstating the account never silently re-publishes it.
     */
    public static function suspendUser(int $userId): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET suspended_at = :now WHERE id = :id AND suspended_at IS NULL')
                ->execute([':now' => $now, ':id' => $userId]);
            $pdo->prepare("UPDATE providers SET status = 'suspended', updated_at = :now WHERE user_id = :id AND status <> 'suspended'")
                ->execute([':now' => $now, ':id' => $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Let the account sign in again. Its listing comes back as 'paused': an admin makes it public on purpose. */
    public static function reinstateUser(int $userId): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET suspended_at = NULL WHERE id = :id')->execute([':id' => $userId]);
            $pdo->prepare("UPDATE providers SET status = 'paused', updated_at = :now WHERE user_id = :id AND status = 'suspended'")
                ->execute([':now' => $now, ':id' => $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Email addresses of every administrator (used to say a review is waiting). */
    public static function adminEmails(): array
    {
        $rows = Database::connection()->query("SELECT email FROM users WHERE role = 'admin' AND suspended_at IS NULL")->fetchAll();
        return array_values(array_filter(array_map(static fn(array $r): string => (string) $r['email'], $rows)));
    }

    /** Escape a user's search text so % and _ match themselves in a LIKE ... ESCAPE '!' pattern. */
    private static function escapeLike(string $text): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);
    }
}
