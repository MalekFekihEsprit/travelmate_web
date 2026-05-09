/**
 * TravelMate Translation System
 * Google Translate API
 */

(function (global) {
    'use strict';

    const GT_URL = 'https://translate.googleapis.com/translate_a/single';

    const REQUEST_GAP_MS = 120;
    const MAX_RETRIES = 3;
    const RETRY_BASE_MS = 400;
    const MAX_CHUNK = 500;

    const LANG_MAP = {
        fr: 'fr',
        en: 'en',
        ar: 'ar',
        de: 'de',
        es: 'es',
        it: 'it'
    };

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function splitText(text) {
        const t = (text || '').trim();

        if (!t) return [];

        if (t.length <= MAX_CHUNK) {
            return [t];
        }

        const parts = [];
        let rest = t;

        while (rest.length > 0) {

            if (rest.length <= MAX_CHUNK) {
                parts.push(rest.trim());
                break;
            }

            let chunk = rest.slice(0, MAX_CHUNK);

            let cut =
                chunk.lastIndexOf('. ') ||
                chunk.lastIndexOf(' ') ||
                MAX_CHUNK;

            if (cut < MAX_CHUNK * 0.3) {
                cut = MAX_CHUNK;
            }

            parts.push(rest.slice(0, cut + 1).trim());

            rest = rest.slice(cut + 1).trim();
        }

        return parts.filter(Boolean);
    }

    const TravelMateTranslate = {

        storageKey: 'travelmate_lang',
        cacheKey: 'travelmate_cache',

        sourceLang: 'fr',
        currentLang: 'fr',

        root: document,

        translateAll: false,

        translationCache: {},

        originalTexts: new Map(),

        _lastRequest: 0,

        init(opts = {}) {

            this.sourceLang = opts.sourceLang || 'fr';

            this.storageKey = opts.storageKey || 'travelmate_lang';

            this.cacheKey = opts.cacheKey || 'travelmate_cache';

            this.translateAll = !!opts.translateAll;

            const r = opts.root || opts.rootSelector;

            this.root =
                typeof r === 'string'
                    ? document.querySelector(r)
                    : (r || document);

            this.currentLang =
                localStorage.getItem(this.storageKey) || this.sourceLang;

            this.translationCache = this._loadCache();

            this.saveOriginals();

            this._bindOutsideClick();

            this._exposeGlobals();

            if (this.currentLang !== this.sourceLang) {
                this.translatePage(this.currentLang);
            }
        },

        _exposeGlobals() {

            const self = this;

            global.setLanguage = function (lang) {
                return self.setLanguage(lang);
            };

            global.toggleLangPanel = function () {
                return self.toggleLangPanel();
            };
        },

        _bindOutsideClick() {

            const self = this;

            document.addEventListener('click', function (e) {

                const wrapper =
                    document.getElementById('navbarLang') ||
                    document.getElementById('langSwitcher');

                if (!wrapper) return;

                if (!wrapper.contains(e.target)) {
                    self.closeLangPanel();
                }
            });
        },

        _loadCache() {

            try {

                const raw = localStorage.getItem(this.cacheKey);

                return raw ? JSON.parse(raw) : {};

            } catch (e) {

                return {};
            }
        },

        _saveCache() {

            try {

                localStorage.setItem(
                    this.cacheKey,
                    JSON.stringify(this.translationCache)
                );

            } catch (e) {}
        },

        _getElements() {

            if (!this.translateAll) {

                return Array.from(
                    this.root.querySelectorAll('[data-translate="true"]')
                );
            }

            const scope =
                this.root.querySelector('main, .app-main')
                || this.root;

            return Array.from(
                scope.querySelectorAll(
                    'h1,h2,h3,h4,h5,h6,p,span,a,button,li,label,td,th'
                )
            ).filter(el => {

                if (el.children.length > 0) return false;

                const text = el.textContent.trim();

                if (!text || text.length < 2) return false;

                if (/^[\d\s\W]+$/.test(text)) return false;

                if (el.closest('[data-no-translate]')) return false;

                return true;
            });
        },

        saveOriginals() {

            const self = this;

            this._getElements().forEach(el => {

                if (self.originalTexts.has(el)) return;

                if (
                    el.tagName === 'INPUT' ||
                    el.tagName === 'TEXTAREA'
                ) {

                    const ph = el.getAttribute('placeholder');

                    if (ph) {
                        self.originalTexts.set(el, ph);
                    }

                    return;
                }

                self.originalTexts.set(
                    el,
                    el.textContent.trim()
                );
            });
        },

        _applyEl(el, text) {

            if (!el || text == null) return;

            if (
                el.tagName === 'INPUT' ||
                el.tagName === 'TEXTAREA'
            ) {

                el.setAttribute('placeholder', text);

            } else {

                el.textContent = text;
            }
        },

        async _throttle() {

            const now = Date.now();

            const wait =
                this._lastRequest + REQUEST_GAP_MS - now;

            if (wait > 0) {
                await sleep(wait);
            }

            this._lastRequest = Date.now();
        },

        async translateChunk(text, targetLang) {

            if (
                !text ||
                !text.trim() ||
                targetLang === this.sourceLang
            ) {

                return text;
            }

            const tgt = LANG_MAP[targetLang] || targetLang;

            const src = LANG_MAP[this.sourceLang] || this.sourceLang;

            const cacheKey = text.trim() + '|' + tgt;

            if (this.translationCache[cacheKey]) {
                return this.translationCache[cacheKey];
            }

            for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {

                try {

                    await this._throttle();

                    const url =
                        GT_URL +
                        '?client=gtx' +
                        '&sl=' + src +
                        '&tl=' + tgt +
                        '&dt=t&q=' +
                        encodeURIComponent(text);

                    const response = await fetch(url);

                    if (!response.ok) {
                        continue;
                    }

                    const data = await response.json();

                    let translated = '';

                    if (
                        Array.isArray(data) &&
                        Array.isArray(data[0])
                    ) {

                        data[0].forEach(part => {

                            if (
                                part &&
                                typeof part[0] === 'string'
                            ) {

                                translated += part[0];
                            }
                        });
                    }

                    if (!translated) {
                        continue;
                    }

                    this.translationCache[cacheKey] = translated;

                    return translated;

                } catch (e) {

                    console.warn('Translation error:', e);

                    await sleep(RETRY_BASE_MS);
                }
            }

            return text;
        },

        async translateText(text, targetLang) {

            if (
                !text ||
                targetLang === this.sourceLang
            ) {

                return text;
            }

            const parts = splitText(text);

            if (parts.length === 1) {

                return await this.translateChunk(
                    parts[0],
                    targetLang
                );
            }

            const translated = [];

            for (const part of parts) {

                translated.push(
                    await this.translateChunk(part, targetLang)
                );
            }

            return translated.join(' ');
        },

        async translatePage(lang) {

            this.saveOriginals();

            const elements = this._getElements();

            const self = this;

            for (const el of elements) {

                const original =
                    self.originalTexts.get(el);

                if (!original) continue;

                if (lang === self.sourceLang) {

                    self._applyEl(el, original);

                    continue;
                }

                const translated =
                    await self.translateText(original, lang);

                self._applyEl(el, translated);
            }

            this._saveCache();

            document.documentElement.setAttribute(
                'lang',
                lang
            );

            document.documentElement.setAttribute(
                'dir',
                lang === 'ar' ? 'rtl' : 'ltr'
            );
        },

        async setLanguage(lang) {

            this.currentLang = lang;

            localStorage.setItem(this.storageKey, lang);

            // Update active state on language buttons
            document.querySelectorAll('.lang-option').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-lang') === lang);
            });

            await this.translatePage(lang);

            this.closeLangPanel();
        },

        toggleLangPanel() {

            const panel   = document.getElementById('langPanel');
            const wrapper = document.getElementById('navbarLang');
            const btn     = document.getElementById('langToggleBtn');

            if (!panel) return;

            const isOpen = panel.classList.toggle('is-open');
            if (wrapper) wrapper.classList.toggle('is-open', isOpen);
            if (btn)     btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        },

        closeLangPanel() {

            const panel   = document.getElementById('langPanel');
            const wrapper = document.getElementById('navbarLang');
            const btn     = document.getElementById('langToggleBtn');

            if (panel)   panel.classList.remove('is-open');
            if (wrapper) wrapper.classList.remove('is-open');
            if (btn)     btn.setAttribute('aria-expanded', 'false');
        }
    };

    global.TravelMateTranslate = TravelMateTranslate;

})(window);