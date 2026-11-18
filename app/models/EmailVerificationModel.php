<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Single-use links that prove someone controls an email address.
 *
 * Why it matters: quote requests are matched to an account by email address. Without proof of
 * ownership, anyone could register someone else's address and read that person's requests.
 * Only a SHA-256 hash of each token is stored. The table is created by Migrator.
 */
final class EmailVerificationModel
{
    public const TTL_HOURS = 48;

    /** Verification emails allowed per account within one hour. */
    private const MAX_PER_HOUR = 3;

    public static function canIssue(int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM email_verifications WHERE user_id = :uid AND created_at > :since'
        );
        $stmt->execute([':uid' => $userId, ':since' => date('Y-m-d H:i:s', time() - 3600)]);
        return (int) $stmt->fetchColumn() < self::MAX_PER_HOUR;
    }

    /** Create a token and return the plain value to put in the email link. */
    public static function issue(int $userId): string
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM email_verifications WHERE expires_at < :cutoff')
            ->execute([':cutoff' => date('Y-m-d H:i:s', time() - 86400)]);

        $token = bin2hex(random_bytes(32));
        $now   = time();

        $pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, created_at, expires_at)
             VALUES (:uid, :hash, :created, :expires)'
        )->execute([
            ':uid'     => $userId,
            ':hash'    => hash('sha256', $token),
            ':created' => date('Y-m-d H:i:s', $now),
            ':expires' => date('Y-m-d H:i:s', $now + self::TTL_HOURS * 3600),
        ]);

        return $token;
    }

    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT * FROM email_verifications WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now LIMIT 1'
        );
        $stmt->execute([':hash' => hash('sha256', $token), ':now' => date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Atomically mark a token used. False means it was already used. */
    public static function claim(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE email_verifications SET used_at = :now WHERE id = :id AND used_at IS NULL'
        );
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
