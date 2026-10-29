<?php
require __DIR__ . '/lib/auth.php';
$admin = require_admin();
$title = 'Live Map'; $page = 'map';
include __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
  html,body{height:100%}
  body{display:flex;flex-direction:column}
  #app{position:relative;flex:1;min-height:0}
  #map{position:absolute;inset:0;background:#0b1118}
  .side{position:absolute;z-index:900;top:14px;left:14px;bottom:14px;width:320px;display:flex;flex-direction:column;gap:10px;pointer-events:none}
  .side>*{pointer-events:auto}
  .search{background:var(--panel);border-radius:999px;padding:4px 6px 4px 16px;box-shadow:0 6px 24px #0008;display:flex;align-items:center;gap:8px}
  .search input{border:0;background:transparent;padding:10px 0;outline:none}
  .pill{font-size:12px;font-weight:700;padding:5px 10px;border-radius:999px;background:var(--panel2);color:var(--muted);white-space:nowrap}
  .list{background:var(--panel);border-radius:var(--r);box-shadow:0 6px 24px #0008;overflow:auto;flex:0 1 auto;max-height:100%}
  .grp{padding:12px 16px 6px;font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
  .row{display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;border-top:1px solid #26364a}
  .row:hover,.row.sel{background:var(--panel2)}
  .row .nm{font-weight:700}.row .sub{font-size:12px;color:var(--muted)}
  .row .sp{margin-left:auto;text-align:right;font-weight:800}
  .row .sp small{display:block;font-weight:500;color:var(--muted);font-size:11px}
  .bubble{width:16px;height:16px;border-radius:50%;flex:none;background:var(--muted)}
  .bubble.moving{background:var(--ok)}.bubble.stopped{background:var(--warn)}.bubble.offline{background:var(--bad)}
  .bus-icon{filter:drop-shadow(0 3px 5px #000a)}
  .bus-tag{position:absolute;left:50%;top:44px;transform:translateX(-50%);background:var(--panel);color:var(--text);font:700 11px system-ui;padding:3px 9px;border-radius:999px;white-space:nowrap;box-shadow:0 2px 8px #0009}
  .leaflet-popup-content-wrapper{background:var(--panel);color:var(--text);border-radius:16px}
  .leaflet-popup-tip{background:var(--panel)}
  .pop b{font-size:15px}.pop div{color:var(--muted);font-size:13px;margin-top:2px}
  .empty{padding:18px;color:var(--muted);font-size:14px}
  .toggle{display:none}
  @media(max-width:700px){
    .side{top:auto;left:8px;right:8px;bottom:8px;width:auto;max-height:46%}
    .list{max-height:34vh}
  }
</style>
<div id="app">
  <div id="map"></div>
  <div class="side">
    <div class="search"><input id="q" placeholder="Search bus or plate"><span class="pill" id="count">0 buses</span></div>
    <div class="list" id="list"><div class="empty">Loading...</div></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="assets/tiles.js"></script>
<script src="assets/map.js"></script>
</body></html>
