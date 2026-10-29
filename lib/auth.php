<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function current_admin(): ?array {
    start_session();
    if (empty($_SESSION['admin_id'])) return null;
    $s = db()->prepare('SELECT a.id, a.company_id, a.name, a.email, c.name AS company FROM admins a JOIN companies c ON c.id = a.company_id WHERE a.id = ?');
    $s->execute([$_SESSION['admin_id']]);
    return $s->fetch() ?: null;
}

function require_admin(bool $api = false): array {
    $a = current_admin();
    if ($a) return $a;
    if ($api) json_out(['error' => 'unauthorized'], 401);
    header('Location: login.php');
    exit;
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_check(?string $t): bool {
    start_session();
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}
