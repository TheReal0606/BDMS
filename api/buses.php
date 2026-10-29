<?php
require __DIR__ . '/../lib/auth.php';
$admin = require_admin(true);

$s = db()->prepare('SELECT id, name, plate, contact_no, pairing_used, last_lat, last_lng, last_speed, last_heading, last_battery, last_seen
                    FROM buses WHERE company_id = ? ORDER BY name');
$s->execute([$admin['company_id']]);
$out = [];
foreach ($s->fetchAll() as $b) {
    $speed = $b['last_speed'] !== null ? (float)$b['last_speed'] : null;
    $out[] = [
        'id' => (int)$b['id'], 'name' => $b['name'], 'plate' => $b['plate'], 'contact_no' => $b['contact_no'],
        'paired' => (bool)$b['pairing_used'],
        'lat' => $b['last_lat'] !== null ? (float)$b['last_lat'] : null,
        'lng' => $b['last_lng'] !== null ? (float)$b['last_lng'] : null,
        'speed' => $speed,
        'heading' => $b['last_heading'] !== null ? (float)$b['last_heading'] : null,
        'battery' => $b['last_battery'] !== null ? (int)$b['last_battery'] : null,
        'last_seen' => $b['last_seen'],
        'status' => bus_status($b['last_seen'], $speed),
    ];
}
json_out(['buses' => $out, 'server_time' => date('Y-m-d H:i:s')]);
