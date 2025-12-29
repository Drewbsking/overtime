<?php

class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function isAdmin(): bool
    {
        return self::check() && ($_SESSION['user']['role'] ?? '') === 'admin';
    }

    public static function isApprover(): bool
    {
        if (!self::check()) {
            return false;
        }
        $role = $_SESSION['user']['role'] ?? 'user';
        return $role === 'approver' || $role === 'admin';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login.php');
        }
        if (self::mustReset() && basename($_SERVER['SCRIPT_NAME']) !== 'password-reset.php') {
            redirect('/password-reset.php?first=1');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $role = $_SESSION['user']['role'] ?? '';
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $sql = 'SELECT * FROM users WHERE username = :username LIMIT 1';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !$user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'] ?? '',
            'email' => $user['email'],
            'role' => $user['role'],
            'must_reset' => (bool)$user['must_reset'],
        ];

        $update = DB::conn()->prepare('UPDATE users SET last_login_at = :now WHERE id = :id');
        $update->execute(['now' => now(), 'id' => $user['id']]);

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function mustReset(): bool
    {
        return self::check() && !empty($_SESSION['user']['must_reset']);
    }

    public static function refreshUser(int $userId): void
    {
        $stmt = DB::conn()->prepare('SELECT id, username, full_name, email, role, must_reset FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'] ?? '',
                'email' => $user['email'],
                'role' => $user['role'],
                'must_reset' => (bool)$user['must_reset'],
            ];
        }
    }

    public static function setPassword(int $userId, string $password, bool $mustReset = false): void
    {
        $hash = self::hashPassword($password);
        $stmt = DB::conn()->prepare('UPDATE users SET password_hash = :hash, must_reset = :mustReset, updated_at = :updatedAt WHERE id = :id');
        $stmt->execute([
            'hash' => $hash,
            'mustReset' => $mustReset ? 1 : 0,
            'updatedAt' => now(),
            'id' => $userId,
        ]);
    }

    public static function createUser(string $username, string $fullName, string $email, string $tempPassword, string $role = 'user'): int
    {
        $hash = self::hashPassword($tempPassword);
        $stmt = DB::conn()->prepare('INSERT INTO users (username, full_name, email, password_hash, role, is_active, must_reset, created_at, updated_at) VALUES (:username, :fullName, :email, :hash, :role, 1, 1, :createdAt, :updatedAt)');
        $stmt->execute([
            'username' => $username,
            'fullName' => $fullName,
            'email' => $email,
            'hash' => $hash,
            'role' => $role,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
        return (int)DB::conn()->lastInsertId();
    }

    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
