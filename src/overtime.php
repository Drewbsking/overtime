<?php

class Overtime
{
    public static function create(int $userId, string $workDate, float $hours, string $reason, string $workType): int
    {
        self::validateHours($hours);
        self::validateReason($reason);
        self::validateWorkType($workType);

        $stmt = DB::conn()->prepare('INSERT INTO overtime_requests (user_id, work_date, hours, reason, work_type, status, created_at, updated_at) VALUES (:user_id, :work_date, :hours, :reason, :work_type, :status, :created_at, :updated_at)');
        $stmt->execute([
            'user_id' => $userId,
            'work_date' => $workDate,
            'hours' => $hours,
            'reason' => $reason,
            'work_type' => $workType,
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
        $sql = 'SELECT r.*, COALESCE(u.full_name, u.username) AS requester_name, u.username AS requester_username
                FROM overtime_requests r
                JOIN users u ON r.user_id = u.id
                WHERE r.status = "pending"
                ORDER BY r.created_at ASC';
        return DB::conn()->query($sql)->fetchAll();
    }

    public static function forUser(int $userId): array
    {
        $sql = 'SELECT r.*,
                       COALESCE(u.full_name, u.username) AS requester_name,
                       u.username AS requester_username,
                       COALESCE(a.full_name, a.username) AS approver_name,
                       a.username AS approver_username
                FROM overtime_requests r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN users a ON r.approver_id = a.id
                WHERE r.user_id = :user_id
                ORDER BY r.created_at DESC';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        $sql = 'SELECT r.*,
                       COALESCE(u.full_name, u.username) AS requester_name,
                       u.username AS requester_username,
                       COALESCE(a.full_name, a.username) AS approver_name,
                       a.username AS approver_username
                FROM overtime_requests r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN users a ON r.approver_id = a.id
                ORDER BY r.created_at DESC';
        return DB::conn()->query($sql)->fetchAll();
    }

    public static function approve(int $requestId, int $approverId, ?string $decisionNote = null): void
    {
        $note = self::normalizeDecisionNote($decisionNote, false);
        self::updateStatus($requestId, $approverId, 'approved', $note);
    }

    public static function deny(int $requestId, int $approverId, string $decisionNote): void
    {
        $note = self::normalizeDecisionNote($decisionNote, true);
        self::updateStatus($requestId, $approverId, 'denied', $note);
    }

    private static function updateStatus(int $requestId, int $approverId, string $status, ?string $decisionNote): void
    {
        $allowed = ['approved', 'denied'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid status.');
        }

        $stmt = DB::conn()->prepare('UPDATE overtime_requests SET status = :status, approver_id = :approver_id, denial_reason = :decision_note, decided_at = :decided_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'approver_id' => $approverId,
            'decision_note' => $decisionNote,
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

    public static function notificationRecipients(): array
    {
        $stmt = DB::conn()->query('SELECT email, COALESCE(full_name, username) AS name FROM users WHERE is_active = 1 AND notify_on_request = 1 AND role IN ("admin", "approver")');
        $rows = $stmt->fetchAll();

        $recipients = [];
        foreach ($rows as $row) {
            $email = trim($row['email'] ?? '');
            if ($email === '') {
                continue;
            }
            $name = trim($row['name'] ?? '') ?: $email;
            $recipients[$email] = $name;
        }

        return $recipients;
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

    private static function validateWorkType(string $workType): void
    {
        $allowed = ['office', 'field'];
        if (!in_array($workType, $allowed, true)) {
            throw new InvalidArgumentException('Invalid work type selected.');
        }
    }

    private static function normalizeDecisionNote(?string $decisionNote, bool $requireNote): ?string
    {
        if ($decisionNote === null) {
            $decisionNote = '';
        }

        $trimmed = trim($decisionNote);

        if ($trimmed === '') {
            if ($requireNote) {
                throw new InvalidArgumentException('Decision note required for this action.');
            }
            return null;
        }

        $length = strlen($trimmed);
        if ($length < 3 || $length > 1000) {
            throw new InvalidArgumentException('Decision note length invalid.');
        }

        return $trimmed;
    }
}
