<?php
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method'], 405);
$in = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim((string)($in['code'] ?? '')));
$device = mb_substr(trim((string)($in['device_name'] ?? 'Android')), 0, 80);
if (!preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $code)) json_out(['error' => 'Invalid code'], 400);

$db = db();
$token = bin2hex(random_bytes(32));
// Atomic claim: only one phone can use a code.
$s = $db->prepare('UPDATE buses SET pairing_used = 1, api_token = ?, device_name = ? WHERE pairing_code = ? AND pairing_used = 0');
$s->execute([$token, $device, $code]);
if ($s->rowCount() !== 1) json_out(['error' => 'Code not found or already used'], 404);

$b = $db->prepare('SELECT id, name, plate FROM buses WHERE api_token = ?');
$b->execute([$token]);
json_out(['ok' => true, 'token' => $token, 'bus' => $b->fetch()]);
