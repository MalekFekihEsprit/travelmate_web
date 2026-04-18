/**
 * Carte admin (Leaflet) : choix lat/lng + géocodage inverse Nominatim (usage modéré).
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
(function () {
    'use strict';

    var NOMINATIM = 'https://nominatim.openstreetmap.org/reverse';
    var DEFAULT_CENTER = [36.8065, 10.1815];
    var DEFAULT_ZOOM = 6;
    var POINT_ZOOM = 14;

    function fixLeafletIcon() {
        if (typeof L === 'undefined') {
            return;
        }
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
        });
    }

    function parseCoord(v) {
        if (v === '' || v === null || typeof v === 'undefined') {
            return NaN;
        }
        var n = parseFloat(String(v).replace(',', '.'));
        return n;
    }

    function shortPlaceFromNominatim(data) {
        if (!data || typeof data !== 'object') {
            return '';
        }
        var a = data.address || {};
        var parts = [
            a.city || a.town || a.village || a.municipality,
            a.state || a.region,
            a.country
        ].filter(Boolean);
        if (parts.length) {
            return parts.join(', ');
        }
        var dn = data.display_name;
        if (typeof dn === 'string' && dn.length) {
            return dn.split(',').slice(0, 3).join(',').trim();
        }
        return '';
    }

    function reverseGeocode(lat, lng, email) {
        var params = new URLSearchParams({
            format: 'jsonv2',
            lat: String(lat),
            lon: String(lng),
            'accept-language': 'fr'
        });
        if (email) {
            params.set('email', email);
        }
        return fetch(NOMINATIM + '?' + params.toString(), {
            headers: { Accept: 'application/json' }
        })
            .then(function (r) {
                return r.json();
            })
            .then(shortPlaceFromNominatim)
            .catch(function () {
                return '';
            });
    }

    function initRoot(root) {
        var wrap = root.querySelector('.js-admin-map-wrap');
        var canvas = root.querySelector('.js-admin-map-canvas');
        var latIn = root.querySelector('.js-admin-map-lat');
        var lngIn = root.querySelector('.js-admin-map-lng');
        var toggleBtn = root.querySelector('.js-admin-map-toggle');
        var clearBtn = root.querySelector('.js-admin-map-clear-coords');
        var lieuId = root.getAttribute('data-lieu-input-id');
        var lieuIn = lieuId ? document.getElementById(lieuId) : null;
        var email = root.getAttribute('data-nominatim-email') || '';
        if (typeof window.TRAVELMATE_NOMINATIM_EMAIL === 'string' && window.TRAVELMATE_NOMINATIM_EMAIL) {
            email = window.TRAVELMATE_NOMINATIM_EMAIL;
        }

        if (!wrap || !canvas || !latIn || !lngIn || !toggleBtn) {
            return;
        }

        var map = null;
        var marker = null;
        var reverseTimer = null;
        var mapOpen = false;

        function readLatLng() {
            var la = parseCoord(latIn.value);
            var lo = parseCoord(lngIn.value);
            if (!isFinite(la) || !isFinite(lo)) {
                return null;
            }
            return L.latLng(la, lo);
        }

        function applyLieuFromGeocode(text) {
            if (!lieuIn || !text) {
                return;
            }
            var cur = (lieuIn.value || '').trim();
            if (cur.length > 0) {
                return;
            }
            lieuIn.value = text;
        }

        function scheduleReverse(lat, lng) {
            if (reverseTimer) {
                clearTimeout(reverseTimer);
            }
            reverseTimer = setTimeout(function () {
                reverseGeocode(lat, lng, email).then(applyLieuFromGeocode);
            }, 600);
        }

        function setMarker(latlng, fromUser) {
            if (!map) {
                return;
            }
            latIn.value = latlng.lat.toFixed(7);
            lngIn.value = latlng.lng.toFixed(7);
            if (!marker) {
                marker = L.marker(latlng, { draggable: true }).addTo(map);
                marker.on('dragend', function (e) {
                    var ll = e.target.getLatLng();
                    latIn.value = ll.lat.toFixed(7);
                    lngIn.value = ll.lng.toFixed(7);
                    scheduleReverse(ll.lat, ll.lng);
                });
            } else {
                marker.setLatLng(latlng);
            }
            if (fromUser) {
                scheduleReverse(latlng.lat, latlng.lng);
            }
        }

        function ensureMap() {
            if (map || typeof L === 'undefined') {
                return;
            }
            fixLeafletIcon();
            var start = readLatLng();
            var center = start || L.latLng(DEFAULT_CENTER[0], DEFAULT_CENTER[1]);
            var zoom = start ? POINT_ZOOM : DEFAULT_ZOOM;
            map = L.map(canvas, { scrollWheelZoom: true }).setView(center, zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            map.on('click', function (e) {
                setMarker(e.latlng, true);
            });
            if (start) {
                setMarker(start, false);
            }
            setTimeout(function () {
                map.invalidateSize();
            }, 80);
        }

        toggleBtn.addEventListener('click', function () {
            mapOpen = !mapOpen;
            wrap.hidden = !mapOpen;
            toggleBtn.setAttribute('aria-expanded', mapOpen ? 'true' : 'false');
            toggleBtn.textContent = mapOpen ? 'Masquer la carte' : 'Afficher la carte';
            if (mapOpen) {
                ensureMap();
                if (map) {
                    var ll = readLatLng();
                    if (ll) {
                        map.setView(ll, POINT_ZOOM);
                        setMarker(ll, false);
                    }
                    setTimeout(function () {
                        map.invalidateSize();
                    }, 120);
                }
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                latIn.value = '';
                lngIn.value = '';
                if (marker && map) {
                    map.removeLayer(marker);
                    marker = null;
                }
            });
        }

        function onInputs() {
            if (!map || !mapOpen) {
                return;
            }
            var ll = readLatLng();
            if (ll) {
                if (!marker) {
                    setMarker(ll, false);
                } else {
                    marker.setLatLng(ll);
                }
                map.setView(ll, POINT_ZOOM);
            }
        }

        latIn.addEventListener('change', onInputs);
        lngIn.addEventListener('change', onInputs);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-admin-map-root').forEach(initRoot);
    });
})();
