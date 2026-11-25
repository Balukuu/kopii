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

    // ---------------------------------------------------------------------------------------
    // Admin: manage the lists providers and sellers choose from
    //
    // There are two lists. "service" categories (photography, catering...) are what providers are
    // listed under; "product" categories (fabric, groceries...) are what shop products are filed under.
    // A slug is made once, from the first name, and never changes: it sits in public URLs and links.
    // ---------------------------------------------------------------------------------------

    public const KINDS = ['service', 'product'];

    /** Icons svg_icon() can draw. ('briefcase' is the fallback shape.) */
    public const ICONS = ['camera', 'video', 'palette', 'scissors', 'utensils', 'code', 'sparkles', 'home', 'music', 'truck', 'layers', 'image', 'search', 'wrench', 'sun', 'leaf', 'briefcase'];

    private static function table(string $kind): string
    {
        return $kind === 'product' ? 'product_categories' : 'categories';
    }

    /** Every category of a kind with how many listings (providers or products) use it. */
    public static function adminList(string $kind): array
    {
        $pdo = Database::connection();
        if ($kind === 'product') {
            $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products x WHERE x.category_id = c.id) AS uses
                    FROM product_categories c
                    ORDER BY c.sort_order ASC, c.name ASC';
        } else {
            $sql = 'SELECT c.*, (SELECT COUNT(*) FROM providers x WHERE x.category_id = c.id) AS uses,
                           (SELECT COUNT(*) FROM opportunities o WHERE o.category_id = c.id) AS opportunity_uses
                    FROM categories c
                    ORDER BY c.sort_order ASC, c.name ASC';
        }
        return $pdo->query($sql)->fetchAll();
    }

    public static function adminFind(string $kind, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ' . self::table($kind) . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Another category of this kind already has this name (ignoring case). */
    public static function adminNameTaken(string $kind, string $name, int $exceptId = 0): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM ' . self::table($kind) . ' WHERE LOWER(name) = LOWER(:name) AND id <> :id'
        );
        $stmt->execute([':name' => trim($name), ':id' => $exceptId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array $d name, icon, sort_order, description (service categories only) */
    public static function adminCreate(string $kind, array $d): int
    {
        $pdo   = Database::connection();
        $table = self::table($kind);
        $slug  = self::uniqueSlug($table, (string) $d['name']);

        if ($kind === 'product') {
            $pdo->prepare('INSERT INTO product_categories (name, slug, icon, sort_order) VALUES (:name, :slug, :icon, :sort)')
                ->execute([':name' => $d['name'], ':slug' => $slug, ':icon' => $d['icon'], ':sort' => (int) $d['sort_order']]);
        } else {
            $pdo->prepare('INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (:name, :slug, :icon, :desc, :sort)')
                ->execute([':name' => $d['name'], ':slug' => $slug, ':icon' => $d['icon'], ':desc' => $d['description'] ?? '', ':sort' => (int) $d['sort_order']]);
        }
        return (int) $pdo->lastInsertId();
    }

    public static function adminUpdate(string $kind, int $id, array $d): void
    {
        $pdo = Database::connection();
        if ($kind === 'product') {
            $pdo->prepare('UPDATE product_categories SET name = :name, icon = :icon, sort_order = :sort WHERE id = :id')
                ->execute([':name' => $d['name'], ':icon' => $d['icon'], ':sort' => (int) $d['sort_order'], ':id' => $id]);
        } else {
            $pdo->prepare('UPDATE categories SET name = :name, icon = :icon, description = :desc, sort_order = :sort WHERE id = :id')
                ->execute([':name' => $d['name'], ':icon' => $d['icon'], ':desc' => $d['description'] ?? '', ':sort' => (int) $d['sort_order'], ':id' => $id]);
        }
    }

    /** Delete a category. The caller has already made sure nothing uses it (the database refuses otherwise). */
    public static function adminDelete(string $kind, int $id): void
    {
        Database::connection()->prepare('DELETE FROM ' . self::table($kind) . ' WHERE id = :id')->execute([':id' => $id]);
    }

    private static function uniqueSlug(string $table, string $name): string
    {
        $pdo  = Database::connection();
        $base = slugify($name);
        $slug = $base;
        for ($n = 2; $n < 500; $n++) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE slug = :slug');
            $stmt->execute([':slug' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }
}
