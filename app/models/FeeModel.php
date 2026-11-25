<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The commission rates an administrator can set, and the arithmetic that goes with them.
 *
 * Kopii does NOT charge commission yet: there is no booking or checkout flow, and money never passes
 * through the site. These rates are stored now so the numbers are agreed in one place and the
 * booking flow can read them later through commissionFor(). Nothing on the public site quotes them.
 */
final class FeeModel
{
    public const SERVICE = 'service';
    public const PRODUCT = 'product';

    /** The launch default for both, in percent. Seeder::seedPlansAndFees() writes the same number. */
    public const DEFAULT_RATE = 10.0;
    public const MAX_RATE     = 30.0;

    private const KEYS = [
        self::SERVICE => 'service_commission_pct',
        self::PRODUCT => 'product_commission_pct',
    ];

    /** @return array{service: float, product: float} percent, e.g. 10.0 */
    public static function rates(): array
    {
        $rates = [self::SERVICE => self::DEFAULT_RATE, self::PRODUCT => self::DEFAULT_RATE];
        try {
            $stmt = Database::connection()->query('SELECT setting_key, setting_value FROM platform_settings');
            foreach ($stmt->fetchAll() as $row) {
                $kind = array_search((string) $row['setting_key'], self::KEYS, true);
                if ($kind !== false && is_numeric($row['setting_value'])) {
                    $rates[$kind] = self::clamp((float) $row['setting_value']);
                }
            }
        } catch (Throwable $e) {
            report_exception($e);
        }
        return $rates;
    }

    public static function rate(string $kind): float
    {
        return self::rates()[$kind] ?? self::DEFAULT_RATE;
    }

    /**
     * Read a percentage typed by an administrator. Accepts "10", "7.5" and "7,5"; returns null for
     * anything else or a value outside 0 to MAX_RATE, so the caller can say what is wrong.
     */
    public static function parseRate(string $input): ?float
    {
        $input = str_replace(',', '.', trim(str_replace('%', '', $input)));
        if ($input === '' || !preg_match('/^\d{1,2}(\.\d{1,2})?$/', $input)) {
            return null;
        }
        $value = (float) $input;
        return $value >= 0 && $value <= self::MAX_RATE ? round($value, 2) : null;
    }

    public static function saveRates(float $service, float $product): void
    {
        $pdo    = Database::connection();
        $now    = date('Y-m-d H:i:s');
        $values = [self::SERVICE => $service, self::PRODUCT => $product];

        foreach (self::KEYS as $kind => $key) {
            $text = rtrim(rtrim(number_format($values[$kind], 2, '.', ''), '0'), '.');
            $text = $text === '' ? '0' : $text;

            $has = $pdo->prepare('SELECT COUNT(*) FROM platform_settings WHERE setting_key = :k');
            $has->execute([':k' => $key]);
            if ((int) $has->fetchColumn() > 0) {
                $pdo->prepare('UPDATE platform_settings SET setting_value = :v, updated_at = :u WHERE setting_key = :k')
                    ->execute([':v' => $text, ':u' => $now, ':k' => $key]);
            } else {
                $pdo->prepare('INSERT INTO platform_settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :u)')
                    ->execute([':k' => $key, ':v' => $text, ':u' => $now]);
            }
        }
    }

    /**
     * What Kopii's cut and the seller's payout would be on an amount, in whole UGX.
     *
     * @return array{rate: float, commission: int, payout: int}
     */
    public static function commissionFor(int $amountUgx, string $kind): array
    {
        $amount     = max(0, $amountUgx);
        $rate       = self::rate($kind === self::PRODUCT ? self::PRODUCT : self::SERVICE);
        $commission = (int) round($amount * $rate / 100);
        return ['rate' => $rate, 'commission' => $commission, 'payout' => $amount - $commission];
    }

    /** "10%" or "7.5%": no trailing zeros. */
    public static function label(float $rate): string
    {
        $text = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
        return ($text === '' ? '0' : $text) . '%';
    }

    private static function clamp(float $rate): float
    {
        return max(0.0, min(self::MAX_RATE, $rate));
    }
}
