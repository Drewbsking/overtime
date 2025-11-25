<?php

class Overtime
{
    public static function create(int $userId, string $workDate, float $hours, string $reason): int
    {
        self::validateHours($hours);
        self::validateReason($reason);

        $stmt = DB::conn()->prepare('INSERT INTO overtime_requests (user_id, work_date, hours, reason, status, created_at, updated_at) VALUES (:user_id, :work_date, :hours, :reason, :status, :created_at, :updated_at)');
        $stmt->execute([
            'user_id' => $userId,
            'work_date' => $workDate,
            'hours' => $hours,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestId = (int)DB::conn()->lastInsertId();
        self::logEvent($requestId, $userId, 'submitted');
        return $requestId;
    }

    public static function pending(): array
    {
        $sql = 'SELECT r.*, u.username AS requester_name FROM overtime_requests r JOIN users u ON r.user_id = u.id WHERE r.status = "pending" ORDER BY r.created_at ASC';
        return DB::conn()->query($sql)->fetchAll();
    }

    public static function forUser(int $userId): array
    {
        $sql = 'SELECT r.*, a.username AS approver_name FROM overtime_requests r LEFT JOIN users a ON r.approver_id = a.id WHERE r.user_id = :user_id ORDER BY r.created_at DESC';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function approve(int $requestId, int $approverId): void
    {
        self::updateStatus($requestId, $approverId, 'approved');
    }

    public static function deny(int $requestId, int $approverId): void
    {
        self::updateStatus($requestId, $approverId, 'denied');
    }

    private static function updateStatus(int $requestId, int $approverId, string $status): void
    {
        $allowed = ['approved', 'denied'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid status.');
        }

        $stmt = DB::conn()->prepare('UPDATE overtime_requests SET status = :status, approver_id = :approver_id, decided_at = :decided_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'approver_id' => $approverId,
            'decided_at' => now(),
            'updated_at' => now(),
            'id' => $requestId,
        ]);

        self::logEvent($requestId, $approverId, $status);
    }

    public static function find(int $requestId): ?array
    {
        $stmt = DB::conn()->prepare('SELECT * FROM overtime_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        return $request ?: null;
    }

    public static function equalizationBoard(int $windowDays = 365): array
    {
        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->sub(new DateInterval('P' . $windowDays . 'D'))
            ->format('Y-m-d');

        $sql = '
            SELECT u.id, u.username, u.email,
                   COALESCE(SUM(r.hours), 0) AS total_hours,
                   MAX(r.decided_at) AS last_assigned_at
            FROM users u
            LEFT JOIN overtime_requests r
                ON u.id = r.user_id
                AND r.status = "approved"
                AND r.decided_at >= :since
            WHERE u.is_active = 1
            GROUP BY u.id, u.username, u.email
            ORDER BY total_hours ASC, (last_assigned_at IS NULL) DESC, last_assigned_at ASC, u.username ASC';

        $stmt = DB::conn()->prepare($sql);
        $stmt->execute(['since' => $since]);
        return $stmt->fetchAll();
    }

    private static function logEvent(int $requestId, int $actorId, string $eventType): void
    {
        $stmt = DB::conn()->prepare('INSERT INTO request_events (request_id, actor_id, event_type, event_at) VALUES (:request_id, :actor_id, :event_type, :event_at)');
        $stmt->execute([
            'request_id' => $requestId,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'event_at' => now(),
        ]);
    }

    private static function validateHours(float $hours): void
    {
        if ($hours <= 0 || $hours > 24) {
            throw new InvalidArgumentException('Hours must be between 0 and 24.');
        }
    }

    private static function validateReason(string $reason): void
    {
        if (strlen($reason) < 3 || strlen($reason) > 1000) {
            throw new InvalidArgumentException('Reason length invalid.');
        }
    }
}
