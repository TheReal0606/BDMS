// Map tile source. OpenStreetMap's public tiles are fine for testing and light use.
// For a production fleet, use a tile provider with an API key (MapTiler, Stadia Maps, Mapbox, ...)
// and change `url`/`attribution` here. Set `darken: false` if the provider already serves a dark style.
window.BDMS_TILES = {
  url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  maxZoom: 19,
  darken: true
};

window.bdmsTileLayer = function () {
  var t = window.BDMS_TILES;
  return L.tileLayer(t.url, {
    maxZoom: t.maxZoom, attribution: t.attribution,
    className: t.darken ? 'tiles-dark' : ''
  });
};
