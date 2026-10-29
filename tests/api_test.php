<?php
// php tests/api_test.php  (Apache must be running)
chdir(__DIR__ . '/..');
require 'lib/util.php';
require 'lib/db.php';
const BASE = 'http://localhost:8081/BDMS';

$fail = 0; $n = 0;
function t(string $name, bool $ok, string $extra = ''): void {
    global $fail, $n; $n++;
    echo ($ok ? "PASS " : "FAIL ") . $name . ($ok ? '' : " $extra") . "\n";
    if (!$ok) $fail++;
}
function http(string $method, string $path, ?array $json = null, array $headers = [], ?string $jar = null, ?array $form = null): array {
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 15]);
    if ($json !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json)); $headers[] = 'Content-Type: application/json'; }
    if ($form !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string)$body, true), (string)$body];
}
function login(string $email, string $pw, string $jar): void {
    $r = http('GET', '/login.php', null, [], $jar);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $r[2], $m);
    http('POST', '/login.php', null, [], $jar, ['csrf' => $m[1], 'email' => $email, 'password' => $pw]);
}

$db = db();
$db->exec("DELETE FROM companies WHERE name IN ('API_A','API_B')");
$db->exec("INSERT INTO companies(name) VALUES ('API_A'),('API_B')");
$ids = $db->query("SELECT id FROM companies WHERE name IN ('API_A','API_B') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
[$ca, $cb] = [(int)$ids[0], (int)$ids[1]];
$h = password_hash('testpass1', PASSWORD_DEFAULT);
$db->prepare("INSERT INTO admins(company_id,name,email,password_hash) VALUES (?,?,?,?),(?,?,?,?)")
   ->execute([$ca, 'A', 'a@api.test', $h, $cb, 'B', 'b@api.test', $h]);
$busA = create_bus($db, $ca, 'Bus A', 'AAA111', null);
$busB = create_bus($db, $cb, 'Bus B', 'BBB222', null);
$stale = create_bus($db, $ca, 'Stale', 'STL000', null);

// ---- pair
[$c] = http('POST', '/api/pair.php', ['code' => 'ZZZZZZ', 'device_name' => 'x']);
t('pair wrong code -> 404', $c === 404, "got $c");
[$c, $j] = http('POST', '/api/pair.php', ['code' => $busA['pairing_code'], 'device_name' => 'Test Phone']);
t('pair ok -> 200 + token', $c === 200 && !empty($j['token']) && strlen($j['token']) === 64, "got $c");
$token = $j['token'] ?? '';
[$c] = http('POST', '/api/pair.php', ['code' => $busA['pairing_code'], 'device_name' => 'Other']);
t('pair reuse code -> 404', $c === 404, "got $c");

// ---- location
$now = time();
$pts = [
    ['lat' => 14.5995, 'lng' => 120.9842, 'speed' => 20, 'heading' => 90, 'battery' => 80, 't' => $now - 20],
    ['lat' => 200,     'lng' => 120.9842, 'speed' => 20, 'heading' => 90, 'battery' => 80, 't' => $now - 15],
    ['lat' => 14.6005, 'lng' => 120.9852, 'speed' => 25, 'heading' => 45, 'battery' => 79, 't' => $now - 10],
];
$auth = ['Authorization: Bearer ' . $token];
[$c, $j] = http('POST', '/api/location.php', ['points' => $pts], $auth);
t('location batch accepts 2 of 3', $c === 200 && ($j['accepted'] ?? -1) === 2, "got $c " . json_encode($j));
[$c, $j] = http('POST', '/api/location.php', ['points' => $pts], $auth);
t('replayed batch accepts 0, no error', $c === 200 && ($j['accepted'] ?? -1) === 0, "got $c " . json_encode($j));
[$c] = http('POST', '/api/location.php', ['points' => $pts], ['Authorization: Bearer nope']);
t('bad token -> 401', $c === 401, "got $c");
[$c] = http('POST', '/api/location.php', ['points' => $pts]);
t('no token -> 401', $c === 401, "got $c");
$row = $db->query("SELECT last_lat,last_lng FROM buses WHERE id = " . (int)$busA['id'])->fetch();
t('last position is newest accepted point', abs($row['last_lat'] - 14.6005) < 1e-6);

// out-of-order older batch must not move last position
http('POST', '/api/location.php', ['points' => [['lat' => 10.0, 'lng' => 120.0, 't' => $now - 600]]], $auth);
$row = $db->query("SELECT last_lat FROM buses WHERE id = " . (int)$busA['id'])->fetch();
t('older offline point does not overwrite last position', abs($row['last_lat'] - 14.6005) < 1e-6);

// make 'Stale' paired and last seen long ago
$db->prepare("UPDATE buses SET last_lat=14.5,last_lng=121,last_speed=0,last_seen=NOW() - INTERVAL 1 HOUR WHERE id = ?")->execute([$stale['id']]);

// ---- buses (admin session)
[$c] = http('GET', '/api/buses.php');
t('buses unauthenticated -> 401', $c === 401, "got $c");
$jarA = tempnam(sys_get_temp_dir(), 'bd'); login('a@api.test', 'testpass1', $jarA);
[$c, $j] = http('GET', '/api/buses.php', null, [], $jarA);
$names = array_column($j['buses'] ?? [], 'name');
t('admin A sees only own buses', $c === 200 && in_array('Bus A', $names) && in_array('Stale', $names) && !in_array('Bus B', $names), json_encode($names));
$byName = array_column($j['buses'] ?? [], null, 'name');
t('fresh bus is moving', ($byName['Bus A']['status'] ?? '') === 'moving');
t('stale bus is offline', ($byName['Stale']['status'] ?? '') === 'offline');

// ---- history
$q = '?bus_id=' . (int)$busA['id'] . '&from=' . urlencode(date('Y-m-d H:i:s', $now - 3600)) . '&to=' . urlencode(date('Y-m-d H:i:s', $now + 60));
[$c, $j] = http('GET', '/api/history.php' . $q, null, [], $jarA);
t('history returns 3 points (incl. older offline point)', $c === 200 && count($j['points'] ?? []) === 3, "got $c " . json_encode($j));
$q2 = '?bus_id=' . (int)$busA['id'] . '&from=' . urlencode(date('Y-m-d H:i:s', $now - 12)) . '&to=' . urlencode(date('Y-m-d H:i:s', $now + 60));
[$c, $j] = http('GET', '/api/history.php' . $q2, null, [], $jarA);
t('history range filter', $c === 200 && count($j['points'] ?? []) === 1, "got $c " . json_encode($j));
[$c] = http('GET', '/api/history.php?bus_id=' . (int)$busB['id'], null, [], $jarA);
t("other company's bus history -> 404", $c === 404, "got $c");

$db->exec("DELETE FROM companies WHERE name IN ('API_A','API_B')");
@unlink($jarA);
echo "\n$n tests, $fail failed\n";
exit($fail ? 1 : 0);
