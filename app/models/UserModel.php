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
