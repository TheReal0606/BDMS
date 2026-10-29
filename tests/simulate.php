<?php
// Dev tool: php tests/simulate.php <pairing_code> [seconds=60] [route=0|1]
// Pairs a fake phone with the code and streams moving GPS points (Metro Manila).
$code = $argv[1] ?? null; $secs = (int)($argv[2] ?? 60); $route = (int)($argv[3] ?? 0);
if (!$code) { fwrite(STDERR, "usage: php tests/simulate.php CODE [seconds] [route]\n"); exit(1); }
$base = getenv('BDMS_URL') ?: 'http://localhost:8081/BDMS';

function post(string $url, array $body, array $hdr = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $hdr)]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode((string)$r, true) ?: [];
}
$p = post("$base/api/pair.php", ['code' => $code, 'device_name' => 'Simulator']);
if (empty($p['token'])) { fwrite(STDERR, "pair failed: " . json_encode($p) . "\n"); exit(1); }
echo "paired: {$p['bus']['name']}\n";

// Waypoints: EDSA (Monumento -> Taft) or Roxas Blvd.
$routes = [
    [[14.6540, 120.9840], [14.6350, 121.0000], [14.6200, 121.0290], [14.5900, 121.0400], [14.5530, 121.0240], [14.5380, 121.0000]],
    [[14.5860, 120.9760], [14.5650, 120.9800], [14.5400, 120.9890], [14.5200, 120.9930], [14.4900, 120.9900]],
];
$wp = $routes[$route % 2];
$steps = max(2, $secs / 3);
for ($i = 0; $i < $steps; $i++) {
    $f = $i / ($steps - 1) * (count($wp) - 1);
    $a = (int)floor($f); $b = min($a + 1, count($wp) - 1); $k = $f - $a;
    $lat = $wp[$a][0] + ($wp[$b][0] - $wp[$a][0]) * $k;
    $lng = $wp[$a][1] + ($wp[$b][1] - $wp[$a][1]) * $k;
    $heading = (rad2deg(atan2($wp[$b][1] - $wp[$a][1], $wp[$b][0] - $wp[$a][0])) + 360) % 360;
    $r = post("$base/api/location.php", ['points' => [[
        'lat' => $lat, 'lng' => $lng, 'speed' => 35 + ($i % 7), 'heading' => $heading,
        'battery' => 90 - (int)($i / 10), 't' => time(),
    ]]], ['Authorization: Bearer ' . $p['token']]);
    echo "sent " . round($lat, 5) . "," . round($lng, 5) . " accepted=" . ($r['accepted'] ?? '?') . "\n";
    sleep(3);
}
