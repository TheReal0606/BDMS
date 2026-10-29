<?php
require __DIR__ . '/lib/auth.php';
$admin = require_admin();
$db = db(); $cid = (int)$admin['company_id'];
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) { $err = 'Session expired. Reload the page.'; }
    else {
        $act = $_POST['action'] ?? '';
        if ($act === 'add') {
            $name = trim($_POST['name'] ?? ''); $plate = strtoupper(trim($_POST['plate'] ?? ''));
            $contact = trim($_POST['contact_no'] ?? '');
            if ($name === '' || $plate === '') $err = 'Bus name and plate are required.';
            else { create_bus($db, $cid, mb_substr($name, 0, 80), mb_substr($plate, 0, 20), $contact !== '' ? mb_substr($contact, 0, 20) : null); $msg = 'Bus added. Give the pairing code to the driver.'; }
        } elseif ($act === 'delete' || $act === 'regen') {
            $bus = find_bus($db, $cid, (int)($_POST['id'] ?? 0));
            if (!$bus) $err = 'Bus not found.';
            elseif ($act === 'delete') { $db->prepare('DELETE FROM buses WHERE id = ?')->execute([$bus['id']]); $msg = 'Bus deleted.'; }
            else {
                $db->prepare('UPDATE buses SET pairing_code = ?, pairing_used = 0, api_token = NULL, device_name = NULL WHERE id = ?')
                   ->execute([gen_pairing_code($db), $bus['id']]);
                $msg = 'New pairing code issued. The old phone is unlinked.';
            }
        }
    }
}
$s = $db->prepare('SELECT * FROM buses WHERE company_id = ? ORDER BY name'); $s->execute([$cid]);
$buses = $s->fetchAll();
$title = 'Buses'; $page = 'buses';
include __DIR__ . '/partials/header.php';
?>
<div class="wrap">
  <?php if ($msg): ?><div class="ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <div class="card">
    <h2>Add bus</h2>
    <form method="post" class="grid">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add">
      <div><label>Bus name</label><input name="name" required maxlength="80" placeholder="Bus 01"></div>
      <div><label>Plate number</label><input name="plate" required maxlength="20" placeholder="ABC 1234"></div>
      <div><label>Contact no. (optional)</label><input name="contact_no" maxlength="20" placeholder="09xx xxx xxxx"></div>
      <div style="align-self:end"><button class="btn">Add bus</button></div>
    </form>
  </div>
  <div class="card">
    <h2>Buses (<?= count($buses) ?>)</h2>
    <table>
      <tr><th>Bus</th><th>Plate</th><th>Pairing code</th><th class="hide-sm">Contact</th><th></th></tr>
      <?php foreach ($buses as $b): ?>
      <tr>
        <td><span class="dot <?= e(bus_status($b['last_seen'], $b['last_speed'] !== null ? (float)$b['last_speed'] : null)) ?>"></span><?= e($b['name']) ?></td>
        <td><?= e($b['plate']) ?></td>
        <td><?= $b['pairing_used'] ? '<span style="color:var(--ok)">Paired</span> <small style="color:var(--muted)">' . e($b['device_name']) . '</small>' : '<span class="code">' . e($b['pairing_code']) . '</span>' ?></td>
        <td class="hide-sm"><?= e($b['contact_no'] ?: '-') ?></td>
        <td style="white-space:nowrap">
          <a class="btn ghost" href="history.php?bus_id=<?= (int)$b['id'] ?>">History</a>
          <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn ghost" name="action" value="regen" onclick="return confirm('Unlink the current phone and issue a new code?')">New code</button>
            <button class="btn danger" name="action" value="delete" onclick="return confirm('Delete this bus and all its history?')">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; if (!$buses): ?><tr><td colspan="5" style="color:var(--muted)">No buses yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div></body></html>
