/**
 * Shared Leaflet map utilities for Travel Platform.
 * Requires Leaflet JS and CSS to be loaded on the page.
 */

/**
 * Initialize a Leaflet map with default OSM tiles.
 * @param {string} containerId - The ID of the div element for the map.
 * @param {Object} [options] - Override default map options (center, zoom, etc.).
 * @returns {L.Map} The Leaflet map instance.
 */
function initMap(containerId, options) {
  var defaults = {
    center: [23.6978, 120.9605],
    zoom: 7,
  };
  var config = Object.assign({}, defaults, options || {});
  var map = L.map(containerId, config);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:
      '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap contributors</a>',
    maxZoom: 19,
  }).addTo(map);

  return map;
}

/**
 * Add a marker for a trip on the map with a popup.
 * @param {L.Map} map - The Leaflet map instance.
 * @param {Object} trip - Trip object with {id, title, latitude, longitude, average_rating}.
 * @returns {L.Marker|undefined} The marker instance, or undefined if no coordinates.
 */
function addTripMarker(map, trip) {
  if (!trip || !trip.latitude || !trip.longitude) return;

  var lat = parseFloat(trip.latitude);
  var lng = parseFloat(trip.longitude);
  if (isNaN(lat) || isNaN(lng)) return;

  var marker = L.marker([lat, lng]).addTo(map);
  var rating = trip.average_rating != null
    ? '\u2605 ' + parseFloat(trip.average_rating).toFixed(1) + ' / 5'
    : '\u5c1a\u7121\u8a55\u5206';
  var popup = document.createElement('div');
  var link = document.createElement('a');
  link.href = '/trip.php?id=' + encodeURIComponent(trip.id);
  link.textContent = '\u67e5\u770b\u8a73\u60c5';
  popup.innerHTML =
    '<strong>' + escapeHtml(trip.title || '') + '</strong><br>' +
    '<span>' + rating + '</span><br>' +
    '<p>' + escapeHtml((trip.summary || '').substring(0, 50)) + '</p>';
  popup.appendChild(link);
  marker.bindPopup(popup);

  return marker;
}

/**
 * Adjust the map view to encompass all given markers.
 * @param {L.Map} map - The Leaflet map instance.
 * @param {Array<L.Marker>} markers - Array of Leaflet marker instances.
 */
function fitAllMarkers(map, markers) {
  if (!markers || markers.length === 0) return;
  var group = L.featureGroup(markers);
  map.fitBounds(group.getBounds().pad(0.1));
}

/**
 * Simple HTML entity escaping for popup content.
 */
function escapeHtml(text) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(text));
  return d.innerHTML;
}

/**
 * Add a spot marker as a colored circle on the map.
 * @param {L.Map} map - The Leaflet map instance.
 * @param {Object} spot - Spot object with {name, latitude, longitude, address, notes, sort_order}.
 * @param {number} index - Zero-based index for numbering.
 * @returns {L.CircleMarker|undefined} The marker, or undefined if no valid coordinates.
 */
function addSpotMarker(map, spot, index) {
  if (!spot || !spot.latitude || !spot.longitude) return;
  var lat = parseFloat(spot.latitude);
  var lng = parseFloat(spot.longitude);
  if (isNaN(lat) || isNaN(lng)) return;

  var marker = L.circleMarker([lat, lng], {
    radius: 10,
    fillColor: '#4f46e5',
    color: '#3730a3',
    weight: 2,
    opacity: 1,
    fillOpacity: 0.8,
  }).addTo(map);

  var popup = document.createElement('div');
  var html = '<strong>' + escapeHtml(spot.name || '') + '</strong>';
  if (spot.address) {
    html += '<br><span>' + escapeHtml(spot.address) + '</span>';
  }
  // Show first photo thumbnail if spot has photos
  if (spot._photos && spot._photos.length > 0) {
    var first = spot._photos[0];
    html += '<br><a href="#" onclick="return false;" style="display:inline-block;margin-top:6px;border-radius:6px;overflow:hidden;border:1px solid #ddd;">' +
      '<img src="' + escapeHtml(first.image_path) + '" alt="photo" ' +
      'data-uploader="' + escapeHtml(first.uploader_name ?? '') + '" ' +
      'data-caption="' + escapeHtml(first.caption ?? '') + '" ' +
      'style="width:120px;height:90px;object-fit:cover;display:block;cursor:pointer;" ' +
      'onclick="var lb=document.getElementById(\'lightbox\'),im=document.getElementById(\'lightbox-image\'),nf=document.getElementById(\'lightbox-info\');' +
      'if(lb&&im){im.src=this.src;' +
      'if(nf){var p=[],u=this.getAttribute(\'data-uploader\'),c=this.getAttribute(\'data-caption\');' +
      'if(u)p.push(\'📸 \'+u);if(c)p.push(\'\\"\'+c+\'\\"\');nf.textContent=p.join(\' · \');}' +
      'lb.style.display=\'flex\';document.body.style.overflow=\'hidden\';}return false;"></a>' +
    if (first.caption) {
      html += '<br><span style="font-size:12px;color:#666;">' + escapeHtml(first.caption) + '</span>';
    }
  }
  if (spot.notes) {
    html += '<br><p>' + escapeHtml(spot.notes) + '</p>';
  }
  popup.innerHTML = html;
  marker.bindPopup(popup);

  return marker;
}

/**
 * Connect spots with a dashed polyline on the map following sort order.
 * @param {L.Map} map - The Leaflet map instance.
 * @param {Array<Object>} spots - Array of spot objects with latitude/longitude.
 * @returns {L.Polyline|null} The polyline, or null if fewer than 2 spots have coordinates.
 */
function connectSpotsPolyline(map, spots) {
  if (!spots || spots.length < 2) return null;

  var latlngs = [];
  for (var i = 0; i < spots.length; i++) {
    var s = spots[i];
    if (!s.latitude || !s.longitude) continue;
    var lat = parseFloat(s.latitude);
    var lng = parseFloat(s.longitude);
    if (isNaN(lat) || isNaN(lng)) continue;
    latlngs.push([lat, lng]);
  }

  if (latlngs.length < 2) return null;

  var polyline = L.polyline(latlngs, {
    color: '#4f46e5',
    weight: 3,
    opacity: 0.7,
    dashArray: '8, 12',
  }).addTo(map);

  return polyline;
}
