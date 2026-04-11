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
        var address = root.getAttribute('data-address') || '';
        
        // Si pas de coordonnées mais une adresse, on fera le géocodage au clic
        var needsGeocoding = !isFinite(lat) || !isFinite(lng);
        var isGeocoding = false;
        var open = false;
        btn.addEventListener('click', function () {
            if (open) {
                // Fermeture
                open = false;
                panel.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
                var showLabel = btn.getAttribute('data-label-show') || 'Voir sur la carte';
                btn.textContent = showLabel;
                return;
            }

            // Ouverture
            open = true;
            panel.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            var hideLabel = btn.getAttribute('data-label-hide') || 'Masquer la carte';
            
            if (needsGeocoding && !isGeocoding && address) {
                // Géocodage dynamique
                isGeocoding = true;
                btn.textContent = 'Recherche en cours...';
                btn.disabled = true;
                
                if (window.TravelMateGeocoding) {
                    window.TravelMateGeocoding.geocodeAddress(address)
                        .then(function(coords) {
                            isGeocoding = false;
                            btn.disabled = false;
                            
                            if (coords && window.TravelMateGeocoding.isValidCoordinates(coords.lat, coords.lng)) {
                                // Mettre à jour les coordonnées et créer la carte
                                lat = coords.lat;
                                lng = coords.lng;
                                ensureMap(canvas, lat, lng, label);
                                btn.textContent = hideLabel;
                                
                                if (canvas._tmLeafletMap) {
                                    setTimeout(function () {
                                        canvas._tmLeafletMap.invalidateSize();
                                    }, 80);
                                }
                            } else {
                                // Erreur de géocodage
                                btn.textContent = 'Adresse non trouvée';
                                panel.innerHTML = '<div style="padding: 1rem; color: #666; text-align: center;">Impossible de trouver cette adresse sur la carte</div>';
                            }
                        });
                } else {
                    // Service de géocodage non disponible
                    isGeocoding = false;
                    btn.disabled = false;
                    btn.textContent = 'Service indisponible';
                    panel.innerHTML = '<div style="padding: 1rem; color: #666; text-align: center;">Service de géocodage indisponible</div>';
                }
            } else if (isFinite(lat) && isFinite(lng)) {
                // Coordonnées déjà disponibles
                btn.textContent = hideLabel;
                ensureMap(canvas, lat, lng, label);
                if (canvas._tmLeafletMap) {
                    setTimeout(function () {
                        canvas._tmLeafletMap.invalidateSize();
                    }, 80);
                }
            } else {
                // Pas d'adresse ni de coordonnées
                btn.textContent = 'Adresse manquante';
                panel.innerHTML = '<div style="padding: 1rem; color: #666; text-align: center;">Aucune adresse spécifiée</div>';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-tm-geo-map').forEach(bindRoot);
    });
})();
