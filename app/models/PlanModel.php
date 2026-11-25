<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Plans: what a listing is on, and for how long.
 *
 * There is always one default plan (Basic, free). Every listing is on it unless an administrator has
 * assigned another plan with an end date. Kopii does not take payment itself yet, so the flow is
 * manual: the business pays by Mobile Money, an administrator confirms it and assigns the plan here.
 * When the end date passes, the listing falls back to the default plan by itself; nothing is deleted.
 */
final class PlanModel
{
    public const MAX_PRICE    = 100000000; // UGX, a sanity cap against a typing slip
    public const MAX_MONTHS   = 36;
    public const MAX_PRODUCTS = 1000;
    public const MAX_NAME     = 60;
    public const MAX_DESC     = 255;

    /** @return list<array> every plan, the default one first, each with a `listings` count */
    public static function all(): array
    {
        $pdo   = Database::connection();
        $plans = $pdo->query(
            'SELECT * FROM plans ORDER BY is_default DESC, is_active DESC, sort_order ASC, price_ugx ASC, id ASC'
        )->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT plan_id, COUNT(*) AS n FROM providers
             WHERE plan_id IS NOT NULL AND (plan_expires_on IS NULL OR plan_expires_on >= :today)
             GROUP BY plan_id'
        );
        $stmt->execute([':today' => date('Y-m-d')]);
        $onPlan = [];
        foreach ($stmt->fetchAll() as $row) {
            $onPlan[(int) $row['plan_id']] = (int) $row['n'];
        }

        $totalProviders = (int) $pdo->query('SELECT COUNT(*) FROM providers')->fetchColumn();
        $onPaidTotal    = 0;
        foreach ($plans as $plan) {
            if (empty($plan['is_default'])) {
                $onPaidTotal += $onPlan[(int) $plan['id']] ?? 0;
            }
        }

        foreach ($plans as &$plan) {
            $plan['listings'] = !empty($plan['is_default'])
                ? max(0, $totalProviders - $onPaidTotal)
                : ($onPlan[(int) $plan['id']] ?? 0);
        }
        unset($plan);
        return $plans;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM plans WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The free plan everything falls back to. A built-in copy stands in if the table has none yet. */
    public static function defaultPlan(): array
    {
        try {
            $row = Database::connection()->query('SELECT * FROM plans WHERE is_default = 1 ORDER BY id ASC LIMIT 1')->fetch();
            if ($row) {
                return $row;
            }
        } catch (Throwable $e) {
            report_exception($e);
        }
        return [
            'id' => 0, 'name' => 'Basic', 'description' => '', 'price_ugx' => 0, 'period_months' => 0,
            'product_limit' => null, 'is_default' => 1, 'is_active' => 1, 'sort_order' => 0,
        ];
    }

    /**
     * The plan a listing is on right now: its assigned plan until the end date has passed, the default
     * plan otherwise. Adds `expires_on` (Y-m-d or null) and `lapsed_from` (the plan name, when an
     * assigned plan has just run out).
     */
    public static function effective(array $provider): array
    {
        $default = self::defaultPlan();
        $default['expires_on']  = null;
        $default['lapsed_from'] = null;

        $planId = (int) ($provider['plan_id'] ?? 0);
        if ($planId <= 0 || $planId === (int) $default['id']) {
            return $default;
        }
        $plan = self::find($planId);
        if ($plan === null) {
            return $default;
        }

        $expires = trim((string) ($provider['plan_expires_on'] ?? ''));
        if ($expires !== '' && $expires < date('Y-m-d')) {
            $default['expires_on']  = $expires;
            $default['lapsed_from'] = (string) $plan['name'];
            return $default;
        }

        $plan['expires_on']  = $expires !== '' ? $expires : null;
        $plan['lapsed_from'] = null;
        return $plan;
    }

    /** How many shop products this listing may have: its plan's limit, or the site-wide limit. */
    public static function productLimit(array $provider): int
    {
        $limit = (int) (self::effective($provider)['product_limit'] ?? 0);
        return $limit > 0 ? $limit : ProductModel::MAX_PER_PROVIDER;
    }

    /** Plans an administrator can choose for a listing: every active plan, plus the one it is on now. */
    public static function assignable(int $currentPlanId): array
    {
        return array_values(array_filter(
            self::all(),
            static fn(array $p): bool => !empty($p['is_active']) || (int) $p['id'] === $currentPlanId
        ));
    }

    /** @return array{0: array, 1: list<string>} cleaned values and problems written for the administrator */
    public static function clean(array $post, bool $isDefault): array
    {
        $name = trim((string) ($post['name'] ?? ''));
        $desc = trim((string) ($post['description'] ?? ''));
        $sort = max(0, min(999, (int) ($post['sort_order'] ?? 0)));

        $problems = [];
        if (text_length($name) < 2 || text_length($name) > self::MAX_NAME) {
            $problems[] = 'Enter a plan name of 2 to ' . self::MAX_NAME . ' characters.';
        }
        if (text_length($desc) > self::MAX_DESC) {
            $problems[] = 'Keep the description to ' . self::MAX_DESC . ' characters or fewer.';
        }

        $price  = 0;
        $months = 0;
        if (!$isDefault) {
            $priceText = str_replace([',', ' '], '', trim((string) ($post['price_ugx'] ?? '')));
            if ($priceText === '' || !ctype_digit($priceText) || (int) $priceText > self::MAX_PRICE) {
                $problems[] = 'Enter the price in whole UGX (0 is allowed, for a free founding-member plan).';
            } else {
                $price = (int) $priceText;
            }
            $monthsText = trim((string) ($post['period_months'] ?? ''));
            if (!ctype_digit($monthsText) || (int) $monthsText < 1 || (int) $monthsText > self::MAX_MONTHS) {
                $problems[] = 'Enter how many months the price covers, from 1 to ' . self::MAX_MONTHS . '.';
            } else {
                $months = (int) $monthsText;
            }
        }

        $limitText = trim((string) ($post['product_limit'] ?? ''));
        $limit     = null;
        if ($limitText !== '') {
            if (!ctype_digit($limitText) || (int) $limitText < 1 || (int) $limitText > self::MAX_PRODUCTS) {
                $problems[] = 'Enter a product limit from 1 to ' . self::MAX_PRODUCTS . ', or leave it empty to use the site-wide limit of ' . ProductModel::MAX_PER_PROVIDER . '.';
            } else {
                $limit = (int) $limitText;
            }
        }

        return [[
            'name'          => $name,
            'description'   => $desc,
            'price_ugx'     => $price,
            'period_months' => $months,
            'product_limit' => $limit,
            'sort_order'    => $sort,
            'is_active'     => !empty($post['is_active']) ? 1 : 0,
        ], $problems];
    }

    public static function nameTaken(string $name, int $exceptId = 0): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM plans WHERE LOWER(name) = LOWER(:n) AND id <> :id');
        $stmt->execute([':n' => $name, ':id' => $exceptId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(array $d): int
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO plans (name, description, price_ugx, period_months, product_limit, is_default, is_active, sort_order, created_at, updated_at)
             VALUES (:name, :desc, :price, :months, :plimit, 0, :active, :sort, :c1, :c2)'
        )->execute([
            ':name'   => $d['name'],
            ':desc'   => $d['description'],
            ':price'  => $d['price_ugx'],
            ':months' => $d['period_months'],
            ':plimit' => $d['product_limit'],
            ':active' => $d['is_active'],
            ':sort'   => $d['sort_order'],
            ':c1'     => $now,
            ':c2'     => $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** The default plan keeps its price at 0 and always stays active, whatever was submitted. */
    public static function update(int $id, array $d, bool $isDefault): void
    {
        Database::connection()->prepare(
            'UPDATE plans SET name = :name, description = :desc, price_ugx = :price, period_months = :months,
                    product_limit = :plimit, is_active = :active, sort_order = :sort, updated_at = :u
             WHERE id = :id'
        )->execute([
            ':name'   => $d['name'],
            ':desc'   => $d['description'],
            ':price'  => $isDefault ? 0 : $d['price_ugx'],
            ':months' => $isDefault ? 0 : $d['period_months'],
            ':plimit' => $d['product_limit'],
            ':active' => $isDefault ? 1 : $d['is_active'],
            ':sort'   => $d['sort_order'],
            ':u'      => date('Y-m-d H:i:s'),
            ':id'     => $id,
        ]);
    }

    /** Put a listing on a plan until a date (null = no end date). planId null returns it to the default plan. */
    public static function assign(int $providerId, ?int $planId, ?string $expiresOn): void
    {
        Database::connection()->prepare(
            'UPDATE providers SET plan_id = :plan, plan_expires_on = :ends, updated_at = :u WHERE id = :id'
        )->execute([
            ':plan' => $planId,
            ':ends' => $planId === null ? null : $expiresOn,
            ':u'    => date('Y-m-d H:i:s'),
            ':id'   => $providerId,
        ]);
    }

    /**
     * Listings that were put on a plan other than the default, soonest end date first (no end date last):
     * the list an administrator works through to chase renewals.
     */
    public static function listingsOnPaidPlans(int $limit = 200): array
    {
        $stmt = Database::connection()->query(
            'SELECT p.id, p.business_name, p.slug, p.plan_id, p.plan_expires_on, pl.name AS plan_name, pl.price_ugx, pl.period_months
             FROM providers p
             INNER JOIN plans pl ON pl.id = p.plan_id
             WHERE pl.is_default = 0
             ORDER BY (p.plan_expires_on IS NULL) ASC, p.plan_expires_on ASC, p.business_name ASC
             LIMIT ' . max(1, $limit)
        );
        return $stmt->fetchAll();
    }

    /**
     * The end date a plan's price covers if it starts today (Y-m-d). A month that is too short for the
     * day keeps the last day of that month, so 31 January + 1 month is 28 February, not 3 March.
     */
    public static function endDateFor(array $plan, ?DateTimeImmutable $from = null): string
    {
        $from   = $from ?? new DateTimeImmutable('today');
        $months = max(1, (int) ($plan['period_months'] ?? 1));
        $first  = $from->modify('first day of this month')->modify('+' . $months . ' months');
        $day    = min((int) $from->format('j'), (int) $first->format('t'));
        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->format('Y-m-d');
    }

    /** Read a date typed into a date field: Y-m-d only, a real calendar day. */
    public static function parseDate(string $input): ?string
    {
        $input = trim($input);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $input));
        return checkdate($m, $d, $y) ? $input : null;
    }
}
