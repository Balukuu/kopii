<?php
defined('KOPII_APP') or exit('Direct access denied.');

class UserModel
{
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute([':email' => trim($email)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Record that the owner of this address has proved control of it. */
    public static function markEmailVerified(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET email_verified_at = :now WHERE id = :id AND email_verified_at IS NULL'
        );
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $userId]);
    }

    public static function updatePassword(int $userId, string $newPassword): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id'   => $userId,
        ]);
    }

    public static function updateDetails(int $userId, string $fullName, string $phone): void
    {
        Database::connection()
            ->prepare('UPDATE users SET full_name = :name, phone = :phone WHERE id = :id')
            ->execute([':name' => trim($fullName), ':phone' => trim($phone), ':id' => $userId]);
    }

    /**
     * Change the sign-in email. The new address starts out unconfirmed: it has not proved anything yet,
     * and quote requests are only matched to an account by a confirmed address.
     */
    public static function updateEmail(int $userId, string $email): void
    {
        Database::connection()
            ->prepare('UPDATE users SET email = :email, email_verified_at = NULL WHERE id = :id')
            ->execute([':email' => strtolower(trim($email)), ':id' => $userId]);
    }

    /**
     * Delete an account and everything that belongs to it, for good.
     *
     * Foreign keys remove the listing (with its portfolio, packages, products, photos, leads received and
     * reviews received), the opportunities the person posted with their proposals, and the sign-in
     * tokens. What the keys do not cover is handled here:
     *   - reviews the person wrote about other providers are deleted (they carry the person's name);
     *   - quote requests they sent stay in those providers' inboxes, but lose the link to the account.
     *
     * @return list<string> the uploaded photos to delete from disk, once this has succeeded
     */
    public static function deleteAccount(int $userId): array
    {
        $pdo   = Database::connection();
        $files = [];

        $collect = static function (string $sql) use ($pdo, $userId, &$files): void {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
                if ($path !== null && $path !== '') {
                    $files[] = (string) $path;
                }
            }
        };
        $collect('SELECT avatar_path FROM users WHERE id = :uid');
        $collect('SELECT avatar_path FROM providers WHERE user_id = :uid');
        $collect('SELECT cover_path FROM providers WHERE user_id = :uid');
        $collect('SELECT pi.image_path FROM portfolio_items pi INNER JOIN providers p ON p.id = pi.provider_id WHERE p.user_id = :uid');
        $collect('SELECT i.image_path FROM product_images i INNER JOIN products pr ON pr.id = i.product_id INNER JOIN providers p ON p.id = pr.provider_id WHERE p.user_id = :uid');

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM reviews WHERE reviewer_user_id = :uid')->execute([':uid' => $userId]);
            $pdo->prepare('UPDATE inquiries SET client_user_id = NULL WHERE client_user_id = :uid')->execute([':uid' => $userId]);
            $pdo->prepare('UPDATE inquiries SET product_id = NULL WHERE product_id IN (SELECT pr.id FROM products pr INNER JOIN providers p ON p.id = pr.provider_id WHERE p.user_id = :uid)')
                ->execute([':uid' => $userId]);
            $pdo->prepare('DELETE FROM users WHERE id = :uid')->execute([':uid' => $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return array_values(array_unique($files));
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, created_at)
             VALUES (:name, :email, :phone, :hash, :role, :created)'
        );
        $stmt->execute([
            ':name'    => trim((string) $data['full_name']),
            ':email'   => strtolower(trim((string) $data['email'])),
            ':phone'   => trim((string) ($data['phone'] ?? '')),
            ':hash'    => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            ':role'    => $data['role'] ?? 'provider',
            ':created' => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }
}
