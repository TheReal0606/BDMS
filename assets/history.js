(function () {
  var map = L.map('hmap').setView([14.5995, 120.9842], 11);
  bdmsTileLayer().addTo(map);

  var line = null, dot = null, pts = [], timer = null;
  var $ = function (id) { return document.getElementById(id); };

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function local(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function fmt(t) { var d = new Date(t * 1000); return d.toLocaleString('en-PH'); }

  var now = new Date();
  $('to').value = local(now);
  $('from').value = local(new Date(now.getTime() - 6 * 3600 * 1000));

  function stop() { if (timer) { clearInterval(timer); timer = null; $('pp').textContent = 'Play'; } }

  function show(i) {
    if (!pts.length) return;
    var p = pts[i];
    if (!dot) dot = L.circleMarker([p.lat, p.lng], { radius: 8, color: '#33ccff', fillColor: '#33ccff', fillOpacity: 1 }).addTo(map);
    dot.setLatLng([p.lat, p.lng]);
    $('clock').textContent = fmt(p.t) + (p.speed != null ? ' - ' + Math.round(p.speed) + ' km/h' : '');
    $('slider').value = i;
  }

  function load() {
    stop();
    var q = 'bus_id=' + encodeURIComponent($('bus').value) +
      '&from=' + encodeURIComponent($('from').value.replace('T', ' ')) +
      '&to=' + encodeURIComponent($('to').value.replace('T', ' '));
    fetch('api/history.php?' + q, { credentials: 'same-origin' })
      .then(function (r) { if (r.status === 401) { location.href = 'login.php'; throw 0; } return r.json(); })
      .then(function (d) {
        pts = d.points || [];
        if (line) { map.removeLayer(line); line = null; }
        if (dot) { map.removeLayer(dot); dot = null; }
        $('slider').max = Math.max(0, pts.length - 1);
        $('info').textContent = d.error ? 'Bus not found.' : pts.length + ' points' + (pts.length >= 5000 ? ' (limit reached - narrow the range)' : '');
        if (!pts.length) { $('clock').textContent = '-'; return; }
        line = L.polyline(pts.map(function (p) { return [p.lat, p.lng]; }), { color: '#33ccff', weight: 5, opacity: .85 }).addTo(map);
        map.fitBounds(line.getBounds(), { padding: [30, 30] });
        show(0);
      }).catch(function () {});
  }

  $('go').addEventListener('click', load);
  $('slider').addEventListener('input', function () { stop(); show(parseInt(this.value, 10)); });
  $('pp').addEventListener('click', function () {
    if (timer) return stop();
    if (!pts.length) return;
    if (parseInt($('slider').value, 10) >= pts.length - 1) $('slider').value = 0;
    $('pp').textContent = 'Pause';
    timer = setInterval(function () {
      var i = parseInt($('slider').value, 10) + 1;
      if (i >= pts.length) return stop();
      show(i);
    }, 120);
  });
  if ($('bus').value) load();
})();
