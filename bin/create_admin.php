<?php
if (PHP_SAPI !== 'cli') {
    exit("Run from CLI only.\n");
}

require_once __DIR__ . '/../bootstrap.php';

$username = $argv[1] ?? null;
$email = $argv[2] ?? null;

if (!$username || !$email) {
    exit("Usage: php bin/create_admin.php <username> <email>\n");
}

$tempPassword = bin2hex(random_bytes(6));
$id = Auth::createUser($username, $email, $tempPassword, 'admin');
echo "Admin user created (ID {$id}). Temp password: {$tempPassword}\n";
