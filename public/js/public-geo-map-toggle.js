/**
 * Carte Leaflet affichée au clic (fiches activité / événement).
 */
(function () {
    'use strict';

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

    function ensureMap(canvas, lat, lng, label) {
        if (canvas._tmLeafletMap) {
            return canvas._tmLeafletMap;
        }
        fixLeafletIcon();
        var map = L.map(canvas, { scrollWheelZoom: true }).setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        L.marker([lat, lng]).addTo(map).bindPopup(label || '');
        canvas._tmLeafletMap = map;
        setTimeout(function () {
            map.invalidateSize();
        }, 100);
        return map;
    }

    function bindRoot(root) {
        var btn = root.querySelector('.js-tm-geo-map-toggle');
        var panel = root.querySelector('.js-tm-geo-map-panel');
        var canvas = root.querySelector('.js-tm-geo-map-canvas');
        if (!btn || !panel || !canvas) {
            return;
        }
        var lat = parseFloat(root.getAttribute('data-lat'));
        var lng = parseFloat(root.getAttribute('data-lng'));
        var label = root.getAttribute('data-label') || '';
        if (!isFinite(lat) || !isFinite(lng)) {
            return;
        }
        var open = false;
        btn.addEventListener('click', function () {
            open = !open;
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            var showLabel = btn.getAttribute('data-label-show') || 'Voir sur la carte';
            var hideLabel = btn.getAttribute('data-label-hide') || 'Masquer la carte';
            btn.textContent = open ? hideLabel : showLabel;
            if (open) {
                ensureMap(canvas, lat, lng, label);
                if (canvas._tmLeafletMap) {
                    setTimeout(function () {
                        canvas._tmLeafletMap.invalidateSize();
                    }, 80);
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-tm-geo-map').forEach(bindRoot);
    });
})();
