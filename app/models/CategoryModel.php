<?php
defined('KOPII_APP') or exit('Direct access denied.');

class CategoryModel
{
    public static function allWithCounts(): array
    {
        $pdo = Database::connection();
        $sql = "
            SELECT
                c.*,
                COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) AS provider_count,
                COUNT(DISTINCT CASE WHEN p.status = 'active' THEN pi.id END) AS portfolio_count
            FROM categories c
            LEFT JOIN providers p ON p.category_id = c.id
            LEFT JOIN portfolio_items pi ON pi.provider_id = p.id
            GROUP BY c.id, c.name, c.slug, c.icon, c.description, c.sort_order
            ORDER BY c.sort_order ASC, c.name ASC
        ";
        return $pdo->query($sql)->fetchAll();
    }

    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
