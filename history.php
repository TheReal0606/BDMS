<?php
require __DIR__ . '/lib/auth.php';
$admin = require_admin();
$s = db()->prepare('SELECT id, name, plate FROM buses WHERE company_id = ? ORDER BY name');
$s->execute([$admin['company_id']]);
$buses = $s->fetchAll();
$sel = (int)($_GET['bus_id'] ?? ($buses[0]['id'] ?? 0));
$title = 'History'; $page = 'history';
include __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
  #hmap{height:55vh;min-height:320px;border-radius:var(--r);background:#0b1118}
  .ctl{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end}
  .play{display:flex;gap:12px;align-items:center;margin-top:14px}
  .play input[type=range]{padding:0}
  #info{color:var(--muted);font-size:14px;margin-top:8px}
</style>
<div class="wrap">
  <div class="card">
    <h2>Route history</h2>
    <div class="ctl">
      <div><label>Bus</label>
        <select id="bus"><?php foreach ($buses as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $b['id'] == $sel ? 'selected' : '' ?>><?= e($b['name'] . ' - ' . $b['plate']) ?></option>
        <?php endforeach; ?></select></div>
      <div><label>From</label><input type="datetime-local" id="from"></div>
      <div><label>To</label><input type="datetime-local" id="to"></div>
      <div><button class="btn" id="go">Show route</button></div>
    </div>
    <div id="info">Pick a bus and time range.</div>
  </div>
  <div class="card">
    <div id="hmap"></div>
    <div class="play"><button class="btn ghost" id="pp">Play</button><input type="range" id="slider" min="0" max="0" value="0"><span id="clock" class="pill" style="background:var(--panel2);padding:6px 12px;border-radius:999px;color:var(--muted)">-</span></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="assets/tiles.js"></script>
<script src="assets/history.js"></script>
</body></html>
