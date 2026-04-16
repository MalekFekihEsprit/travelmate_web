/**
 * TravelMate — Dark Mode Toggle
 * Persists preference in localStorage.
 * Reads system preference as default.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'travelmate-theme';
    var html = document.documentElement;

    function getPreferred() {
        var stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
        // Fall back to OS preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    function apply(theme) {
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    }

    function toggle() {
        var current = html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        apply(next);
        localStorage.setItem(STORAGE_KEY, next);
    }

    // Apply immediately (avoids flash)
    apply(getPreferred());

    // Bind toggle buttons once DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        var buttons = document.querySelectorAll('.dark-mode-toggle');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', toggle);
        });
    });

    // Listen for OS preference changes
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
            if (!localStorage.getItem(STORAGE_KEY)) {
                apply(e.matches ? 'dark' : 'light');
            }
        });
    }
})();
