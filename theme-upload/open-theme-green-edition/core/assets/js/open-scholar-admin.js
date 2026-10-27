(function () {
    'use strict';

    function prepareSections() {
        document.querySelectorAll('[data-ninecode-settings-root]').forEach(function (root) {
            root.querySelectorAll('[data-ninecode-settings-section]').forEach(function (section, index) {
                if (section.tagName !== 'DETAILS') return;
                section.open = section.hasAttribute('data-osch-keep-open');
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

    function installNavigationShell() {
        var menu = document.getElementById('adminmenu');
        if (!menu || menu.querySelector('.osch-navigation-identity')) return;
        var identity = document.createElement('li');
        identity.className = 'osch-navigation-identity';
        identity.setAttribute('aria-label', 'Open Scholar navigation');
        identity.innerHTML = '<span class="osch-navigation-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span><b>OPEN SCHOLAR</b><small>APPS · CONTENT · SYSTEM</small></span>';
        menu.insertBefore(identity, menu.firstChild);
    }


    /** Respect institution-level display preferences without mutating WP capabilities. */
    function applyNavigationPreferences(menu) {
        var config = window.oschWorkspaceMenuConfig;
        if (!Array.isArray(config) || !menu) return;
        function matchLinks(candidate, configured) {
            if (!candidate || !configured) return false;
            try {
                var a = new URL(candidate, window.location.href);
                var b = new URL(configured, window.location.href);
                if (a.origin !== b.origin) return false;
                var pageA = a.searchParams.get('page'), pageB = b.searchParams.get('page');
                if (pageA || pageB) return pageA && pageA === pageB;
                return a.pathname === b.pathname && (a.searchParams.get('post_type') || '') === (b.searchParams.get('post_type') || '');
            } catch (ignored) { return false; }
        }
        var positions = [];
        menu.querySelectorAll(':scope > li.menu-top').forEach(function (item, index) {
            var main = item.querySelector(':scope > a.menu-top');
            if (!main) return;
            var record = config.find(function (row) { return matchLinks(main.href, row.url); });
            if (!record) {
                // WordPress's own items (no catalogue row) still follow the tiers: core settings before other plugins.
                var file = (main.getAttribute('href') || '').split('?')[0].split('/').pop();
                var coreTier = /^(index|themes|plugins|users|profile|tools|options-[a-z-]+|nav-menus)\.php$/.test(file);
                var tier = file === 'index.php' ? 0 : (coreTier ? 2 : 3);
                positions.push({item: item, order: tier * 100 + 900, index: index, tier: tier});
                return;
            }
            positions.push({item: item, order: Number.isFinite(record.order) ? record.order : 500, index: index, tier: record.tier || 2});
            if (record.menu === false) { item.hidden = true; item.classList.add('osch-navigation-hidden'); }
            if (record.title) {
                var name = main.querySelector('.wp-menu-name');
                if (name) name.textContent = record.title;
            }
            var submenu = item.querySelector(':scope > .wp-submenu');
            if (!submenu || !Array.isArray(record.submenus)) return;
            submenu.querySelectorAll('li > a[href]').forEach(function (link) {
                var sub = record.submenus.find(function (row) { return matchLinks(link.href, row.url); });
                if (!sub) return;
                if (sub.visible === false) { link.closest('li').hidden = true; }
                if (sub.title) link.textContent = sub.title;
            });
        });
        // Reorder visible app rows only; preserve IDs, native WordPress links and event handlers.
        positions.sort(function (a, b) { return a.order - b.order || a.index - b.index; });
        positions.forEach(function (entry) { menu.appendChild(entry.item); });
        // Other plugins (tier 3) sit together at the end, under a collapsed Advanced heading.
        var advanced = positions.filter(function (entry) { return entry.tier === 3 && !entry.item.hidden; });
        if (advanced.length) {
            var head = document.createElement('li');
            head.className = 'osch-nav-advanced-head';
            head.innerHTML = '<button type="button" class="osch-nav-advanced-toggle" aria-expanded="false"><span>Advanced</span><span class="osch-nav-advanced-count">' + advanced.length + '</span></button>';
            menu.insertBefore(head, advanced[0].item);
            advanced.forEach(function (entry) { entry.item.classList.add('osch-nav-advanced'); });
            head.querySelector('button').addEventListener('click', function () {
                var open = menu.classList.toggle('osch-nav-advanced-open');
                this.setAttribute('aria-expanded', String(open));
            });
        }
    }

    /** Original permission-filtered WordPress menu becomes a centered app popup.
     * No links are fabricated and no menu items are removed. Existing IDs persist.
     */
    function installPopupNavigation() {
        var menuWrap = document.getElementById('adminmenuwrap');
        var menu = document.getElementById('adminmenu');
        if (!menuWrap || !menu || document.getElementById('osch-nav-dialog')) return;
        applyNavigationPreferences(menu);

        var launcher = document.createElement('button');
        launcher.type = 'button';
        launcher.id = 'osch-nav-launcher';
        launcher.setAttribute('aria-label', 'Open Open Scholar menu');
        launcher.setAttribute('aria-haspopup', 'dialog');
        launcher.setAttribute('aria-controls', 'osch-nav-dialog');
        launcher.setAttribute('aria-expanded', 'false');
        var brandIcon = window.oschNavBrand && window.oschNavBrand.icon ? String(window.oschNavBrand.icon) : '';
        var capIcon = window.oschNavBrand && window.oschNavBrand.cap ? String(window.oschNavBrand.cap) : brandIcon;
        launcher.innerHTML = '<img class="osch-launcher-site-icon" alt="" aria-hidden="true">';
        launcher.querySelector('.osch-launcher-site-icon').src = brandIcon;


        var root = document.createElement('div');
        root.id = 'osch-nav-dialog';
        root.hidden = true;
        root.innerHTML = '<button type="button" class="osch-nav-backdrop" data-osch-close tabindex="-1" aria-label="Close menu"></button>' +
          '<section class="osch-nav-popup" role="dialog" aria-modal="true" aria-label="Open Scholar navigation">' +
          '<header class="osch-nav-header"><div class="osch-nav-brand"><span class="osch-nav-brand-mark"><img class="osch-nav-brand-img" alt="Open Scholar site icon"></span><div><strong>OPEN SCHOLAR</strong><small>WordPress · Applications · Tools</small></div></div><button type="button" class="osch-nav-close" data-osch-close aria-label="Close menu">×</button></header>' +
          '<div class="osch-nav-menuhost"></div>' +
          '<footer class="osch-nav-footer"><span>Powered by OpenScholar.ng</span><a href="' + (document.querySelector('#wp-admin-bar-site-name a') ? document.querySelector('#wp-admin-bar-site-name a').href : '/') + '">View site</a></footer></section>';

        // Preserve one copy of the live core navigation; WordPress continues to
        // control permissions, URLs, menus and the current-screen markers.
        document.body.appendChild(root);
        var brandImage = root.querySelector('.osch-nav-brand-img');
        if (brandImage) brandImage.src = capIcon;
        root.querySelector('.osch-nav-menuhost').appendChild(menuWrap);
        document.body.appendChild(launcher);
        var previousFocus = null;

        var LINK_ICON = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>';
        function setSubmenu(item, open) {
            item.classList.toggle('osch-submenu-open', open);
            var toggle = item.querySelector(':scope > .osch-submenu-toggle');
            if (!toggle) return;
            var name = item.querySelector('.wp-menu-name') ? item.querySelector('.wp-menu-name').textContent.trim() : 'item';
            toggle.textContent = open ? '\u2212' : '+';
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', (open ? 'Hide' : 'Show') + ' submenu for ' + name);
        }
        menu.querySelectorAll(':scope > li.menu-top').forEach(function (item) {
            var submenu = item.querySelector(':scope > .wp-submenu');
            var top = item.querySelector(':scope > a.menu-top');
            if (!submenu || !top) return;
            var name = item.querySelector('.wp-menu-name') ? item.querySelector('.wp-menu-name').textContent.trim() : 'item';
            // The link button opens the item's own page; the rest of the row opens its submenu.
            var go = document.createElement('a');
            go.className = 'osch-menu-go';
            go.href = top.href;
            go.setAttribute('aria-label', 'Open ' + name);
            go.title = 'Open ' + name;
            go.innerHTML = LINK_ICON;
            item.appendChild(go);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'osch-submenu-toggle';
            item.appendChild(button);
            setSubmenu(item, item.classList.contains('wp-has-current-submenu') || item.classList.contains('wp-menu-open'));
            button.addEventListener('click', function (event) {
                event.preventDefault();
                setSubmenu(item, !item.classList.contains('osch-submenu-open'));
            });
        });
        // Capture phase, so WordPress's responsive menu script never swallows the tap.
        menu.addEventListener('click', function (event) {
            // The link button navigates normally; only keep WordPress from cancelling it.
            if (event.target.closest('.osch-menu-go')) { event.stopPropagation(); return; }
            var top = event.target.closest('li.menu-top > a.menu-top');
            if (!top || !menu.contains(top)) return;
            var item = top.parentNode;
            if (!item.querySelector(':scope > .wp-submenu')) return;
            event.preventDefault();
            event.stopPropagation();
            setSubmenu(item, !item.classList.contains('osch-submenu-open'));
        }, true);
        function scrollNavigationToCurrent() {
            var host = root.querySelector('.osch-nav-menuhost');
            if (!host) return;
            // WordPress already applies current-menu-ancestor/current markers.
            // Honour these first; the URL matcher is only a fallback for plugin pages.
            var selected = menu.querySelector('.wp-submenu li.current > a, .wp-submenu a[aria-current="page"]');
            if (!selected) selected = menu.querySelector('li.menu-top.current > a.menu-top, li.wp-has-current-submenu > a.menu-top');
            if (!selected) {
                var path = window.location.pathname;
                var page = new URLSearchParams(window.location.search).get('page');
                var postType = new URLSearchParams(window.location.search).get('post_type');
                menu.querySelectorAll('a[href]').forEach(function (link) {
                    if (selected) return;
                    try {
                        var url = new URL(link.href, window.location.href);
                        if (url.origin !== window.location.origin || url.pathname !== path) return;
                        if (page && url.searchParams.get('page') !== page) return;
                        if (postType && url.searchParams.get('post_type') !== postType) return;
                        selected = link;
                    } catch (ignored) { /* Unknown plugin route: keep WordPress markers. */ }
                });
            }
            if (!selected) return;
            var parent = selected.closest('li.menu-top');
            if (parent && parent.querySelector(':scope > .wp-submenu')) setSubmenu(parent, true);
            menu.querySelectorAll('.osch-nav-current-page').forEach(function (old) { old.classList.remove('osch-nav-current-page'); });
            selected.classList.add('osch-nav-current-page');
            selected.setAttribute('aria-current', 'page');
            // Scroll only the menu, never the underlying edit screen.
            requestAnimationFrame(function () {
                if (root.hidden) return;
                var target = selected.getBoundingClientRect();
                var viewport = host.getBoundingClientRect();
                host.scrollTop += target.top - viewport.top - Math.min(55, Math.max(10, host.clientHeight * .13));
            });
        }

        function setOpen(open) {
            root.hidden = !open;
            document.body.classList.toggle('osch-nav-open', open);
            launcher.setAttribute('aria-expanded', String(open));
            launcher.setAttribute('aria-label', open ? 'Close Open Scholar menu' : 'Open Open Scholar menu');
            launcher.classList.toggle('osch-is-open', open);
            if (open) {
                previousFocus = document.activeElement;
                document.body.classList.remove('wp-responsive-open', 'n9be-tablet-menu-open');
                var wpwrap = document.getElementById('wpwrap');
                if (wpwrap) wpwrap.classList.remove('wp-responsive-open', 'n9be-tablet-menu-open');
                root.querySelector('.osch-nav-close').focus();
                scrollNavigationToCurrent();
            } else {
                (previousFocus && typeof previousFocus.focus === 'function' ? previousFocus : launcher).focus();
            }
        }
        launcher.addEventListener('click', function () { setOpen(root.hidden); });
        root.querySelectorAll('[data-osch-close]').forEach(function (node) {
            node.addEventListener('click', function () { setOpen(false); });
        });
        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { event.preventDefault(); setOpen(false); return; }
            if (event.key !== 'Tab') return;
            var focusable = Array.prototype.filter.call(root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled])'), function (node) {
                return node.getClientRects().length > 0 && !node.closest('[hidden]');
            });
            if (!focusable.length) return;
            var first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        // Existing toolbar menu actions and Top Bar popup open this same navigation.
        document.addEventListener('n9be:open-scholar-nav', function () { setOpen(true); });
        document.addEventListener('click', function (event) {
            var coreMenuButton = event.target.closest('#wp-admin-bar-menu-toggle');
            if (coreMenuButton) { event.preventDefault(); event.stopImmediatePropagation(); setOpen(root.hidden); }
        }, true);
        document.body.classList.add('osch-modal-navigation-ready');
    }

    /** Profile and user forms: settings about the software move into one collapsed Advanced section. */
    function prepareProfileForm() {
        var form = document.getElementById('your-profile');
        if (!form) return;
        var rows = Array.prototype.slice.call(form.querySelectorAll('.user-syntax-highlighting-wrap, .user-admin-color-wrap, .user-comment-shortcuts-wrap, .user-admin-bar-front-wrap, .user-infinite-scrolling-wrap, .user-language-wrap, .user-sessions-wrap'));
        var apps = document.getElementById('application-passwords-section');
        if (!rows.length && !apps) return;
        var details = document.createElement('details');
        details.className = 'osch-profile-advanced';
        details.innerHTML = '<summary><span>Advanced settings</span><small>Editor, colours, language, sessions and application passwords</small></summary>';
        if (rows.length) {
            var table = document.createElement('table');
            table.className = 'form-table';
            var body = document.createElement('tbody');
            table.appendChild(body);
            rows.forEach(function (row) { body.appendChild(row); });
            details.appendChild(table);
        }
        if (apps) details.appendChild(apps);
        form.insertBefore(details, form.querySelector('p.submit') || null);
        form.querySelectorAll('table.form-table').forEach(function (table) {
            if (!table.querySelector('tr')) table.remove();
        });
    }

    function init() {
        installNavigationShell();
        prepareProfileForm();
        // Named, semantically distinct icons. Never replace unknown plugin icons
        // with unrelated generic line art (and never use a default lightbulb).
        document.querySelectorAll('#adminmenu > li.menu-top').forEach(function (row) {
            var icon = row.querySelector(':scope > a.menu-top .wp-menu-image');
            var label = row.querySelector(':scope > a.menu-top .wp-menu-name');
            if (!icon || !label) return;
            var name = label.textContent.replace(/\s+/g, ' ').trim();
            var key = name;
            // Label takes precedence over ancestor/submenu content.
            var mappings = [
                [/Ultra Backup|\bBackup\b|Restore|Archive/i, 'M4 7h16v13H4z|M3 4h18v4H3z|M9 12h6m-3 0v5m-3-3 3 3 3-3'],
                [/Open Navigator|Navigator/i, 'M12 2l8.5 19L12 17l-8.5 4L12 2z|M12 17V9'],
                [/Open Index|\bIndex\b/i, 'M4 4h16v16H4z|M8 8h8M8 12h8M8 16h5'],
                [/Lecture Notes|Lecture Documents/i, 'M6 3h12v18H6z|M9 3v18|M12 8h4M12 12h4M12 16h3'],
                [/eLectura|e.?Lectur|Video Lecture/i, 'M3 5h14v14H3z|M17 10l4-3v10l-4-3z|M8 9l5 3-5 3z'],
                [/e.?PDF|PDF Reader/i, 'M6 2h9l5 5v15H6z|M15 2v5h5|M8 13h9M8 17h7'],
                [/e.?Book|e.?Textbook|Library|Books/i, 'M3 5c3-1 6-1 9 1v15c-3-2-6-2-9-1z|M12 6c3-2 6-2 9-1v15c-3-1-6-1-9 1z'],
                [/e.?Step|Tutorial|Lesson/i, 'M4 19h4v-5h4V9h4V4h4|M3 21h18'],
                [/Journal|Publication|Proceeding|Article/i, 'M5 3h11l3 3v15H5z|M16 3v4h4|M8 11h8M8 15h8M8 19h5'],
                [/Conference|Events|Calendar/i, 'M4 4h16v17H4z|M8 2v4m8-4v4M4 9h16|M8 13h3m2 0h3m-8 4h3'],
                [/Course|Learning|Academy|Classroom/i, 'M3 7l9-4 9 4-9 4-9-4z|M6 10v6c3 3 9 3 12 0v-6|M21 7v7'],
                [/Presentation|Slide/i, 'M4 4h16v12H4z|M12 16v5M8 21h8|M7 9h7M7 12h10'],
                [/Research|Scholar|Academic/i, 'M3 4h18v16H3z|M7 8h10M7 12h5M7 16h7|M16 14l2 2 3-4'],
                [/Media|Gallery|Image|Video|Audio/i, 'M3 5h18v14H3z|M7 9a2 2 0 1 0 0 .01M4 17l5-5 4 4 3-3 4 4'],
                [/Appearance|Theme|Design|Customize/i, 'M12 3a9 9 0 0 0 0 18h1a2 2 0 0 0 2-2 2 2 0 0 0-1-1.8 2 2 0 0 1 1-3.8h2a4 4 0 0 0 4-4 9 9 0 0 0-9-6.4z|M7.5 11h.01M9 7.5h.01M14 7.5h.01M17 11h.01'],
                [/Plugin|Application|Apps/i, 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
                [/Users|Members|People|Profile|Authors/i, 'M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8z|M17 11a3 3 0 0 1 0-6|M22 20v-2a4 4 0 0 0-3-3.8'],
                [/Tools|Utilities/i, 'M14 3a5 5 0 0 0-5 7L3 16a3 3 0 0 0 5 5l6-6a5 5 0 0 0 7-6l-4 4-4-4 4-4z'],
                [/Settings|Control Centre|System/i, 'M12 3v3m0 12v3m9-9h-3M6 12H3|M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1m12.8 0-2.1-2.1M7.7 7.7 5.6 5.6|M15.5 12a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0z'],
                [/Posts|Blog|News|Content/i, 'M5 3h14v18H5z|M8 7h8M8 11h8M8 15h6'],
                [/Pages|Document|Files/i, 'M6 2h9l5 5v15H6z|M15 2v5h5|M9 12h8M9 16h8'],
                [/Comments|Feedback|Discussion/i, 'M3 4h18v14H8l-5 4z|M7 9h10M7 13h7'],
                [/Store|Products|Commerce/i, 'M4 10l2-7h12l2 7v11H4z|M2 10h20|M9 21v-8h6v8'],
                [/Forms|Survey|Application Forms/i, 'M6 3h12v18H6z|M9 8h6M9 12h2m2 0h2M9 16h6'],
                [/SEO|Analytics|Statistics|Reports/i, 'M3 20h18|M5 17V9h3v8M11 17V4h3v13M17 17v-6h3v6'],
                [/Menu|Navigation/i, 'M4 6h16M4 12h16M4 18h16'],
                [/Security|Protect/i, 'M12 2l8 4v6c0 5-3 8-8 10-5-2-8-5-8-10V6z|M9 12l2 2 4-5'],
                [/Import|Export|Transfer/i, 'M3 7h12|M11 3l4 4-4 4|M21 17H9|M13 13l-4 4 4 4'],
                [/Dashboard|Workspace|Home/i, 'M3 11l9-8 9 8|M5 10v11h14V10|M9 21v-7h6v7']
            ];
            var mapping = null;
            for (var i=0; i<mappings.length; i++) {
                if (mappings[i][0].test(key)) { mapping = mappings[i][1]; break; }
            }
            if (!mapping) return; // Plugin-provided icon is more accurate than a guess.
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', 'currentColor');
            svg.setAttribute('stroke-width', '1.9');
            svg.setAttribute('stroke-linecap', 'round');
            svg.setAttribute('stroke-linejoin', 'round');
            svg.setAttribute('aria-hidden', 'true');
            mapping.split('|').forEach(function (pathData) {
                var path = document.createElementNS(svg.namespaceURI, 'path');
                path.setAttribute('d', pathData);
                svg.appendChild(path);
            });
            icon.replaceChildren(svg);
            icon.classList.add('osch-nav-symbol');
            icon.title = name;
        });
        var system = document.querySelector('#toplevel_page_osch-system');
        if (system && !system.querySelector('.osch-menu-expand')) {
            var toggle = document.createElement('button');
            toggle.type = 'button'; toggle.className = 'osch-menu-expand'; toggle.setAttribute('aria-label','Expand Control Centre menu'); toggle.setAttribute('aria-expanded','false'); toggle.textContent = '+';
            var link = system.querySelector(':scope > a.menu-top'); if (link) link.insertAdjacentElement('afterend',toggle);
            toggle.addEventListener('click',function(){var open=system.classList.toggle('osch-submenu-open');toggle.textContent=open?'−':'+';toggle.setAttribute('aria-expanded',open?'true':'false');toggle.setAttribute('aria-label',(open?'Collapse':'Expand')+' Control Centre menu');});
        }
        var appSearch = document.getElementById('osch-app-search');
        var appButtons = Array.prototype.slice.call(document.querySelectorAll('.osch-app-filters [data-osch-group]'));
        var activeGroup = 'all';
        function filterApplications() {
            var query = appSearch ? appSearch.value.trim().toLowerCase() : '';
            var count = 0;
            document.querySelectorAll('.osch-app-tile[data-osch-app]').forEach(function (tile) {
                var byName = tile.getAttribute('data-osch-app').indexOf(query) !== -1;
                var byGroup = activeGroup === 'all' || tile.getAttribute('data-osch-group') === activeGroup;
                tile.hidden = !(byName && byGroup);
                if (!tile.hidden) count++;
            });
            var empty = document.getElementById('osch-app-empty');
            if (empty) empty.hidden = count > 0;
        }
        if (appSearch) appSearch.addEventListener('input', filterApplications);
        appButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeGroup = button.getAttribute('data-osch-group');
                appButtons.forEach(function (item) {
                    var selected = item === button;
                    item.classList.toggle('is-active', selected);
                    item.setAttribute('aria-pressed', String(selected));
                });
                filterApplications();
            });
        });
        upgradeLegacyPanels();
        prepareSections();
        prepareEditor();
        installPopupNavigation();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
}());
