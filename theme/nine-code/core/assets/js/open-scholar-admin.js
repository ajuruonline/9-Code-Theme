(function () {
    'use strict';

    function prepareSections() {
        document.querySelectorAll('[data-ninecode-settings-root]').forEach(function (root) {
            root.querySelectorAll('[data-ninecode-settings-section]').forEach(function (section, index) {
                if (section.tagName !== 'DETAILS') return;
                section.open = false;
                section.classList.add('ninecode-settings-section', index % 2 ? 'ninecode-tone-green' : 'ninecode-tone-red');
                var summary = section.querySelector(':scope > summary');
                if (!summary) return;
                summary.setAttribute('aria-expanded', 'false');
                section.addEventListener('toggle', function () {
                    summary.setAttribute('aria-expanded', section.open ? 'true' : 'false');
                });
            });
        });
    }

    function upgradeLegacyPanels() {
        document.querySelectorAll('.ncu-admin .ncu-panel').forEach(function (panel) {
            if (panel.tagName === 'DETAILS' || panel.closest('.ncu-dashboard-grid')) return;
            var heading = panel.querySelector(':scope > h2, :scope > h3');
            if (!heading) return;
            var details = document.createElement('details');
            details.className = panel.className;
            details.setAttribute('data-ninecode-settings-section', '');
            var summary = document.createElement('summary');
            summary.textContent = heading.textContent;
            var body = document.createElement('div');
            body.className = 'ninecode-settings-body';
            Array.prototype.slice.call(panel.childNodes).forEach(function (node) {
                if (node !== heading) body.appendChild(node);
            });
            details.appendChild(summary);
            details.appendChild(body);
            details.open = false;
            panel.replaceWith(details);
        });
        document.querySelectorAll('.ncu-admin').forEach(function (root) { root.setAttribute('data-ninecode-settings-root', ''); });
    }

    function prepareEditor() {
        document.body.classList.add('open-scholar-mobile-first');
        document.querySelectorAll('.postbox').forEach(function (box) {
            var heading = box.querySelector(':scope > .postbox-header h2');
            if (heading && !box.getAttribute('aria-label')) box.setAttribute('aria-label', heading.textContent.trim());
        });
    }

    function init() {
        document.querySelectorAll('#adminmenu > li.menu-top .wp-menu-image').forEach(function (icon) {
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('width', '20'); svg.setAttribute('height', '20');
            svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
            svg.setAttribute('stroke-width', '1.6'); svg.setAttribute('stroke-linejoin', 'round');
            svg.setAttribute('aria-hidden', 'true');
            var row = icon.closest('li'), key = ((row && row.id) || '') + ' ' + ((row && row.textContent) || '');
            var shapes = [
                [/dashboard|workspace/i, 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
                [/control|system|setting/i, 'M12 3v3m0 12v3m9-9h-3M6 12H3m15.4-6.4-2.1 2.1M7.7 16.3l-2.1 2.1m12.8 0-2.1-2.1M7.7 7.7 5.6 5.6M15.5 12a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z'],
                [/lecture|learn|class|textbook|library|publication/i, 'm3 5 9-3 9 3-9 3-9-3Zm3 5v6.5c0 1.2 2.7 2.5 6 2.5s6-1.3 6-2.5V10M21 6v7'],
                [/conference|presentation|event/i, 'M4 4h16v16H4zM8 2v4m8-4v4M4 9h16M8 13h3m2 0h3m-8 3h3'],
                [/user|people|profile/i, 'M16 20v-1.5A4.5 4.5 0 0 0 11.5 14h-3A4.5 4.5 0 0 0 4 18.5V20m6-10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm6 1 2 2m0 0 3-3m-3 3v5'],
                [/open scholar|nine|ncu|9 /i, 'M4 19V8l8-4 8 4v11M8 19v-5h8v5M3 20h18M7 9h10'],
                [/backup|archive/i, 'M4 5h16v14H4zM8 5V3h8v2m-8 5h8m-4 0v6m-3-3 3 3 3-3'],
                [/plugin|app/i, 'M8 3h3v4h2V3h3l2 2v3h-4v2h4v3l-2 2h-3v-4h-2v4H8l-2-2v-3h4V8H6V5l2-2Z']
            ];
            var d = 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5';
            shapes.some(function (shape) { if (shape[0].test(key)) { d = shape[1]; return true; } return false; });
            d.split('|').forEach(function (pathData) { var path = document.createElementNS(svg.namespaceURI, 'path'); path.setAttribute('d', pathData); svg.appendChild(path); });
            icon.replaceChildren(svg); icon.classList.add('osch-nav-symbol');
        });
        var appSearch = document.getElementById('osch-app-search');
        if (appSearch) appSearch.addEventListener('input', function () {
            var query = appSearch.value.trim().toLowerCase(), count = 0;
            document.querySelectorAll('[data-osch-app]').forEach(function (tile) {
                tile.hidden = tile.getAttribute('data-osch-app').indexOf(query) === -1;
                if (!tile.hidden) count++;
            });
            document.getElementById('osch-app-empty').hidden = count > 0;
        });
        upgradeLegacyPanels();
        prepareSections();
        prepareEditor();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
}());
