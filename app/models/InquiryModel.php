<?php
defined('KOPII_APP') or exit('Direct access denied.');

class InquiryModel
{
    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO inquiries (
                provider_id, client_user_id, package_id, client_name, client_email, client_phone,
                event_date, event_location, budget_ugx, project_details, status, created_at
            ) VALUES (
                :pid, :cuid, :pkgid, :cname, :cemail, :cphone,
                :edate, :eloc, :budget, :details, :status, :created
            )'
        );
        $budget = !empty($data['budget_ugx']) ? max(0, (int) $data['budget_ugx']) : null;
        $pkgId  = !empty($data['package_id']) ? (int) $data['package_id'] : null;

        $stmt->execute([
            ':pid'     => (int) $data['provider_id'],
            ':cuid'    => !empty($data['client_user_id']) ? (int) $data['client_user_id'] : null,
            ':pkgid'   => $pkgId,
            ':cname'   => trim((string) $data['client_name']),
            ':cemail'  => strtolower(trim((string) $data['client_email'])),
            ':cphone'  => trim((string) $data['client_phone']),
            ':edate'   => trim((string) ($data['event_date'] ?? '')),
            ':eloc'    => trim((string) ($data['event_location'] ?? '')),
            ':budget'  => $budget,
            ':details' => trim((string) $data['project_details']),
            ':status'  => 'new',
            ':created' => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function forProvider(int $providerId, ?string $statusFilter = null): array
    {
        $pdo = Database::connection();
        $params = [':pid' => $providerId];
        $statusSql = '';
        if ($statusFilter && in_array($statusFilter, ['new', 'contacted', 'booked', 'archived'], true)) {
            $statusSql = ' AND i.status = :status';
            $params[':status'] = $statusFilter;
        }

        $sql = "
            SELECT i.*, sp.name AS package_name, sp.price_ugx AS package_price
            FROM inquiries i
            LEFT JOIN service_packages sp ON sp.id = i.package_id
            WHERE i.provider_id = :pid {$statusSql}
            ORDER BY CASE WHEN i.status = 'new' THEN 0 ELSE 1 END ASC, i.id DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countsForProvider(int $providerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT status, COUNT(*) AS cnt FROM inquiries WHERE provider_id = :pid GROUP BY status');
        $stmt->execute([':pid' => $providerId]);
        $counts = ['all' => 0, 'new' => 0, 'contacted' => 0, 'booked' => 0, 'archived' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $st = (string) $row['status'];
            $c  = (int) $row['cnt'];
            if (isset($counts[$st])) {
                $counts[$st] = $c;
            }
            $counts['all'] += $c;
        }
        return $counts;
    }

    /**
     * Requests a signed-in client has sent. They are matched by account, and by email address only
     * once that address has been confirmed: otherwise anyone could register somebody else's address
     * and read that person's requests.
     */
    public static function forClient(int $userId, string $email, bool $emailVerified = false): array
    {
        $pdo = Database::connection();

        $where  = 'i.client_user_id = :uid';
        $params = [':uid' => $userId];
        if ($emailVerified && $email !== '') {
            $where .= ' OR LOWER(i.client_email) = LOWER(:email)';
            $params[':email'] = $email;
        }

        $sql = "
            SELECT i.*, p.business_name, p.slug AS provider_slug, p.whatsapp AS provider_whatsapp, sp.name AS package_name
            FROM inquiries i
            INNER JOIN providers p ON p.id = i.provider_id
            LEFT JOIN service_packages sp ON sp.id = i.package_id
            WHERE ({$where})
            ORDER BY i.id DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Has this person asked this provider for a quote through Kopii? (Used to gate instant reviews.) */
    public static function userHasContacted(int $providerId, int $userId, string $email, bool $emailVerified): bool
    {
        $where  = 'client_user_id = :uid';
        $params = [':pid' => $providerId, ':uid' => $userId];
        if ($emailVerified && $email !== '') {
            $where .= ' OR LOWER(client_email) = LOWER(:email)';
            $params[':email'] = $email;
        }

        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM inquiries WHERE provider_id = :pid AND ({$where}) LIMIT 1"
        );
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public static function updateStatus(int $inquiryId, int $providerId, string $status): void
    {
        if (!in_array($status, ['new', 'contacted', 'booked', 'archived'], true)) {
            return;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE inquiries SET status = :st WHERE id = :id AND provider_id = :pid');
        $stmt->execute([':st' => $status, ':id' => $inquiryId, ':pid' => $providerId]);
    }
}
