<?php
require_once __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Manila');

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function bus_status(?string $last_seen, ?float $speed): string {
    if ($last_seen === null) return 'offline';
    if (time() - strtotime($last_seen) > OFFLINE_SECONDS) return 'offline';
    return ($speed ?? 0) > MOVING_KMH ? 'moving' : 'stopped';
}

function valid_point(array $p): bool {
    foreach (['lat', 'lng', 't'] as $k) {
        if (!isset($p[$k]) || !is_numeric($p[$k])) return false;
    }
    if ($p['lat'] < -90 || $p['lat'] > 90 || $p['lng'] < -180 || $p['lng'] > 180) return false;
    return $p['t'] > 946684800 && $p['t'] < time() + 86400;
}

function gen_pairing_code(PDO $db): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $stmt = $db->prepare('SELECT 1 FROM buses WHERE pairing_code = ?');
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

function create_bus(PDO $db, int $company_id, string $name, string $plate, ?string $contact): array {
    $code = gen_pairing_code($db);
    $db->prepare('INSERT INTO buses(company_id,name,plate,contact_no,pairing_code) VALUES (?,?,?,?,?)')
       ->execute([$company_id, $name, $plate, $contact ?: null, $code]);
    return find_bus($db, $company_id, (int)$db->lastInsertId());
}

function find_bus(PDO $db, int $company_id, int $id): ?array {
    $s = $db->prepare('SELECT * FROM buses WHERE id = ? AND company_id = ?');
    $s->execute([$id, $company_id]);
    return $s->fetch() ?: null;
}
