<?php
// Plain assert runner: php tests/run.php
chdir(__DIR__ . '/..');
require 'lib/util.php';
require 'lib/db.php';
require 'lib/auth.php';

$fail = 0; $n = 0;
function t(string $name, bool $ok): void {
    global $fail, $n; $n++;
    echo ($ok ? "PASS " : "FAIL ") . $name . "\n";
    if (!$ok) $fail++;
}

$now = time();
t('offline when last_seen 3 min old', bus_status(date('Y-m-d H:i:s', $now - 180), 10.0) === 'offline');
t('offline when never seen', bus_status(null, null) === 'offline');
t('moving when fresh and speed 20', bus_status(date('Y-m-d H:i:s', $now), 20.0) === 'moving');
t('stopped when fresh and speed 0', bus_status(date('Y-m-d H:i:s', $now), 0.0) === 'stopped');

$ok = ['lat' => 14.5, 'lng' => 121.0, 'speed' => 10, 't' => $now];
t('valid point accepted', valid_point($ok));
t('lat 91 rejected', !valid_point(['lat' => 91] + $ok));
t('lng non-numeric rejected', !valid_point(['lng' => 'x'] + $ok));
t('missing t rejected', !valid_point(['lat' => 1, 'lng' => 1]));

$pdo = db();
$code = gen_pairing_code($pdo);
t('pairing code format', (bool)preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $code));

$_SESSION = ['csrf' => 'abc'];
t('csrf wrong token rejected', !csrf_check('zzz'));
t('csrf right token accepted', csrf_check('abc'));

// company scoping
$pdo->exec("DELETE FROM companies WHERE name IN ('T_A','T_B')");
$pdo->exec("INSERT INTO companies(name) VALUES ('T_A'),('T_B')");
$ids = $pdo->query("SELECT id FROM companies WHERE name IN ('T_A','T_B') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$busA = create_bus($pdo, (int)$ids[0], 'Bus 1', 'ABC123', null);
t('bus has valid code', (bool)preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $busA['pairing_code']));
t('own company finds bus', find_bus($pdo, (int)$ids[0], (int)$busA['id']) !== null);
t('other company cannot find bus', find_bus($pdo, (int)$ids[1], (int)$busA['id']) === null);
$pdo->exec("DELETE FROM companies WHERE name IN ('T_A','T_B')");

echo "\n$n tests, $fail failed\n";
exit($fail ? 1 : 0);
