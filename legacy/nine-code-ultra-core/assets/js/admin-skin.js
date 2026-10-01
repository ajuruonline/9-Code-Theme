(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    ready(function () {
        /* Single-line menu labels may ellipsize on narrow admin rails. Preserve
         * the complete label as a native title for pointer users. */
        document.querySelectorAll('#adminmenu .wp-menu-name, #adminmenu .wp-submenu a').forEach(function (node) {
            var anchor = node.matches('a') ? node : node.closest('a');
            var label = (node.textContent || '').trim();
            if (anchor && label && !anchor.getAttribute('title')) anchor.setAttribute('title', label);
        });

        var skinInputs = document.querySelectorAll('[data-ncu-skin-previews] input[name="ncu[admin_skin_mode]"]');
        var skinNames = ['github', 'future', 'chatgpt', 'gemini', 'white_future'];
        if (skinInputs.length) {
            skinInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    if (!input.checked || skinNames.indexOf(input.value) === -1) return;
                    skinNames.forEach(function (name) { document.body.classList.remove('ncu-admin-skin--' + name); });
                    document.body.classList.add('ncu-admin-skin--' + input.value);
                    document.querySelectorAll('[data-ncu-skin-card]').forEach(function (card) {
                        card.classList.toggle('is-selected', card.getAttribute('data-ncu-skin-card') === input.value);
                    });
                });
            });
        }

        var root = document.getElementById('ncu-command-palette');
        if (!root) return;
        var dialog = root.querySelector('.ncu-command-palette__dialog');
        var search = root.querySelector('[data-ncu-command-search]');
        var empty = root.querySelector('[data-ncu-command-empty]');
        var lastFocus = null;
        var activeIndex = -1;

        function visibleItems() {
            return Array.prototype.filter.call(root.querySelectorAll('[data-ncu-command-item]'), function (item) {
                return !item.hidden;
            });
        }

        function setActive(index) {
            var items = visibleItems();
            items.forEach(function (item) { item.classList.remove('is-active'); });
            if (!items.length) {
                activeIndex = -1;
                return;
            }
            if (index < 0) index = items.length - 1;
            if (index >= items.length) index = 0;
            activeIndex = index;
            items[index].classList.add('is-active');
            items[index].scrollIntoView({ block: 'nearest' });
        }

        function refreshGroups() {
            var groups = root.querySelectorAll('[data-ncu-command-group]');
            groups.forEach(function (group) {
                var next = group.nextElementSibling;
                var any = false;
                while (next && !next.hasAttribute('data-ncu-command-group')) {
                    if (next.hasAttribute('data-ncu-command-item') && !next.hidden) any = true;
                    next = next.nextElementSibling;
                }
                group.hidden = !any;
            });
        }

        function filter() {
            var query = (search.value || '').trim().toLowerCase();
            var count = 0;
            root.querySelectorAll('[data-ncu-command-item]').forEach(function (item) {
                var haystack = item.getAttribute('data-search') || '';
                var match = !query || haystack.indexOf(query) !== -1;
                item.hidden = !match;
                if (match) count++;
            });
            refreshGroups();
            if (empty) empty.hidden = count !== 0;
            setActive(count ? 0 : -1);
        }

        function openPalette() {
            if (!root.hidden) return;
            lastFocus = document.activeElement;
            root.hidden = false;
            root.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('ncu-command-open');
            search.value = '';
            filter();
            window.setTimeout(function () { search.focus(); }, 20);
        }

        function closePalette() {
            if (root.hidden) return;
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
            document.documentElement.classList.remove('ncu-command-open');
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        }

        document.addEventListener('click', function (event) {
            var open = event.target.closest('[data-ncu-command-open]');
            if (open) {
                event.preventDefault();
                openPalette();
                return;
            }
            if (event.target.closest('[data-ncu-command-close]')) {
                event.preventDefault();
                closePalette();
            }
        });

        search.addEventListener('input', filter);

        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closePalette();
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
                return;
            }
            if (event.key === 'Tab') {
                var focusable = Array.prototype.slice.call(root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled])')).filter(function (el) { return !el.hidden && el.offsetParent !== null; });
                if (focusable.length) {
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
                return;
            }
            if (event.key === 'Enter') {
                var items = visibleItems();
                if (activeIndex >= 0 && items[activeIndex]) {
                    event.preventDefault();
                    items[activeIndex].click();
                }
            }
        });

        document.addEventListener('keydown', function (event) {
            var target = event.target;
            var typing = target && (target.matches('input,textarea,select,[contenteditable="true"]'));
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && !typing) {
                event.preventDefault();
                if (root.hidden) openPalette(); else closePalette();
            }
        });

        if (dialog) {
            dialog.addEventListener('click', function (event) { event.stopPropagation(); });
        }
    });
})();
