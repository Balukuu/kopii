<?php
defined('KOPII_APP') or exit('Direct access denied.');

class ProviderModel
{
    public static function search(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $pdo = Database::connection();
        $where = ["p.status = 'active'"];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . strtolower(trim((string) $filters['q'])) . '%';
            $where[] = '(LOWER(p.business_name) LIKE :q1 OR LOWER(p.headline) LIKE :q2 OR LOWER(p.specialties) LIKE :q3 OR LOWER(p.bio) LIKE :q4 OR LOWER(c.name) LIKE :q5 OR LOWER(p.city) LIKE :q6)';
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
            $params[':q5'] = $q;
            $params[':q6'] = $q;
        }

        if (!empty($filters['category'])) {
            $where[] = 'c.slug = :category';
            $params[':category'] = (string) $filters['category'];
        }

        if (!empty($filters['city'])) {
            $where[] = 'LOWER(p.city) = LOWER(:city)';
            $params[':city'] = trim((string) $filters['city']);
        }

        self::addAreaFilter($filters, $where, $params);

        if (!empty($filters['type']) && in_array($filters['type'], ['freelancer', 'business'], true)) {
            $where[] = 'p.provider_type = :ptype';
            $params[':ptype'] = $filters['type'];
        }

        if (!empty($filters['max_price']) && (int) $filters['max_price'] > 0) {
            $where[] = 'p.starting_price_ugx <= :max_price';
            $params[':max_price'] = (int) $filters['max_price'];
        }

        if (!empty($filters['verified'])) {
            $where[] = 'p.is_verified = 1';
        }

        if (!empty($filters['featured'])) {
            $where[] = self::featuredSql();
        }

        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $cleanIds = array_values(array_filter(array_map('intval', $filters['ids']), static fn($v) => $v > 0));
            if (!empty($cleanIds)) {
                $placeholders = [];
                foreach ($cleanIds as $idx => $cid) {
                    $ph = ':pid_' . $idx;
                    $placeholders[] = $ph;
                    $params[$ph] = $cid;
                }
                $where[] = 'p.id IN (' . implode(',', $placeholders) . ')';
            }
        }

        $whereSql = implode(' AND ', $where);

        $sort = (string) ($filters['sort'] ?? 'featured');
        $featuredFirst = 'CASE WHEN ' . self::featuredSql() . ' THEN 1 ELSE 0 END DESC';
        $orderBy = match ($sort) {
            'rating'    => 'avg_rating DESC, review_count DESC, p.is_verified DESC',
            'price_asc' => 'CASE WHEN p.starting_price_ugx = 0 THEN 1 ELSE 0 END ASC, p.starting_price_ugx ASC',
            'work'      => 'portfolio_count DESC, ' . $featuredFirst,
            'newest'    => 'p.id DESC',
            default     => $featuredFirst . ', p.is_verified DESC, avg_rating DESC, portfolio_count DESC, p.id DESC',
        };

        $sql = "
            SELECT
                p.*,
                u.full_name AS owner_name,
                c.name AS category_name,
                c.slug AS category_slug,
                c.icon AS category_icon,
                COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
                COUNT(DISTINCT r.id) AS review_count,
                COUNT(DISTINCT pi.id) AS portfolio_count
            FROM providers p
            INNER JOIN users u ON u.id = p.user_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN reviews r ON r.provider_id = p.id AND r.status = 'published'
            LEFT JOIN portfolio_items pi ON pi.provider_id = p.id
            WHERE {$whereSql}
            GROUP BY p.id, u.full_name, c.name, c.slug, c.icon
            ORDER BY {$orderBy}
            LIMIT " . max(1, $limit) . " OFFSET " . max(0, $offset);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $providers = $stmt->fetchAll();

        return self::attachPortfolioPreviews($providers);
    }

    public static function countSearch(array $filters = []): int
    {
        $pdo = Database::connection();
        $where = ["p.status = 'active'"];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . strtolower(trim((string) $filters['q'])) . '%';
            $where[] = '(LOWER(p.business_name) LIKE :q1 OR LOWER(p.headline) LIKE :q2 OR LOWER(p.specialties) LIKE :q3 OR LOWER(p.bio) LIKE :q4 OR LOWER(c.name) LIKE :q5 OR LOWER(p.city) LIKE :q6)';
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
            $params[':q5'] = $q;
            $params[':q6'] = $q;
        }
        if (!empty($filters['category'])) {
            $where[] = 'c.slug = :category';
            $params[':category'] = (string) $filters['category'];
        }
        if (!empty($filters['city'])) {
            $where[] = 'LOWER(p.city) = LOWER(:city)';
            $params[':city'] = trim((string) $filters['city']);
        }
        self::addAreaFilter($filters, $where, $params);
        if (!empty($filters['type']) && in_array($filters['type'], ['freelancer', 'business'], true)) {
            $where[] = 'p.provider_type = :ptype';
            $params[':ptype'] = $filters['type'];
        }
        if (!empty($filters['max_price']) && (int) $filters['max_price'] > 0) {
            $where[] = 'p.starting_price_ugx <= :max_price';
            $params[':max_price'] = (int) $filters['max_price'];
        }
        if (!empty($filters['verified'])) {
            $where[] = 'p.is_verified = 1';
        }

        $sql = 'SELECT COUNT(DISTINCT p.id) FROM providers p INNER JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * "Near me" in Uganda means a neighbourhood or a town, more than a whole city. An area typed by a
     * visitor matches where the provider is based (neighbourhood or city) and the places they say they serve.
     */
    private static function addAreaFilter(array $filters, array &$where, array &$params): void
    {
        $area = trim((string) ($filters['area'] ?? ''));
        if ($area === '') {
            return;
        }
        $like    = '%' . like_escape(strtolower($area)) . '%';
        $where[] = "(LOWER(p.neighborhood) LIKE :area1 ESCAPE '!' OR LOWER(p.service_areas) LIKE :area2 ESCAPE '!' OR LOWER(p.city) LIKE :area3 ESCAPE '!')";
        $params[':area1'] = $params[':area2'] = $params[':area3'] = $like;
    }

    private static function attachPortfolioPreviews(array $providers): array
    {
        if (empty($providers)) {
            return [];
        }
        $pdo = Database::connection();
        $ids = array_map(static fn($p) => (int) $p['id'], $providers);
        $in  = implode(',', $ids);

        $stmt = $pdo->query("SELECT * FROM portfolio_items WHERE provider_id IN ({$in}) ORDER BY is_featured DESC, sort_order ASC, id DESC");
        $grouped = [];
        foreach ($stmt->fetchAll() as $item) {
            $pid = (int) $item['provider_id'];
            if (!isset($grouped[$pid]) || count($grouped[$pid]) < 3) {
                $grouped[$pid][] = $item;
            }
        }

        foreach ($providers as &$p) {
            $p['portfolio_preview'] = $grouped[(int) $p['id']] ?? [];
        }
        unset($p);
        return $providers;
    }

    public static function portfolioShowcaseFeed(array $filters = [], int $limit = 18): array
    {
        $pdo = Database::connection();
        $where = ["p.status = 'active'"];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . strtolower(trim((string) $filters['q'])) . '%';
            $where[] = '(LOWER(pi.title) LIKE :q1 OR LOWER(pi.project_tag) LIKE :q2 OR LOWER(pi.description) LIKE :q3 OR LOWER(p.business_name) LIKE :q4)';
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }
        if (!empty($filters['category'])) {
            $where[] = 'c.slug = :category';
            $params[':category'] = (string) $filters['category'];
        }
        if (!empty($filters['city'])) {
            $where[] = 'LOWER(p.city) = LOWER(:city)';
            $params[':city'] = trim((string) $filters['city']);
        }
        self::addAreaFilter($filters, $where, $params);

        $sql = "
            SELECT
                pi.*,
                p.business_name,
                p.slug AS provider_slug,
                p.city AS provider_city,
                p.provider_type,
                p.is_verified,
                p.starting_price_ugx,
                c.name AS category_name,
                c.slug AS category_slug
            FROM portfolio_items pi
            INNER JOIN providers p ON p.id = pi.provider_id
            INNER JOIN categories c ON c.id = p.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY pi.is_featured DESC, pi.id DESC
            LIMIT " . max(1, $limit);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::connection();
        $sql = "
            SELECT
                p.*,
                u.full_name AS owner_name,
                c.name AS category_name,
                c.slug AS category_slug,
                c.icon AS category_icon,
                COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
                COUNT(DISTINCT r.id) AS review_count
            FROM providers p
            INNER JOIN users u ON u.id = p.user_id
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN reviews r ON r.provider_id = p.id AND r.status = 'published'
            WHERE p.slug = :slug
            GROUP BY p.id, u.full_name, c.name, c.slug, c.icon
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $provider = $stmt->fetch();
        if (!$provider) {
            return null;
        }

        $provider['portfolio'] = self::getPortfolio((int) $provider['id']);
        $provider['packages']  = self::getPackages((int) $provider['id']);
        $provider['reviews']   = ReviewModel::forProvider((int) $provider['id']);

        return $provider;
    }

    public static function findByUserId(int $userId): ?array
    {
        $pdo = Database::connection();
        $sql = "
            SELECT
                p.*,
                c.name AS category_name,
                c.slug AS category_slug,
                COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
                COUNT(DISTINCT r.id) AS review_count
            FROM providers p
            INNER JOIN categories c ON c.id = p.category_id
            LEFT JOIN reviews r ON r.provider_id = p.id AND r.status = 'published'
            WHERE p.user_id = :uid
            GROUP BY p.id, c.name, c.slug
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function incrementViews(int $providerId): void
    {
        $key = 'viewed_provider_' . $providerId;
        if (!empty($_SESSION[$key])) {
            return;
        }
        $_SESSION[$key] = true;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE providers SET views_count = views_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $providerId]);
    }

    public static function getPortfolio(int $providerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM portfolio_items WHERE provider_id = :pid ORDER BY is_featured DESC, sort_order ASC, id DESC');
        $stmt->execute([':pid' => $providerId]);
        return $stmt->fetchAll();
    }

    public static function getPackages(int $providerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM service_packages WHERE provider_id = :pid ORDER BY sort_order ASC, price_ugx ASC, id ASC');
        $stmt->execute([':pid' => $providerId]);
        return $stmt->fetchAll();
    }

    public static function generateUniqueSlug(string $businessName, ?int $excludeId = null): string
    {
        $pdo = Database::connection();
        $base = slugify($businessName);
        $slug = $base;
        $counter = 2;

        while (true) {
            if ($excludeId !== null) {
                $stmt = $pdo->prepare('SELECT id FROM providers WHERE slug = :slug AND id != :id LIMIT 1');
                $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
            } else {
                $stmt = $pdo->prepare('SELECT id FROM providers WHERE slug = :slug LIMIT 1');
                $stmt->execute([':slug' => $slug]);
            }
            if (!$stmt->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . $counter;
            $counter++;
        }
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $slug = self::generateUniqueSlug((string) $data['business_name']);

        $stmt = $pdo->prepare(
            'INSERT INTO providers (
                user_id, category_id, business_name, slug, provider_type, headline, bio,
                city, neighborhood, service_areas, phone, whatsapp, public_email, website, instagram,
                starting_price_ugx, years_experience, response_time, specialties,
                avatar_path, cover_path, is_verified, is_featured, is_available, status,
                views_count, created_at, updated_at
            ) VALUES (
                :uid, :cid, :bname, :slug, :ptype, :headline, :bio,
                :city, :neighborhood, :sareas, :phone, :whatsapp, :pemail, :web, :ig,
                :price, :exp, :resp, :spec,
                :avatar, :cover, 0, 0, 1, :status,
                0, :created, :updated
            )'
        );

        $stmt->execute([
            ':uid'          => (int) $data['user_id'],
            ':cid'          => (int) $data['category_id'],
            ':bname'        => trim((string) $data['business_name']),
            ':slug'         => $slug,
            ':ptype'        => in_array($data['provider_type'] ?? '', ['freelancer', 'business'], true) ? $data['provider_type'] : 'freelancer',
            ':headline'     => trim((string) $data['headline']),
            ':bio'          => trim((string) $data['bio']),
            ':city'         => trim((string) ($data['city'] ?? 'Kampala')),
            ':neighborhood' => trim((string) ($data['neighborhood'] ?? '')),
            ':sareas'       => trim((string) ($data['service_areas'] ?? '')),
            ':phone'        => trim((string) $data['phone']),
            ':whatsapp'     => trim((string) ($data['whatsapp'] ?: $data['phone'])),
            ':pemail'       => trim((string) ($data['public_email'] ?? '')),
            ':web'          => trim((string) ($data['website'] ?? '')),
            ':ig'           => trim((string) ($data['instagram'] ?? '')),
            ':price'        => max(0, (int) ($data['starting_price_ugx'] ?? 0)),
            ':exp'          => max(0, (int) ($data['years_experience'] ?? 1)),
            ':resp'         => trim((string) ($data['response_time'] ?? 'Within 2 hours')),
            ':spec'         => trim((string) ($data['specialties'] ?? '')),
            ':avatar'       => $data['avatar_path'] ?? null,
            ':cover'        => $data['cover_path'] ?? null,
            ':status'       => 'active',
            ':created'      => $now,
            ':updated'      => $now,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $providerId, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE providers SET
                category_id = :cid,
                business_name = :bname,
                provider_type = :ptype,
                headline = :headline,
                bio = :bio,
                city = :city,
                neighborhood = :neighborhood,
                service_areas = :sareas,
                phone = :phone,
                whatsapp = :whatsapp,
                public_email = :pemail,
                website = :web,
                instagram = :ig,
                starting_price_ugx = :price,
                years_experience = :exp,
                response_time = :resp,
                specialties = :spec,
                avatar_path = :avatar,
                cover_path = :cover,
                is_available = :avail,
                updated_at = :updated
             WHERE id = :id'
        );

        $stmt->execute([
            ':cid'          => (int) $data['category_id'],
            ':bname'        => trim((string) $data['business_name']),
            ':ptype'        => in_array($data['provider_type'] ?? '', ['freelancer', 'business'], true) ? $data['provider_type'] : 'freelancer',
            ':headline'     => trim((string) $data['headline']),
            ':bio'          => trim((string) $data['bio']),
            ':city'         => trim((string) $data['city']),
            ':neighborhood' => trim((string) ($data['neighborhood'] ?? '')),
            ':sareas'       => trim((string) ($data['service_areas'] ?? '')),
            ':phone'        => trim((string) $data['phone']),
            ':whatsapp'     => trim((string) ($data['whatsapp'] ?: $data['phone'])),
            ':pemail'       => trim((string) ($data['public_email'] ?? '')),
            ':web'          => trim((string) ($data['website'] ?? '')),
            ':ig'           => trim((string) ($data['instagram'] ?? '')),
            ':price'        => max(0, (int) ($data['starting_price_ugx'] ?? 0)),
            ':exp'          => max(0, (int) ($data['years_experience'] ?? 1)),
            ':resp'         => trim((string) ($data['response_time'] ?? 'Within 2 hours')),
            ':spec'         => trim((string) ($data['specialties'] ?? '')),
            ':avatar'       => $data['avatar_path'],
            ':cover'        => $data['cover_path'],
            ':avail'        => !empty($data['is_available']) ? 1 : 0,
            ':updated'      => date('Y-m-d H:i:s'),
            ':id'           => $providerId,
        ]);
    }

    public static function addPortfolioItem(int $providerId, array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO portfolio_items (provider_id, title, project_tag, description, image_path, client_location, is_featured, sort_order, created_at)
             VALUES (:pid, :title, :tag, :desc, :img, :loc, :feat, 0, :created)'
        );
        $stmt->execute([
            ':pid'     => $providerId,
            ':title'   => trim((string) $data['title']),
            ':tag'     => trim((string) ($data['project_tag'] ?? '')),
            ':desc'    => trim((string) ($data['description'] ?? '')),
            ':img'     => (string) $data['image_path'],
            ':loc'     => trim((string) ($data['client_location'] ?? '')),
            ':feat'    => !empty($data['is_featured']) ? 1 : 0,
            ':created' => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** Of these provider ids, the ones that exist and are active (only those can receive requests). */
    public static function activeIdsFrom(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT id FROM providers WHERE status = 'active' AND id IN ({$placeholders})");
        $stmt->execute($ids);
        $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // Keep the order the client chose.
        return array_values(array_intersect($ids, $found));
    }

    public static function findPortfolioItem(int $itemId, int $providerId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM portfolio_items WHERE id = :id AND provider_id = :pid LIMIT 1');
        $stmt->execute([':id' => $itemId, ':pid' => $providerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Who to email when this provider receives a lead: the account owner's login email
     * (not the public contact email, which may be a shared inbox or left blank).
     */
    public static function notificationTarget(int $providerId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.email AS email, u.full_name AS owner_name, p.business_name AS business_name
             FROM providers p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $providerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function deletePortfolioItem(int $itemId, int $providerId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM portfolio_items WHERE id = :id AND provider_id = :pid');
        $stmt->execute([':id' => $itemId, ':pid' => $providerId]);
        return $stmt->rowCount() > 0;
    }

    public static function addPackage(int $providerId, array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO service_packages (provider_id, name, price_ugx, price_unit, turnaround, description, deliverables, is_popular, sort_order)
             VALUES (:pid, :name, :price, :unit, :turn, :desc, :deliv, :pop, 0)'
        );
        $stmt->execute([
            ':pid'   => $providerId,
            ':name'  => trim((string) $data['name']),
            ':price' => max(0, (int) $data['price_ugx']),
            ':unit'  => trim((string) ($data['price_unit'] ?? 'per session')),
            ':turn'  => trim((string) ($data['turnaround'] ?? '')),
            ':desc'  => trim((string) ($data['description'] ?? '')),
            ':deliv' => trim((string) ($data['deliverables'] ?? '')),
            ':pop'   => !empty($data['is_popular']) ? 1 : 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** One of this provider's own packages (for editing), or null. */
    public static function findPackage(int $packageId, int $providerId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM service_packages WHERE id = :id AND provider_id = :pid LIMIT 1');
        $stmt->execute([':id' => $packageId, ':pid' => $providerId]);
        return $stmt->fetch() ?: null;
    }

    /** Change a package the provider owns. False when it does not exist or belongs to someone else. */
    public static function updatePackage(int $packageId, int $providerId, array $data): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE service_packages SET
                name = :name, price_ugx = :price, price_unit = :unit, turnaround = :turn,
                description = :desc, deliverables = :deliv, is_popular = :pop
             WHERE id = :id AND provider_id = :pid'
        );
        $stmt->execute([
            ':name'  => trim((string) $data['name']),
            ':price' => max(0, (int) $data['price_ugx']),
            ':unit'  => trim((string) ($data['price_unit'] ?? 'per session')),
            ':turn'  => trim((string) ($data['turnaround'] ?? '')),
            ':desc'  => trim((string) ($data['description'] ?? '')),
            ':deliv' => trim((string) ($data['deliverables'] ?? '')),
            ':pop'   => !empty($data['is_popular']) ? 1 : 0,
            ':id'    => $packageId,
            ':pid'   => $providerId,
        ]);
        // rowCount() is 0 on MySQL when nothing changed, so confirm the row exists instead.
        return self::findPackage($packageId, $providerId) !== null;
    }

    /** Change a portfolio item the provider owns. Pass image_path to swap the photo; leave it out to keep the current one. */
    public static function updatePortfolioItem(int $itemId, int $providerId, array $data): bool
    {
        $sql = 'UPDATE portfolio_items SET
                    title = :title, project_tag = :tag, description = :desc, client_location = :loc, is_featured = :feat'
            . (isset($data['image_path']) ? ', image_path = :img' : '')
            . ' WHERE id = :id AND provider_id = :pid';

        $params = [
            ':title' => trim((string) $data['title']),
            ':tag'   => trim((string) ($data['project_tag'] ?? '')),
            ':desc'  => trim((string) ($data['description'] ?? '')),
            ':loc'   => trim((string) ($data['client_location'] ?? '')),
            ':feat'  => !empty($data['is_featured']) ? 1 : 0,
            ':id'    => $itemId,
            ':pid'   => $providerId,
        ];
        if (isset($data['image_path'])) {
            $params[':img'] = (string) $data['image_path'];
        }

        Database::connection()->prepare($sql)->execute($params);
        return self::findPortfolioItem($itemId, $providerId) !== null;
    }

    /** Live listings for the sitemap: slug and when they last changed. */
    public static function sitemapRows(int $limit = 2000): array
    {
        return Database::connection()
            ->query("SELECT slug, updated_at FROM providers WHERE status = 'active' ORDER BY id ASC LIMIT " . max(1, $limit))
            ->fetchAll();
    }

    public static function deletePackage(int $packageId, int $providerId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM service_packages WHERE id = :id AND provider_id = :pid');
        $stmt->execute([':id' => $packageId, ':pid' => $providerId]);
        return $stmt->rowCount() > 0;
    }

    public static function marketplaceStats(): array
    {
        $pdo = Database::connection();
        return [
            'providers'  => (int) $pdo->query("SELECT COUNT(*) FROM providers WHERE status = 'active'")->fetchColumn(),
            'portfolios' => (int) $pdo->query("SELECT COUNT(*) FROM portfolio_items")->fetchColumn(),
            'cities'     => (int) $pdo->query("SELECT COUNT(DISTINCT city) FROM providers WHERE status = 'active'")->fetchColumn(),
            'reviews'    => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'published'")->fetchColumn(),
        ];
    }

    /**
     * Diagnostic talent matcher: scores & ranks active providers against a client brief.
     */
    public static function matchTalent(array $criteria = [], int $limit = 6): array
    {
        $all = self::search(['sort' => 'featured'], 48);
        if (empty($all)) {
            return [];
        }

        $targetCat   = strtolower(trim((string) ($criteria['category'] ?? '')));
        $targetCity  = strtolower(trim((string) ($criteria['city'] ?? '')));
        $targetType  = strtolower(trim((string) ($criteria['type'] ?? '')));
        $maxBudget   = (int) ($criteria['max_price'] ?? 0);
        $keywordsRaw = strtolower(trim((string) ($criteria['q'] ?? '')));
        $keywords    = array_values(array_filter(preg_split('/[\s,]+/', $keywordsRaw) ?: [], static fn($w) => strlen($w) >= 3));

        $scored = [];
        foreach ($all as $p) {
            $score = 52;
            $reasons = [];

            if ($targetCat !== '' && strtolower((string) $p['category_slug']) === $targetCat) {
                $score += 24;
                $reasons[] = 'Category Specialist';
            } elseif ($targetCat === '') {
                $score += 12;
            } else {
                // Keep strict category relevance when category is chosen if enough providers exist, otherwise lower score
                $score -= 18;
            }

            if (!empty($p['is_verified'])) {
                $score += 10;
                $reasons[] = 'Verified by Kopii';
            }

            if ($targetCity !== '') {
                if (strtolower((string) $p['city']) === $targetCity) {
                    $score += 9;
                    $reasons[] = 'Based in ' . $p['city'];
                } elseif (stripos((string) ($p['neighborhood'] ?? '') . ' ' . (string) ($p['bio'] ?? ''), 'nationwide') !== false) {
                    $score += 4;
                    $reasons[] = 'Serves Nationwide';
                }
            } else {
                $score += 5;
            }

            $startPrice = (int) ($p['starting_price_ugx'] ?? 0);
            if ($maxBudget > 0 && $startPrice > 0) {
                if ($startPrice <= $maxBudget) {
                    $score += 7;
                    $reasons[] = 'Fits UGX Budget';
                } elseif ($startPrice <= (int) ($maxBudget * 1.25)) {
                    $score += 2;
                } else {
                    $score -= 6;
                }
            } else {
                $score += 4;
            }

            if ($targetType !== '' && in_array($targetType, ['freelancer', 'business'], true)) {
                if ($p['provider_type'] === $targetType) {
                    $score += 4;
                }
            }

            if (!empty($keywords)) {
                $haystack = strtolower(
                    ($p['business_name'] ?? '') . ' ' .
                    ($p['headline'] ?? '') . ' ' .
                    ($p['specialties'] ?? '') . ' ' .
                    ($p['bio'] ?? '') . ' ' .
                    ($p['category_name'] ?? '')
                );
                $kwHits = 0;
                foreach ($keywords as $kw) {
                    if (str_contains($haystack, $kw)) {
                        $kwHits++;
                    }
                }
                if ($kwHits > 0) {
                    $score += min(8, $kwHits * 4);
                    $reasons[] = 'Skill Match';
                }
            }

            if ((int) ($p['portfolio_count'] ?? 0) >= 2) {
                $score += 2;
            }
            if ((float) ($p['avg_rating'] ?? 0) >= 4.5) {
                $score += 2;
            }

            $p['match_score']   = max(68, min(99, $score));
            $p['match_reasons'] = array_slice(array_unique($reasons), 0, 3);
            $p['_raw_score']    = $score;
            $scored[] = $p;
        }

        // Prefer exact category matches when any exist
        if ($targetCat !== '') {
            $exactCat = array_values(array_filter($scored, static fn($p) => strtolower((string) $p['category_slug']) === $targetCat));
            if (!empty($exactCat)) {
                $scored = $exactCat;
            }
        }

        usort($scored, static fn($a, $b) => $b['_raw_score'] <=> $a['_raw_score']);
        return array_slice($scored, 0, max(1, $limit));
    }

    // Admin actions set an explicit state instead of flipping the current one, so a stale page or a
    // double-click can never undo what the admin meant to do.

    /** Give or take away the Verified badge, recording who did it and when. */
    public static function adminSetVerified(int $providerId, bool $verified, int $adminUserId): void
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE providers SET is_verified = :v, verified_at = :at, verified_by = :by, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            ':v'  => $verified ? 1 : 0,
            ':at' => $verified ? $now : null,
            ':by' => $verified ? $adminUserId : null,
            ':u'  => $now,
            ':id' => $providerId,
        ]);
    }

    /**
     * Feature a listing (optionally only until a date, inclusive) or take it off Featured.
     * Featuring with no date has no end; un-featuring always clears the date.
     */
    public static function adminSetFeatured(int $providerId, bool $featured, ?string $until = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE providers SET is_featured = :f, featured_until = :until, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            ':f'     => $featured ? 1 : 0,
            ':until' => $featured ? $until : null,
            ':u'     => date('Y-m-d H:i:s'),
            ':id'    => $providerId,
        ]);
    }

    /**
     * SQL that is true for a listing that is Featured right now: switched on, and not past its end date.
     * The date is written straight into the SQL; it comes from date(), never from a visitor.
     */
    public static function featuredSql(string $alias = 'p'): string
    {
        return '(' . $alias . ".is_featured = 1 AND (" . $alias . ".featured_until IS NULL OR " . $alias . ".featured_until >= '" . date('Y-m-d') . "'))";
    }

    /** The same test for a row already loaded (an admin page, a card). */
    public static function isFeaturedNow(array $provider): bool
    {
        if (empty($provider['is_featured'])) {
            return false;
        }
        $until = trim((string) ($provider['featured_until'] ?? ''));
        return $until === '' || $until >= date('Y-m-d');
    }

    /**
     * Make a listing public ('active') or hide it ('paused'). A 'suspended' listing belongs to a
     * suspended account and is left alone: reinstate the account instead.
     */
    public static function adminSetStatus(int $providerId, string $status): void
    {
        if (!in_array($status, ['active', 'paused'], true)) {
            return;
        }
        $stmt = Database::connection()->prepare(
            "UPDATE providers SET status = :s, updated_at = :u WHERE id = :id AND status <> 'suspended'"
        );
        $stmt->execute([':s' => $status, ':u' => date('Y-m-d H:i:s'), ':id' => $providerId]);
    }
}
