/**
 * TravelMate — traduction front via MyMemory (API publique gratuite, sans compte ni paiement).
 * @see https://mymemory.translated.net/doc/spec.php
 */
(function (global) {
    'use strict';

    var API_URL = 'https://api.mymemory.translated.net/get';
    /** Limite conservative pour le plan gratuit (évite les erreurs serveur sur longues chaînes). */
    var MAX_CHUNK = 400;
    /** Délai entre requêtes : l’API gratuite refuse souvent les rafales (comme en Java). */
    var REQUEST_GAP_MS = 450;
    var MAX_RETRIES = 4;
    var RETRY_BASE_MS = 500;

    /** Codes cibles acceptés par MyMemory (ISO court). */
    var MYMEMORY_TARGET = { fr: 'fr', en: 'en', ar: 'ar', de: 'de', es: 'es', it: 'it' };

    function sleep(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function splitTextForApi(text) {
        var t = (text || '').trim();
        if (!t) {
            return [];
        }
        if (t.length <= MAX_CHUNK) {
            return [t];
        }
        var parts = [];
        var rest = t;
        while (rest.length > 0) {
            if (rest.length <= MAX_CHUNK) {
                parts.push(rest.trim());
                break;
            }
            var chunk = rest.slice(0, MAX_CHUNK);
            var cut = chunk.lastIndexOf('\n\n');
            if (cut < MAX_CHUNK * 0.2) {
                cut = chunk.lastIndexOf('. ');
            }
            if (cut < MAX_CHUNK * 0.2) {
                cut = chunk.lastIndexOf(' ');
            }
            if (cut < MAX_CHUNK * 0.2) {
                cut = MAX_CHUNK;
            }
            var piece = rest.slice(0, cut + 1).trim();
            parts.push(piece);
            rest = rest.slice(cut + 1).trim();
        }
        return parts.filter(Boolean);
    }

    function isMyMemoryErrorMessage(s) {
        return typeof s === 'string' && s.indexOf('MYMEMORY') !== -1;
    }

    function isResponseOk(data) {
        if (!data || data.quotaFinished) {
            return false;
        }
        var st = data.responseStatus;
        return st === 200 || st === '200' || Number(st) === 200;
    }

    var TravelMateTranslate = {
        storageKey: 'travelmate_lang',
        sourceLang: 'fr',
        root: null,
        translationCache: {},
        originalTexts: new Map(),
        currentLang: 'fr',
        _lastRequest: 0,

        init: function (opts) {
            opts = opts || {};
            this.sourceLang = opts.sourceLang || 'fr';
            this.storageKey = opts.storageKey || 'travelmate_lang';
            var r = opts.root || opts.rootSelector;
            this.root = typeof r === 'string' ? document.querySelector(r) : (r || document);
            if (!this.root) {
                this.root = document;
            }
            this.currentLang = localStorage.getItem(this.storageKey) || this.sourceLang;
            this.translationCache = {};
            this.originalTexts = new Map();

            this._exposeGlobals();
            this.saveOriginals();
            this._bindOutsideClick();

            var self = this;
            if (this.currentLang !== this.sourceLang) {
                document.querySelectorAll('.lang-option').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.dataset.lang === self.currentLang);
                });
                this.translatePage(this.currentLang);
            }
        },

        _exposeGlobals: function () {
            var self = this;
            global.setLanguage = function (lang) {
                return self.setLanguage(lang);
            };
            global.toggleLangPanel = function () {
                self.toggleLangPanel();
            };
        },

        _bindOutsideClick: function () {
            var self = this;
            document.addEventListener('click', function (e) {
                if (!e.target.closest('#langSwitcher')) {
                    self.closeLangPanel();
                }
            });
        },

        saveOriginals: function () {
            var self = this;
            this.root.querySelectorAll('[data-translate="true"]').forEach(function (el) {
                if (self.originalTexts.has(el)) {
                    return;
                }
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    var ph = el.getAttribute('placeholder');
                    if (ph) {
                        self.originalTexts.set(el, ph);
                    }
                    return;
                }
                var original = (el.dataset.original != null && el.dataset.original !== '')
                    ? el.dataset.original
                    : el.textContent.trim();
                self.originalTexts.set(el, original);
            });
        },

        _throttle: async function () {
            var now = Date.now();
            var wait = this._lastRequest + REQUEST_GAP_MS - now;
            if (wait > 0) {
                await sleep(wait);
            }
            this._lastRequest = Date.now();
        },

        translateChunk: async function (text, targetLang) {
            if (!text || !text.trim() || targetLang === this.sourceLang) {
                return text;
            }
            var memTgt = MYMEMORY_TARGET[targetLang] || targetLang;
            var cacheKey = text.trim() + '|' + memTgt;
            if (this.translationCache[cacheKey]) {
                return this.translationCache[cacheKey];
            }
            var trimmed = text.trim();
            var langpair = encodeURIComponent(this.sourceLang + '|' + memTgt);
            var lastErr = null;

            for (var attempt = 0; attempt < MAX_RETRIES; attempt++) {
                if (attempt > 0) {
                    await sleep(RETRY_BASE_MS * attempt);
                }
                await this._throttle();
                try {
                    var url = API_URL + '?q=' + encodeURIComponent(trimmed)
                        + '&langpair=' + langpair;
                    var res = await fetch(url);
                    if (!res.ok) {
                        lastErr = 'HTTP ' + res.status;
                        continue;
                    }
                    var data = await res.json();
                    if (!isResponseOk(data)) {
                        lastErr = data && data.responseDetails ? data.responseDetails : 'bad response';
                        continue;
                    }
                    var rd = data.responseData;
                    if (!rd || typeof rd.translatedText !== 'string') {
                        lastErr = 'no translatedText';
                        continue;
                    }
                    var out = rd.translatedText;
                    if (!out.length && trimmed.length) {
                        lastErr = 'empty translation';
                        continue;
                    }
                    if (isMyMemoryErrorMessage(out)) {
                        lastErr = 'quota or API message in body';
                        continue;
                    }
                    this.translationCache[cacheKey] = out;
                    return out;
                } catch (e) {
                    lastErr = e;
                    console.warn('MyMemory tentative ' + (attempt + 1) + '/' + MAX_RETRIES + ':', e);
                }
            }
            if (lastErr) {
                console.warn('MyMemory: abandon après ' + MAX_RETRIES + ' essais —', lastErr);
            }
            return text;
        },

        translateText: async function (text, targetLang) {
            var self = this;
            if (!text || !text.trim() || targetLang === this.sourceLang) {
                return text;
            }
            var memTgt = MYMEMORY_TARGET[targetLang] || targetLang;
            var fullKey = text.trim() + '|' + memTgt;
            if (this.translationCache[fullKey]) {
                return this.translationCache[fullKey];
            }
            var parts = splitTextForApi(text);
            if (parts.length === 1) {
                var single = await this.translateChunk(parts[0], targetLang);
                this.translationCache[fullKey] = single;
                return single;
            }
            var out = [];
            for (var i = 0; i < parts.length; i++) {
                out.push(await this.translateChunk(parts[i], targetLang));
            }
            var joined = out.join(' ').trim();
            this.translationCache[fullKey] = joined;
            return joined;
        },

        translatePage: async function (lang) {
            var bar = document.getElementById('translateLoadingBar');
            if (bar) {
                bar.classList.remove('is-done');
                bar.classList.add('is-loading');
            }

            this.saveOriginals();

            var all = Array.from(this.root.querySelectorAll('[data-translate="true"]'));

            var uniqueTexts = [];
            var seen = {};
            all.forEach(function (el) {
                var o = TravelMateTranslate.originalTexts.get(el);
                if (o && !seen[o]) {
                    seen[o] = true;
                    uniqueTexts.push(o);
                }
            });

            for (var i = 0; i < uniqueTexts.length; i++) {
                await this.translateText(uniqueTexts[i], lang);
            }

            var self = this;
            all.forEach(function (el) {
                var original = self.originalTexts.get(el);
                if (original == null) {
                    return;
                }
                var memLang = MYMEMORY_TARGET[lang] || lang;
                var cacheKey = original + '|' + memLang;
                var translated = lang === self.sourceLang
                    ? original
                    : (self.translationCache[cacheKey] || original);

                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.setAttribute('placeholder', translated);
                } else if (el.tagName === 'OPTION') {
                    el.textContent = translated;
                } else {
                    el.textContent = translated;
                }
            });

            document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
            document.documentElement.setAttribute('lang', lang);

            if (bar) {
                bar.classList.add('is-done');
                setTimeout(function () {
                    bar.classList.remove('is-loading', 'is-done');
                }, 500);
            }
        },

        setLanguage: async function (lang) {
            this.currentLang = lang;
            localStorage.setItem(this.storageKey, lang);
            document.querySelectorAll('.lang-option').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.dataset.lang === lang);
            });
            await this.translatePage(lang);
            this.closeLangPanel();
        },

        toggleLangPanel: function () {
            var panel = document.getElementById('langPanel');
            if (panel) {
                panel.classList.toggle('is-open');
            }
        },

        closeLangPanel: function () {
            var panel = document.getElementById('langPanel');
            if (panel) {
                panel.classList.remove('is-open');
            }
        }
    };

    global.TravelMateTranslate = TravelMateTranslate;
})(typeof window !== 'undefined' ? window : this);
