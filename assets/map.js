(function () {
  var COLORS = { moving: '#2ecc71', stopped: '#f5b041', offline: '#e74c3c' };
  var map = L.map('map', { zoomControl: false }).setView([14.5995, 120.9842], 11);
  L.control.zoom({ position: 'bottomright' }).addTo(map);
  bdmsTileLayer().addTo(map);

  var markers = {};   // id -> {marker, from, to, t0, bus}
  var buses = [];
  var selected = null;
  var fitted = false;

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function iconFor(b) {
    var c = COLORS[b.status] || '#8fa3b8';
    var rot = b.heading == null ? 0 : b.heading;
    var html = '<div class="bus-icon" style="width:40px;height:40px;transform:rotate(' + rot + 'deg)">' +
      '<svg viewBox="0 0 40 40" width="40" height="40"><circle cx="20" cy="20" r="17" fill="#0f1720" stroke="' + c + '" stroke-width="3"/>' +
      '<path d="M20 7 L28 27 L20 22 L12 27 Z" fill="' + c + '"/></svg></div>' +
      '<div class="bus-tag">' + esc(b.name) + '</div>';
    return L.divIcon({ html: html, className: '', iconSize: [40, 40], iconAnchor: [20, 20] });
  }

  function ago(ts) {
    if (!ts) return 'never';
    var s = Math.max(0, Math.round((Date.now() - new Date(ts.replace(' ', 'T')).getTime()) / 1000));
    if (s < 60) return s + 's ago';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    return Math.floor(s / 86400) + 'd ago';
  }

  function popup(b) {
    return '<div class="pop"><b>' + esc(b.name) + '</b> &middot; ' + esc(b.plate) +
      '<div>' + b.status + ' &middot; ' + (b.speed != null ? Math.round(b.speed) + ' km/h' : '-') + '</div>' +
      '<div>Battery ' + (b.battery != null ? b.battery + '%' : '-') + ' &middot; ' + ago(b.last_seen) + '</div>' +
      (b.contact_no ? '<div>' + esc(b.contact_no) + '</div>' : '') +
      '<div><a href="history.php?bus_id=' + b.id + '">Route history</a></div></div>';
  }

  function animate(now) {
    Object.keys(markers).forEach(function (id) {
      var m = markers[id];
      if (!m.to) return;
      var k = Math.min(1, (now - m.t0) / 1500);
      m.marker.setLatLng([m.from[0] + (m.to[0] - m.from[0]) * k, m.from[1] + (m.to[1] - m.from[1]) * k]);
    });
    requestAnimationFrame(animate);
  }
  requestAnimationFrame(animate);

  function upsert(b) {
    if (b.lat == null) return;
    var m = markers[b.id];
    if (!m) {
      var mk = L.marker([b.lat, b.lng], { icon: iconFor(b) }).addTo(map).bindPopup(popup(b));
      mk.on('click', function () { selected = b.id; render(); });
      markers[b.id] = { marker: mk, from: [b.lat, b.lng], to: null, t0: 0, bus: b };
    } else {
      var cur = m.marker.getLatLng();
      m.from = [cur.lat, cur.lng];
      m.to = [b.lat, b.lng];
      m.t0 = performance.now();
      m.marker.setIcon(iconFor(b));
      m.marker.setPopupContent(popup(b));
      m.bus = b;
    }
  }

  function render() {
    var q = document.getElementById('q').value.trim().toLowerCase();
    var vis = buses.filter(function (b) { return !q || (b.name + ' ' + b.plate).toLowerCase().indexOf(q) >= 0; });
    document.getElementById('count').textContent = buses.length + (buses.length === 1 ? ' bus' : ' buses');
    var html = '';
    [['Online', function (b) { return b.status !== 'offline'; }], ['Offline', function (b) { return b.status === 'offline'; }]].forEach(function (g) {
      var items = vis.filter(g[1]);
      if (!items.length) return;
      html += '<div class="grp">' + g[0] + ' (' + items.length + ')</div>';
      items.forEach(function (b) {
        html += '<div class="row' + (selected === b.id ? ' sel' : '') + '" data-id="' + b.id + '">' +
          '<span class="bubble ' + b.status + '"></span><div><div class="nm">' + esc(b.name) + '</div>' +
          '<div class="sub">' + esc(b.plate) + (b.paired ? '' : ' &middot; not paired') + '</div></div>' +
          '<div class="sp">' + (b.status === 'offline' ? '' : Math.round(b.speed || 0) + ' <small>km/h</small>') +
          (b.status === 'offline' ? '<small>' + ago(b.last_seen) + '</small>' : '') + '</div></div>';
      });
    });
    document.getElementById('list').innerHTML = html || '<div class="empty">' +
      (buses.length ? 'No match.' : 'No buses yet. Add one on the Buses page.') + '</div>';
  }

  document.getElementById('list').addEventListener('click', function (e) {
    var row = e.target.closest('.row'); if (!row) return;
    selected = parseInt(row.getAttribute('data-id'), 10);
    var m = markers[selected];
    if (m) { map.flyTo(m.marker.getLatLng(), Math.max(map.getZoom(), 15), { duration: 0.8 }); m.marker.openPopup(); }
    render();
  });
  document.getElementById('q').addEventListener('input', render);

  function load() {
    fetch('api/buses.php', { credentials: 'same-origin' })
      .then(function (r) { if (r.status === 401) { location.href = 'login.php'; throw 0; } return r.json(); })
      .then(function (d) {
        buses = d.buses || [];
        buses.forEach(upsert);
        if (!fitted) {
          var pts = buses.filter(function (b) { return b.lat != null; }).map(function (b) { return [b.lat, b.lng]; });
          if (pts.length) { map.fitBounds(pts, { padding: [80, 80], maxZoom: 15 }); fitted = true; }
        }
        render();
      }).catch(function () {});
  }
  load();
  setInterval(load, 3000);
})();
