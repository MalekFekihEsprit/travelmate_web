/**
 * Filtres liste activités + carte (Leaflet / OSM) + géocodage inverse (Nominatim).
 * APIs gratuites : tuiles OpenStreetMap, Nominatim (usage modéré, voir https://operations.osmfoundation.org/policies/nominatim/).
 */
(function () {
    'use strict';

    var RADIUS_KM = 45;
    var NOMINATIM = 'https://nominatim.openstreetmap.org/reverse';
    /** Renseignez un e-mail de contact pour respecter la politique Nominatim (requis en production). */
    var NOMINATIM_EMAIL = (typeof window.TRAVELMATE_NOMINATIM_EMAIL === 'string' && window.TRAVELMATE_NOMINATIM_EMAIL)
        ? window.TRAVELMATE_NOMINATIM_EMAIL
        : '';

    var regionMap = {
        tunis: ['tunis', 'la marsa', 'sidi bou said', 'la goulette', 'carthage', 'ariana', 'manouba', 'ben arous', 'hammam lif', 'rades', 'bardo', 'ezzouhour'],
        hammamet: ['hammamet', 'nabeul', 'kelibia', 'korba', 'menzel temime'],
        sousse: ['sousse', 'monastir', 'port el kantaoui', 'chott meriem'],
        sfax: ['sfax', 'jebeniana', 'mahres'],
        djerba: ['djerba', 'houmt souk', 'midoun', 'aghir', 'ajim'],
        tozeur: ['tozeur', 'nefta', 'douz', 'kebili'],
        tabarka: ['tabarka', 'ain draham', 'jendouba'],
        bizerte: ['bizerte', 'menzel bourguiba', 'ras angela']
    };

    var pickState = { lat: null, lng: null, tokens: [], displayLabel: '' };
    var activeDestination = '';
    var mapInstance = null;
    var mapMarker = null;
    var mapInited = false;
    var lastReverseController = null;

    function capitalize(str) {
        return (str || '').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function haversineKm(lat1, lon1, lat2, lon2) {
        var R = 6371;
        var toR = Math.PI / 180;
        var dLat = (lat2 - lat1) * toR;
        var dLon = (lon2 - lon1) * toR;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * toR) * Math.cos(lat2 * toR) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
        return 2 * R * Math.asin(Math.sqrt(Math.min(1, a)));
    }

    function extractTokensFromAddress(addr) {
        if (!addr || typeof addr !== 'object') {
            return [];
        }
        var keys = ['city', 'town', 'village', 'municipality', 'county', 'state', 'region', 'suburb', 'neighbourhood'];
        var tokens = new Set();
        keys.forEach(function (k) {
            var v = addr[k];
            if (!v || typeof v !== 'string') {
                return;
            }
            var lower = v.toLowerCase().trim();
            tokens.add(lower);
            lower.split(/[\s,;/]+/).forEach(function (part) {
                if (part.length >= 3) {
                    tokens.add(part);
                }
            });
        });
        return Array.from(tokens);
    }

    function mergeTokens(baseTokens, extra) {
        var s = new Set(baseTokens || []);
        (extra || []).forEach(function (t) { if (t && t.length >= 2) { s.add(t.toLowerCase()); } });
        return Array.from(s);
    }

    function matchDestString(card, query) {
        if (!query) {
            return true;
        }
        var lieu = card.dataset.lieu || '';
        if (lieu.includes(query)) {
            return true;
        }
        for (var region in regionMap) {
            if (!Object.prototype.hasOwnProperty.call(regionMap, region)) {
                continue;
            }
            var subs = regionMap[region];
            if (region === query || query.includes(region) || region.includes(query)) {
                if (subs.some(function (s) { return lieu.includes(s); }) || lieu.includes(region)) {
                    return true;
                }
            }
            if (subs.includes(query)) {
                if (lieu.includes(query) || lieu.includes(region)) {
                    return true;
                }
            }
        }
        return false;
    }

    function matchDest(card) {
        if (pickState.lat != null && pickState.lng != null) {
            var clat = parseFloat(card.dataset.lat);
            var clng = parseFloat(card.dataset.lng);
            if (!isNaN(clat) && !isNaN(clng)) {
                return haversineKm(pickState.lat, pickState.lng, clat, clng) <= RADIUS_KM;
            }
            var lieu = (card.dataset.lieu || '').toLowerCase();
            if (!lieu) {
                return false;
            }
            var tokens = pickState.tokens || [];
            for (var i = 0; i < tokens.length; i++) {
                var t = tokens[i];
                if (t.length >= 3 && lieu.includes(t)) {
                    return true;
                }
            }
            if (pickState.displayLabel) {
                var lbl = pickState.displayLabel.toLowerCase();
                if (lbl.length >= 3 && lieu.includes(lbl)) {
                    return true;
                }
                var parts = lbl.split(/[\s,]+/).filter(function (p) { return p.length >= 3; });
                for (var j = 0; j < parts.length; j++) {
                    if (lieu.includes(parts[j])) {
                        return true;
                    }
                }
            }
            return false;
        }
        return matchDestString(card, activeDestination);
    }

    function getAllCards() {
        return Array.from(document.querySelectorAll('#actGrid .act-card'));
    }

    function updateDestBanner(allCards) {
        var banner = document.getElementById('destBanner');
        var bannerText = document.getElementById('destBannerText');
        if (!banner || !bannerText) {
            return;
        }
        var hasPick = pickState.lat != null && pickState.lng != null;
        var hasText = !!activeDestination;
        if (!hasPick && !hasText) {
            banner.classList.remove('is-visible');
            return;
        }
        var count = allCards.filter(function (c) { return matchDest(c); }).length;
        var label = hasPick ? pickState.displayLabel : capitalize(activeDestination);
        if (hasPick) {
            bannerText.innerHTML = '<strong>' + count + ' activité' + (count > 1 ? 's' : '') + '</strong> trouvée' + (count > 1 ? 's' : '') +
                ' près de <strong>' + label + '</strong> <span style="opacity:.85">(rayon ≈ ' + RADIUS_KM + ' km)</span>';
        } else {
            bannerText.innerHTML = '<strong>' + count + ' activité' + (count > 1 ? 's' : '') + '</strong> trouvée' + (count > 1 ? 's' : '') +
                ' à <strong>' + label + '</strong> et ses alentours';
        }
        banner.classList.add('is-visible');
    }

    function applyDestination(query) {
        pickState = { lat: null, lng: null, tokens: [], displayLabel: '' };
        activeDestination = (query || '').trim().toLowerCase();
        if (mapMarker && mapInstance) {
            mapInstance.removeLayer(mapMarker);
            mapMarker = null;
        }
        filter();
        updateDestBanner(getAllCards());
    }

    function applyMapPick(lat, lng, displayLabel, tokens) {
        activeDestination = '';
        pickState = {
            lat: lat,
            lng: lng,
            tokens: tokens || [],
            displayLabel: displayLabel || ''
        };
        var destInput = document.getElementById('destInput');
        if (destInput) {
            destInput.value = displayLabel || (lat.toFixed(4) + ', ' + lng.toFixed(4));
        }
        filter();
        updateDestBanner(getAllCards());
    }

    function reverseGeocode(lat, lng) {
        var params = new URLSearchParams({
            format: 'jsonv2',
            lat: String(lat),
            lon: String(lng),
            'accept-language': 'fr'
        });
        if (NOMINATIM_EMAIL) {
            params.set('email', NOMINATIM_EMAIL);
        }
        var url = NOMINATIM + '?' + params.toString();
        if (lastReverseController) {
            lastReverseController.abort();
        }
        lastReverseController = new AbortController();
        return fetch(url, {
            signal: lastReverseController.signal,
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('Nominatim ' + res.status);
            }
            return res.json();
        }).then(function (data) {
            var addr = data.address || {};
            var label = addr.city || addr.town || addr.village || addr.municipality || addr.county || addr.state || data.display_name || '';
            if (typeof label !== 'string') {
                label = String(label);
            }
            if (label.length > 80) {
                label = label.split(',')[0].trim();
            }
            var tokens = extractTokensFromAddress(addr);
            var country = (addr.country || '').toLowerCase();
            if (country) {
                tokens = mergeTokens(tokens, [country]);
            }
            return { label: label || 'Point sélectionné', tokens: tokens };
        }).catch(function () {
            return { label: 'Point sélectionné', tokens: [] };
        });
    }

    function ensureMap() {
        if (mapInited || typeof L === 'undefined') {
            return;
        }
        var el = document.getElementById('destMapContainer');
        if (!el) {
            return;
        }
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
        });
        mapInstance = L.map('destMapContainer', { scrollWheelZoom: true }).setView([34.0, 9.0], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(mapInstance);
        mapInstance.on('click', function (e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;
            if (mapMarker) {
                mapInstance.removeLayer(mapMarker);
            }
            mapMarker = L.marker([lat, lng]).addTo(mapInstance);
            reverseGeocode(lat, lng).then(function (r) {
                applyMapPick(lat, lng, r.label, r.tokens);
            });
        });
        mapInited = true;
    }

    function openMapPanel(panel) {
        if (!panel) {
            return;
        }
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        var toggle = document.getElementById('destMapToggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
        ensureMap();
        if (mapInstance) {
            setTimeout(function () {
                mapInstance.invalidateSize();
            }, 200);
        }
    }

    function closeMapPanel(panel) {
        if (!panel) {
            return;
        }
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        var toggle = document.getElementById('destMapToggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function filter() {
        var searchInput = document.getElementById('searchInput');
        var diffFilter = document.getElementById('diffFilter');
        var budgetFilter = document.getElementById('budgetFilter');
        var countEl = document.getElementById('countNum');
        var allCards = getAllCards();
        var q = (searchInput && searchInput.value) ? searchInput.value.toLowerCase().trim() : '';
        var diff = (diffFilter && diffFilter.value) ? diffFilter.value.toLowerCase() : '';
        var budget = budgetFilter ? budgetFilter.value : '';
        var activeCat = '';
        document.querySelectorAll('.cat-chip.is-active').forEach(function (chip) {
            activeCat = chip.dataset.cat || '';
        });
        var visible = 0;
        allCards.forEach(function (card) {
            var matchNom = !q || (card.dataset.nom && card.dataset.nom.includes(q));
            var matchCat = !activeCat || card.dataset.cat === activeCat;
            var matchDiff = !diff || (card.dataset.diff && card.dataset.diff.toLowerCase() === diff);
            var b = parseInt(card.dataset.budget, 10) || 0;
            var matchBudget = !budget ||
                (budget === '0-50' && b <= 50) ||
                (budget === '50-150' && b > 50 && b <= 150) ||
                (budget === '150+' && b > 150);
            var matchLieu = matchDest(card);
            var show = matchNom && matchCat && matchDiff && matchBudget && matchLieu;
            card.style.display = show ? '' : 'none';
            if (show) {
                visible++;
            }
        });
        if (countEl) {
            countEl.textContent = visible;
        }
    }

    function init() {
        var destInput = document.getElementById('destInput');
        var suggestions = document.getElementById('destSuggestions');
        var destMapPanel = document.getElementById('destMapPanel');
        var destMapClose = document.getElementById('destMapClose');
        var destMapToggle = document.getElementById('destMapToggle');
        var allCards = getAllCards();
        var allLieux = [];
        var seen = {};
        allCards.forEach(function (c) {
            var l = c.dataset.lieu;
            if (l && !seen[l]) {
                seen[l] = true;
                allLieux.push(l);
            }
        });

        function buildSuggestions(query) {
            var q = query.toLowerCase().trim();
            if (!suggestions) {
                return;
            }
            if (!q) {
                suggestions.classList.remove('is-open');
                return;
            }
            var matches = new Set();
            allLieux.forEach(function (lieu) {
                if (lieu.includes(q)) {
                    matches.add(lieu);
                }
            });
            Object.keys(regionMap).forEach(function (region) {
                if (region.includes(q)) {
                    regionMap[region].forEach(function (sub) { matches.add(sub); });
                    matches.add(region);
                }
                regionMap[region].forEach(function (sub) {
                    if (sub.includes(q)) {
                        matches.add(sub);
                    }
                });
            });
            var items = Array.from(matches).slice(0, 6);
            if (!items.length) {
                suggestions.classList.remove('is-open');
                return;
            }
            suggestions.innerHTML = items.map(function (lieu) {
                return '<div class="dest-suggestion" data-lieu="' + lieu.replace(/"/g, '&quot;') + '">' +
                    '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/></svg>' +
                    '<div><div class="dest-suggestion__main">' + capitalize(lieu) + '</div></div></div>';
            }).join('');
            suggestions.querySelectorAll('.dest-suggestion').forEach(function (el) {
                el.addEventListener('click', function () {
                    destInput.value = capitalize(el.dataset.lieu);
                    suggestions.classList.remove('is-open');
                    applyDestination(el.dataset.lieu);
                });
            });
            suggestions.classList.add('is-open');
        }

        if (destInput) {
            destInput.addEventListener('input', function () { buildSuggestions(destInput.value); });
            destInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    if (suggestions) {
                        suggestions.classList.remove('is-open');
                    }
                    applyDestination(destInput.value.toLowerCase().trim());
                }
            });
            destInput.addEventListener('focus', function () {
                if (destMapPanel) {
                    openMapPanel(destMapPanel);
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.dest-input-wrap') && suggestions) {
                suggestions.classList.remove('is-open');
            }
        });

        if (destMapClose && destMapPanel) {
            destMapClose.addEventListener('click', function () {
                closeMapPanel(destMapPanel);
            });
        }
        if (destMapToggle && destMapPanel) {
            destMapToggle.addEventListener('click', function () {
                if (destMapPanel.classList.contains('is-open')) {
                    closeMapPanel(destMapPanel);
                } else {
                    openMapPanel(destMapPanel);
                }
            });
        }

        var destBannerClear = document.getElementById('destBannerClear');
        if (destBannerClear) {
            destBannerClear.addEventListener('click', function () {
                activeDestination = '';
                pickState = { lat: null, lng: null, tokens: [], displayLabel: '' };
                if (destInput) {
                    destInput.value = '';
                }
                if (mapMarker && mapInstance) {
                    mapInstance.removeLayer(mapMarker);
                    mapMarker = null;
                }
                filter();
                var b = document.getElementById('destBanner');
                if (b) {
                    b.classList.remove('is-visible');
                }
            });
        }

        var searchInput = document.getElementById('searchInput');
        var diffFilter = document.getElementById('diffFilter');
        var budgetFilter = document.getElementById('budgetFilter');
        var catChips = document.querySelectorAll('.cat-chip');
        if (searchInput) {
            searchInput.addEventListener('input', filter);
        }
        if (diffFilter) {
            diffFilter.addEventListener('change', filter);
        }
        if (budgetFilter) {
            budgetFilter.addEventListener('change', filter);
        }
        catChips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                catChips.forEach(function (c) { c.classList.remove('is-active'); });
                chip.classList.add('is-active');
                filter();
            });
        });

        var heroLink = document.querySelector('a[href="#activites"]');
        if (heroLink) {
            heroLink.addEventListener('click', function (e) {
                e.preventDefault();
                var sec = document.getElementById('activites');
                if (sec) {
                    sec.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
