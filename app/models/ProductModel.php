<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Products in the shop.
 *
 * A product is visible to the public only while BOTH the product and its seller's provider listing
 * are active, so pausing or suspending a provider hides their shelf with them. Every named
 * placeholder appears once per statement and LIKE patterns escape with `!`, so the same queries run
 * on MySQL and SQLite.
 */
final class ProductModel
{
    public const ACTIVE    = 'active';
    public const PAUSED    = 'paused';
    public const SUSPENDED = 'suspended';
    public const STATUSES  = [self::ACTIVE, self::PAUSED, self::SUSPENDED];

    public const MAX_IMAGES       = 5;
    public const MAX_PER_PROVIDER = 100;

    private const COVER_SQL = '(SELECT i.image_path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order ASC, i.id ASC LIMIT 1)';

    // ---------------------------------------------------------------------------------------
    // Categories
    // ---------------------------------------------------------------------------------------

    /** All product categories with how many products are visible in each. */
    public static function categories(): array
    {
        return Database::connection()->query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM products p
                     INNER JOIN providers pr ON pr.id = p.provider_id
                     WHERE p.category_id = c.id AND p.status = 'active' AND pr.status = 'active') AS product_count
             FROM product_categories c
             ORDER BY c.sort_order ASC, c.name ASC"
        )->fetchAll();
    }

    public static function categoryBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM product_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch() ?: null;
    }

    public static function categoryById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM product_categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // ---------------------------------------------------------------------------------------
    // Public listing
    // ---------------------------------------------------------------------------------------

    /**
     * @param array $f q, category (slug), city, max_price, in_stock (1), condition, provider_id, sort
     */
    public static function search(array $f, int $limit = 24, int $offset = 0): array
    {
        $params = [];
        $where  = self::publicWhere($f, $params);

        $orderBy = match ((string) ($f['sort'] ?? 'newest')) {
            'price_asc'  => 'CASE WHEN p.price_ugx = 0 THEN 1 ELSE 0 END ASC, p.price_ugx ASC, p.id DESC',
            'price_desc' => 'p.price_ugx DESC, p.id DESC',
            'popular'    => 'p.views_count DESC, p.id DESC',
            default      => 'p.id DESC',
        };

        $stmt = Database::connection()->prepare(
            'SELECT p.*,
                    pc.name AS category_name, pc.slug AS category_slug, pc.icon AS category_icon,
                    pr.business_name, pr.slug AS provider_slug, pr.city, pr.is_verified,
                    ' . self::COVER_SQL . ' AS cover_path
             FROM products p
             INNER JOIN providers pr ON pr.id = p.provider_id
             INNER JOIN product_categories pc ON pc.id = p.category_id
             WHERE ' . $where . '
             ORDER BY ' . $orderBy . '
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
             FROM products p
             INNER JOIN providers pr ON pr.id = p.provider_id
             INNER JOIN product_categories pc ON pc.id = p.category_id
             WHERE ' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private static function publicWhere(array $f, array &$params): string
    {
        $where = ["p.status = 'active'", "pr.status = 'active'"];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . like_escape($q) . '%';
            $where[] = "(p.name LIKE :q1 ESCAPE '!' OR p.description LIKE :q2 ESCAPE '!' OR pr.business_name LIKE :q3 ESCAPE '!' OR pc.name LIKE :q4 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = $like;
        }

        if (!empty($f['category'])) {
            $where[]             = 'pc.slug = :category';
            $params[':category'] = (string) $f['category'];
        }

        if (!empty($f['city'])) {
            $where[]        = 'LOWER(pr.city) = LOWER(:city)';
            $params[':city'] = trim((string) $f['city']);
        }

        if ((int) ($f['max_price'] ?? 0) > 0) {
            // Products priced "ask for price" (0) are left out of a price filter: they are not under any budget.
            $where[]              = 'p.price_ugx > 0 AND p.price_ugx <= :max_price';
            $params[':max_price'] = (int) $f['max_price'];
        }

        if (!empty($f['in_stock'])) {
            $where[] = "p.stock_status <> 'out_of_stock'";
        }

        $condition = (string) ($f['condition'] ?? '');
        if (in_array($condition, ['new', 'used', 'refurbished'], true)) {
            $where[]              = 'p.item_condition = :cond';
            $params[':cond']      = $condition;
        }

        if (!empty($f['provider_id'])) {
            $where[]               = 'p.provider_id = :provider_id';
            $params[':provider_id'] = (int) $f['provider_id'];
        }

        if (!empty($f['exclude_id'])) {
            $where[]              = 'p.id <> :exclude_id';
            $params[':exclude_id'] = (int) $f['exclude_id'];
        }

        return implode(' AND ', $where);
    }

    /** The newest visible products, for the home page. */
    public static function latest(int $limit = 8): array
    {
        return self::search(['sort' => 'newest'], $limit);
    }

    /** How many products are on sale, and how many sellers have at least one. */
    public static function marketStats(): array
    {
        $row = Database::connection()->query(
            "SELECT COUNT(*) AS products, COUNT(DISTINCT p.provider_id) AS sellers
             FROM products p
             INNER JOIN providers pr ON pr.id = p.provider_id
             WHERE p.status = 'active' AND pr.status = 'active'"
        )->fetch();
        return ['products' => (int) ($row['products'] ?? 0), 'sellers' => (int) ($row['sellers'] ?? 0)];
    }

    // ---------------------------------------------------------------------------------------
    // One product
    // ---------------------------------------------------------------------------------------

    /** A product with its seller, category and photos, whatever its status (callers decide who may see it). */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*,
                    pc.name AS category_name, pc.slug AS category_slug, pc.icon AS category_icon,
                    pr.user_id AS provider_user_id, pr.business_name, pr.slug AS provider_slug, pr.city, pr.neighborhood,
                    pr.phone AS provider_phone, pr.whatsapp AS provider_whatsapp, pr.is_verified, pr.avatar_path AS provider_avatar,
                    pr.response_time, pr.status AS provider_status, pr.provider_type
             FROM products p
             INNER JOIN providers pr ON pr.id = p.provider_id
             INNER JOIN product_categories pc ON pc.id = p.category_id
             WHERE p.slug = :slug
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }
        $product['images'] = self::images((int) $product['id']);
        return $product;
    }

    /** One of this provider's own products (for editing), or null. */
    public static function findOwned(int $id, int $providerId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM products WHERE id = :id AND provider_id = :pid LIMIT 1');
        $stmt->execute([':id' => $id, ':pid' => $providerId]);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }
        $product['images'] = self::images((int) $product['id']);
        return $product;
    }

    public static function images(int $productId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC');
        $stmt->execute([':id' => $productId]);
        return $stmt->fetchAll();
    }

    /** Count a view once per visitor session. */
    public static function incrementViews(int $productId): void
    {
        $key = 'viewed_product_' . $productId;
        if (!empty($_SESSION[$key])) {
            return;
        }
        $_SESSION[$key] = true;
        Database::connection()->prepare('UPDATE products SET views_count = views_count + 1 WHERE id = :id')->execute([':id' => $productId]);
    }

    // ---------------------------------------------------------------------------------------
    // A provider's own shelf
    // ---------------------------------------------------------------------------------------

    /**
     * Everything a provider has listed, any status, newest first (with cover photo and enquiry count).
     * Pass $publicOnly to get just what visitors can see.
     */
    public static function forProvider(int $providerId, bool $publicOnly = false): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, pc.name AS category_name,
                    ' . self::COVER_SQL . ' AS cover_path,
                    (SELECT COUNT(*) FROM inquiries q WHERE q.product_id = p.id) AS enquiry_count
             FROM products p
             INNER JOIN product_categories pc ON pc.id = p.category_id
             WHERE p.provider_id = :pid' . ($publicOnly ? " AND p.status = 'active'" : '') . '
             ORDER BY p.id DESC'
        );
        $stmt->execute([':pid' => $providerId]);
        return $stmt->fetchAll();
    }

    public static function countForProvider(int $providerId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM products WHERE provider_id = :pid');
        $stmt->execute([':pid' => $providerId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array $d category_id, name, description, price_ugx, price_unit, item_condition, stock_status
     */
    public static function create(int $providerId, array $d): int
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');

        $pdo->prepare(
            'INSERT INTO products (provider_id, category_id, name, slug, description, price_ugx, price_unit, item_condition, stock_status, status, views_count, created_at, updated_at)
             VALUES (:pid, :cid, :name, :slug, :desc, :price, :unit, :cond, :stock, :status, 0, :created, :updated)'
        )->execute([
            ':pid'     => $providerId,
            ':cid'     => (int) $d['category_id'],
            ':name'    => trim((string) $d['name']),
            ':slug'    => self::uniqueSlug((string) $d['name']),
            ':desc'    => trim((string) ($d['description'] ?? '')),
            ':price'   => max(0, (int) ($d['price_ugx'] ?? 0)),
            ':unit'    => (string) ($d['price_unit'] ?? 'each'),
            ':cond'    => (string) ($d['item_condition'] ?? 'new'),
            ':stock'   => (string) ($d['stock_status'] ?? 'in_stock'),
            ':status'  => self::ACTIVE,
            ':created' => $now,
            ':updated' => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, int $providerId, array $d): void
    {
        Database::connection()->prepare(
            'UPDATE products SET
                category_id = :cid, name = :name, description = :desc, price_ugx = :price, price_unit = :unit,
                item_condition = :cond, stock_status = :stock, updated_at = :updated
             WHERE id = :id AND provider_id = :pid'
        )->execute([
            ':cid'     => (int) $d['category_id'],
            ':name'    => trim((string) $d['name']),
            ':desc'    => trim((string) ($d['description'] ?? '')),
            ':price'   => max(0, (int) ($d['price_ugx'] ?? 0)),
            ':unit'    => (string) ($d['price_unit'] ?? 'each'),
            ':cond'    => (string) ($d['item_condition'] ?? 'new'),
            ':stock'   => (string) ($d['stock_status'] ?? 'in_stock'),
            ':updated' => date('Y-m-d H:i:s'),
            ':id'      => $id,
            ':pid'     => $providerId,
        ]);
    }

    /**
     * Pause or re-list one of your own products. A product an administrator suspended cannot be
     * re-listed by its owner, so this never touches one.
     */
    public static function setOwnerStatus(int $id, int $providerId, string $status): bool
    {
        if (!in_array($status, [self::ACTIVE, self::PAUSED], true)) {
            return false;
        }
        $stmt = Database::connection()->prepare(
            "UPDATE products SET status = :status, updated_at = :updated WHERE id = :id AND provider_id = :pid AND status <> 'suspended'"
        );
        $stmt->execute([':status' => $status, ':updated' => date('Y-m-d H:i:s'), ':id' => $id, ':pid' => $providerId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a product. Enquiries already received keep the product's name (inquiries.product_name),
     * and lose only the link. Returns the photo paths so the caller can delete the files.
     *
     * @return list<string>
     */
    public static function delete(int $id, int $providerId): array
    {
        $product = self::findOwned($id, $providerId);
        if ($product === null) {
            return [];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE inquiries SET product_id = NULL WHERE product_id = :id')->execute([':id' => $id]);
            $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute([':id' => $id]);
            $pdo->prepare('DELETE FROM products WHERE id = :id AND provider_id = :pid')->execute([':id' => $id, ':pid' => $providerId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return array_map(static fn(array $img): string => (string) $img['image_path'], $product['images']);
    }

    public static function addImage(int $productId, string $path): void
    {
        $pdo  = Database::connection();
        $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = :pid');
        $next->execute([':pid' => $productId]);

        $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (:pid, :path, :sort)')
            ->execute([':pid' => $productId, ':path' => $path, ':sort' => (int) $next->fetchColumn()]);
    }

    /** Remove one photo from one of your products. Returns its path, or null if it was not found. */
    public static function deleteImage(int $imageId, int $productId): ?string
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare('SELECT image_path FROM product_images WHERE id = :id AND product_id = :pid LIMIT 1');
        $stmt->execute([':id' => $imageId, ':pid' => $productId]);
        $path = $stmt->fetchColumn();
        if ($path === false) {
            return null;
        }
        $pdo->prepare('DELETE FROM product_images WHERE id = :id AND product_id = :pid')->execute([':id' => $imageId, ':pid' => $productId]);
        return (string) $path;
    }

    private static function uniqueSlug(string $name): string
    {
        $base = substr(slugify($name), 0, 80);
        $base = rtrim($base, '-') !== '' ? rtrim($base, '-') : 'product-' . bin2hex(random_bytes(3));
        $slug = $base;
        $n    = 2;

        $stmt = Database::connection()->prepare('SELECT 1 FROM products WHERE slug = :slug LIMIT 1');
        while (true) {
            $stmt->execute([':slug' => $slug]);
            if (!$stmt->fetchColumn()) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    // ---------------------------------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------------------------------

    /** @param array $f q, status, category (slug) */
    public static function adminSearch(array $f, int $limit, int $offset): array
    {
        $params = [];
        $where  = self::adminWhere($f, $params);

        $stmt = Database::connection()->prepare(
            'SELECT p.*, pc.name AS category_name,
                    pr.business_name, pr.slug AS provider_slug, pr.status AS provider_status,
                    u.full_name AS owner_name, u.email AS owner_email,
                    ' . self::COVER_SQL . ' AS cover_path,
                    (SELECT COUNT(*) FROM inquiries q WHERE q.product_id = p.id) AS enquiry_count
             FROM products p
             INNER JOIN providers pr ON pr.id = p.provider_id
             INNER JOIN users u ON u.id = pr.user_id
             INNER JOIN product_categories pc ON pc.id = p.category_id
             WHERE ' . $where . '
             ORDER BY p.id DESC
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
             FROM products p
             INNER JOIN providers pr ON pr.id = p.provider_id
             INNER JOIN users u ON u.id = pr.user_id
             INNER JOIN product_categories pc ON pc.id = p.category_id
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
            $where[] = "(p.name LIKE :q1 ESCAPE '!' OR pr.business_name LIKE :q2 ESCAPE '!' OR u.email LIKE :q3 ESCAPE '!')";
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }

        $status = (string) ($f['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $where[]           = 'p.status = :status';
            $params[':status'] = $status;
        }

        if (!empty($f['category'])) {
            $where[]             = 'pc.slug = :category';
            $params[':category'] = (string) $f['category'];
        }

        return implode(' AND ', $where);
    }

    /** A product plus its owner's contact details, for an admin action and the email that follows. */
    public static function adminFind(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, pr.business_name, pr.slug AS provider_slug,
                    u.full_name AS owner_name, u.email AS owner_email
             FROM products p
             INNER JOIN providers pr ON pr.id = p.provider_id
             INNER JOIN users u ON u.id = pr.user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Set the status explicitly (never "flip"): active, paused or suspended. */
    public static function adminSetStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        Database::connection()->prepare('UPDATE products SET status = :status, updated_at = :updated WHERE id = :id')
            ->execute([':status' => $status, ':updated' => date('Y-m-d H:i:s'), ':id' => $id]);
    }
}
