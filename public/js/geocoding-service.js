/**
 * Service de géocodage pour convertir les adresses en coordonnées
 * Utilise l'API Nominatim d'OpenStreetMap (gratuite et sans clé API)
 */
(function() {
    'use strict';

    window.TravelMateGeocoding = {
        /**
         * Convertit une adresse en coordonnées GPS
         * @param {string} address - L'adresse à géocoder
         * @returns {Promise<{lat: number, lng: number}|null>} - Coordonnées ou null si erreur
         */
        geocodeAddress: function(address) {
            return new Promise(function(resolve, reject) {
                if (!address || address.trim().length === 0) {
                    resolve(null);
                    return;
                }

                var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + 
                         encodeURIComponent(address) + '&limit=1';

                fetch(url)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        if (data && data.length > 0) {
                            var result = data[0];
                            resolve({
                                lat: parseFloat(result.lat),
                                lng: parseFloat(result.lon)
                            });
                        } else {
                            resolve(null);
                        }
                    })
                    .catch(function(error) {
                        console.warn('Geocoding failed for address:', address, error);
                        resolve(null);
                    });
            });
        },

        /**
         * Vérifie si des coordonnées sont valides
         * @param {number} lat 
         * @param {number} lng 
         * @returns {boolean}
         */
        isValidCoordinates: function(lat, lng) {
            return isFinite(lat) && isFinite(lng) && 
                   lat >= -90 && lat <= 90 && 
                   lng >= -180 && lng <= 180;
        }
    };
})();
