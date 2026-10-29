<?php
require __DIR__ . '/../lib/auth.php';
$admin = require_admin(true);
$db = db();

$bus = find_bus($db, (int)$admin['company_id'], (int)($_GET['bus_id'] ?? 0));
if (!$bus) json_out(['error' => 'not found'], 404);

$norm = function (?string $v, string $default): string {
    $ts = $v ? strtotime($v) : false;
    return date('Y-m-d H:i:s', $ts !== false ? $ts : strtotime($default));
};
$from = $norm($_GET['from'] ?? null, '-1 day');
$to = $norm($_GET['to'] ?? null, 'now');

$s = $db->prepare('SELECT lat, lng, speed, heading, UNIX_TIMESTAMP(recorded_at) AS t
                   FROM locations WHERE bus_id = ? AND recorded_at BETWEEN ? AND ?
                   ORDER BY recorded_at LIMIT 5000');
$s->execute([$bus['id'], $from, $to]);
$pts = array_map(fn($r) => [
    'lat' => (float)$r['lat'], 'lng' => (float)$r['lng'],
    'speed' => $r['speed'] !== null ? (float)$r['speed'] : null,
    'heading' => $r['heading'] !== null ? (float)$r['heading'] : null,
    't' => (int)$r['t'],
], $s->fetchAll());
json_out(['bus' => ['id' => (int)$bus['id'], 'name' => $bus['name'], 'plate' => $bus['plate']], 'points' => $pts]);
