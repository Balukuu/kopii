<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Reviews have a status. Only 'published' reviews are shown and counted toward a provider's rating
 * (and therefore their ranking). A review from someone who really requested a quote through Kopii
 * is published straight away; anything else waits as 'pending' until an administrator approves it.
 */
class ReviewModel
{
    public const PUBLISHED = 'published';
    public const PENDING   = 'pending';

    public static function forProvider(int $providerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM reviews WHERE provider_id = :pid AND status = :status ORDER BY id DESC');
        $stmt->execute([':pid' => $providerId, ':status' => self::PUBLISHED]);
        return $stmt->fetchAll();
    }

    /**
     * @param array $data provider_id, reviewer_user_id, reviewer_name, reviewer_city, service_hired,
     *                    rating, comment, status (published|pending), verified_request (bool)
     */
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $rating = min(5, max(1, (int) ($data['rating'] ?? 5)));
        $status = ($data['status'] ?? self::PENDING) === self::PUBLISHED ? self::PUBLISHED : self::PENDING;

        $stmt = $pdo->prepare(
            'INSERT INTO reviews (provider_id, reviewer_user_id, reviewer_name, reviewer_city, service_hired, rating, comment, status, verified_request, created_at)
             VALUES (:pid, :ruid, :rname, :rcity, :service, :rating, :comment, :status, :verified, :created)'
        );
        $stmt->execute([
            ':pid'      => (int) $data['provider_id'],
            ':ruid'     => !empty($data['reviewer_user_id']) ? (int) $data['reviewer_user_id'] : null,
            ':rname'    => trim((string) $data['reviewer_name']),
            ':rcity'    => trim((string) ($data['reviewer_city'] ?? '')),
            ':service'  => trim((string) ($data['service_hired'] ?? '')),
            ':rating'   => $rating,
            ':comment'  => trim((string) $data['comment']),
            ':status'   => $status,
            ':verified' => !empty($data['verified_request']) ? 1 : 0,
            ':created'  => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** Whether this account has already reviewed this provider (one review per account). */
    public static function existsFromUser(int $providerId, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM reviews WHERE provider_id = :pid AND reviewer_user_id = :uid LIMIT 1'
        );
        $stmt->execute([':pid' => $providerId, ':uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Reviews for the admin console: waiting ones first, then newest. */
    public static function adminList(int $limit = 100): array
    {
        $sql = "
            SELECT r.*, p.business_name, p.slug AS provider_slug
            FROM reviews r
            INNER JOIN providers p ON p.id = r.provider_id
            ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END ASC, r.id DESC
            LIMIT " . max(1, $limit);
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function pendingCount(): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM reviews WHERE status = :status');
        $stmt->execute([':status' => self::PENDING]);
        return (int) $stmt->fetchColumn();
    }

    public static function approve(int $reviewId): void
    {
        $stmt = Database::connection()->prepare('UPDATE reviews SET status = :status WHERE id = :id');
        $stmt->execute([':status' => self::PUBLISHED, ':id' => $reviewId]);
    }

    public static function delete(int $reviewId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM reviews WHERE id = :id');
        $stmt->execute([':id' => $reviewId]);
    }
}
