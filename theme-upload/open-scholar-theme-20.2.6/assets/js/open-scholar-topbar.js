/* Open Scholar Theme v20.2.1 — preserve toolbar functionality without a top strip. */
(function () {
    'use strict';
    var originalToolbarActions = [];
    var activePopup = null;
    var returnFocus = null;

    function $(selector, scope) { return (scope || document).querySelector(selector); }
    function $all(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
    function launcherFor(id) { return $('[data-n9be-' + (id === 'n9be-topbar-popup' ? 'topbar' : 'search') + '-toggle]'); }

    function closePopup(restoreFocus) {
        if (!activePopup) return;
        var panel = activePopup;
        activePopup = null;
        panel.hidden = true;
        document.body.classList.remove('n9be-side-popup-open');
        var launcher = launcherFor(panel.id);
        if (launcher) launcher.setAttribute('aria-expanded', 'false');
        if (restoreFocus && returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
    }

    function openPopup(id) {
        var popup = document.getElementById(id);
        if (!popup) return;
        if (activePopup === popup) { closePopup(true); return; }
        closePopup(false);
        var quickDrawerClose = $('[data-n9be-quick-close]', $('#n9be-quick-drawer'));
        if (quickDrawerClose && !$('#n9be-quick-drawer').hidden) quickDrawerClose.click();
        var navClose = $('#osch-nav-dialog:not([hidden]) .osch-nav-close');
        if (navClose) navClose.click();
        returnFocus = launcherFor(id);
        activePopup = popup;
        popup.hidden = false;
        document.body.classList.add('n9be-side-popup-open');
        if (returnFocus) returnFocus.setAttribute('aria-expanded', 'true');
        if (id === 'n9be-topbar-popup') populateToolbarActions();
        if (id === 'n9be-search-popup') populateApplicationResults();
        var focusable = $('input:not([disabled]),button:not([disabled]),a[href]', $('.n9be-side-panel', popup));
        if (focusable) focusable.focus();
    }

    function textForToolbarLink(link) {
        var label = (link.textContent || link.getAttribute('aria-label') || link.title || '').replace(/\s+/g, ' ').trim();
        if (label) return label;
        var id = link.closest('[id^="wp-admin-bar-"]');
        return id ? id.id.replace(/^wp-admin-bar-/, '').replace(/[-_]/g, ' ') : '';
    }

    function createToolbarButton(link, destination) {
        var label = textForToolbarLink(link);
        if (!label) return;
        var entry = document.createElement('button');
        entry.type = 'button';
        entry.textContent = label;
        entry.className = 'n9be-toolbar-cloned-action';
        entry.setAttribute('data-topbar-action-index', String(originalToolbarActions.length));
        originalToolbarActions.push(link);
        destination.appendChild(entry);
    }

    /* Reuse the original permitted WordPress toolbar links and handlers. We do
     * not invent a cache endpoint, bypass nonces, or claim a cache was cleared. */
    function populateToolbarActions() {
        var cache = $('#n9be-topbar-cache');
        var other = $('#n9be-topbar-extra');
        if (!cache || !other) return;
        originalToolbarActions = [];
        cache.replaceChildren();
        other.replaceChildren();
        var seen = Object.create(null);
        var links = $all('#wpadminbar a.ab-item');
        links.forEach(function (link) {
            var label = textForToolbarLink(link);
            var href = link.getAttribute('href') || '';
            var parent = link.closest('[id^="wp-admin-bar-"]');
            var id = parent ? parent.id : '';
            var namedCacheAction = /clear|purge|flush|invalidate|empty/i.test(label + ' ' + id);
            if (!label || (!href && !namedCacheAction) || (href === '#' && !namedCacheAction)) return;
            if (/wp-admin-bar-(wp-logo|menu-toggle|search|top-secondary|my-account|logout|site-name|new-content|dashboard)/i.test(id)) return;
            if (/^(about open scholar|documentation|support|feedback)$/i.test(label)) return;
            var key = label.toLowerCase() + '|' + href;
            if (seen[key]) return;
            seen[key] = true;
            if (/(?:clear|purge|flush|invalidate|empty).{0,30}(?:cache|cdn|assets|storage)|(?:cache|cdn).{0,30}(?:clear|purge|flush|invalidate)|cache|performance/i.test(label + ' ' + id)) {
                createToolbarButton(link, cache);
            } else {
                createToolbarButton(link, other);
            }
        });
        if (!cache.children.length) {
            var message = document.createElement('p');
            message.className = 'n9be-tool-empty';
            message.textContent = 'No caching plugin has exposed a toolbar action. Clear Cache will appear here when available.';
            cache.appendChild(message);
        }
        if (!other.children.length) {
            var placeholder = document.createElement('p');
            placeholder.className = 'n9be-tool-empty';
            placeholder.textContent = 'No additional toolbar commands.';
            other.appendChild(placeholder);
        }
    }

    function populateApplicationResults() {
        var results = $('#n9be-search-apps-results');
        var search = $('#n9be-search-apps-input');
        if (!results || !search) return;
        results.replaceChildren();
        var query = search.value.replace(/\s+/g, ' ').trim().toLowerCase();
        if (!query) {
            var prompt = document.createElement('p');
            prompt.className = 'n9be-tool-empty';
            prompt.textContent = 'Type an app or setting name to see matching destinations.';
            results.appendChild(prompt);
            return;
        }
        var matches = 0;
        var seen = Object.create(null);
        $all('#osch-nav-dialog #adminmenu a[href]').forEach(function (item) {
            var title = (item.textContent || '').replace(/\s+/g, ' ').trim();
            var href = item.href;
            if (!title || !href || title.toLowerCase().indexOf(query) === -1 || seen[href]) return;
            seen[href] = true;
            if (matches >= 16) return;
            var result = document.createElement('a');
            result.href = href;
            result.textContent = title;
            results.appendChild(result);
            matches++;
        });
        if (!matches) {
            var empty = document.createElement('p');
            empty.className = 'n9be-tool-empty';
            empty.textContent = 'No matching applications. Search posts above, or try another name.';
            results.appendChild(empty);
        }
    }

    function clearWordPressCache(button) {
        var config = window.n9beTopBar || {};
        var status = $('#n9be-topbar-popup .n9be-cache-result');
        if (!config.ajaxUrl || !config.nonce) {
            if (status) status.textContent = 'You do not have permission to clear the object cache.';
            return;
        }
        var request = new URLSearchParams();
        request.set('action', 'osch_clear_object_cache');
        request.set('nonce', config.nonce);
        button.disabled = true;
        if (status) status.textContent = 'Clearing WordPress object cache…';
        fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: request })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                var data = result && result.data;
                if (!result || !result.success) throw new Error(data && data.message ? data.message : 'Cache could not be cleared.');
                if (status) status.textContent = data.message;
            })
            .catch(function (error) { if (status) status.textContent = error.message || 'Could not clear the object cache.'; })
            .finally(function () { button.disabled = false; });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) return;
        var clear = target.closest('#n9be-topbar-popup [data-n9be-clear-cache]');
        if (clear) { event.preventDefault(); clearWordPressCache(clear); return; }
        if (target.closest('[data-n9be-topbar-toggle]')) { event.preventDefault(); openPopup('n9be-topbar-popup'); return; }
        if (target.closest('[data-n9be-search-toggle]')) { event.preventDefault(); openPopup('n9be-search-popup'); return; }
        if (target.closest('.n9be-side-popup [data-n9be-popup-close]')) { event.preventDefault(); closePopup(true); return; }
        if (target.closest('[data-n9be-open-nav]')) {
            event.preventDefault();
            closePopup(false);
            document.dispatchEvent(new CustomEvent('n9be:open-scholar-nav'));
            return;
        }
        if (target.closest('#n9be-topbar-popup [data-n9be-action="theme_menu"]')) { closePopup(false); return; }
        var mirror = target.closest('#n9be-topbar-popup [data-topbar-action-index]');
        if (mirror) {
            event.preventDefault();
            var index = Number(mirror.getAttribute('data-topbar-action-index'));
            var original = originalToolbarActions[index];
            closePopup(false);
            if (original && document.contains(original)) original.click();
        }
    });
    document.addEventListener('input', function (event) {
        if (event.target && event.target.id === 'n9be-search-apps-input') populateApplicationResults();
    });
    document.addEventListener('keydown', function (event) {
        if (!activePopup) return;
        if (event.key === 'Escape') { event.preventDefault(); closePopup(true); return; }
        if (event.key !== 'Tab') return;
        var nodes = $all('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),summary', activePopup)
            .filter(function (el) { return el.getClientRects().length > 0 && !el.closest('[hidden]'); });
        if (!nodes.length) return;
        if (event.shiftKey && document.activeElement === nodes[0]) { event.preventDefault(); nodes[nodes.length - 1].focus(); }
        else if (!event.shiftKey && document.activeElement === nodes[nodes.length - 1]) { event.preventDefault(); nodes[0].focus(); }
    });
    // Opening the old Quick Actions popup closes either of these separate tools.
    document.addEventListener('click', function (event) {
        if (activePopup && event.target && event.target.closest && event.target.closest('[data-n9be-quick-toggle]')) closePopup(false);
    }, true);
}());
