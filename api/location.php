<?php
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method'], 405);

$hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$hdr && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) $hdr = $v;
}
if (!preg_match('/^Bearer\s+([0-9a-f]{64})$/i', $hdr, $m)) json_out(['error' => 'unauthorized'], 401);

$db = db();
$s = $db->prepare('SELECT id FROM buses WHERE api_token = ?');
$s->execute([strtolower($m[1])]);
$bus = $s->fetch();
if (!$bus) json_out(['error' => 'unauthorized'], 401);
$busId = (int)$bus['id'];

$in = json_decode(file_get_contents('php://input'), true);
$points = is_array($in['points'] ?? null) ? array_slice($in['points'], 0, 100) : [];

$ins = $db->prepare('INSERT IGNORE INTO locations(bus_id,lat,lng,speed,heading,battery,recorded_at) VALUES (?,?,?,?,?,?,?)');
$accepted = 0; $newest = null;
foreach ($points as $p) {
    if (!is_array($p) || !valid_point($p)) continue;
    $speed = isset($p['speed']) && is_numeric($p['speed']) ? max(0, (float)$p['speed']) : null;
    $heading = isset($p['heading']) && is_numeric($p['heading']) ? fmod((float)$p['heading'], 360) : null;
    $battery = isset($p['battery']) && is_numeric($p['battery']) ? max(0, min(100, (int)$p['battery'])) : null;
    $at = date('Y-m-d H:i:s', (int)$p['t']);
    $ins->execute([$busId, (float)$p['lat'], (float)$p['lng'], $speed, $heading, $battery, $at]);
    if ($ins->rowCount() === 1) {
        $accepted++;
        if ($newest === null || $p['t'] > $newest['t']) $newest = ['p' => $p, 't' => $p['t'], 'at' => $at, 'speed' => $speed, 'heading' => $heading, 'battery' => $battery];
    }
}
// Always refresh last_seen (phone is alive); move the marker only for newer points.
$db->prepare('UPDATE buses SET last_seen = NOW() WHERE id = ?')->execute([$busId]);
if ($newest) {
    $db->prepare('UPDATE buses SET last_lat=?, last_lng=?, last_speed=?, last_heading=?, last_battery=?, last_point_at=?
                  WHERE id = ? AND (last_point_at IS NULL OR last_point_at < ?)')
       ->execute([(float)$newest['p']['lat'], (float)$newest['p']['lng'], $newest['speed'], $newest['heading'], $newest['battery'], $newest['at'], $busId, $newest['at']]);
}
json_out(['ok' => true, 'accepted' => $accepted]);
