(function () {
    'use strict';
    var cfg = window.NCU13UI || {};

    function neutralizeInlineStyles(root) {
        if (!root || !cfg.runtimeBlueLock) return;
        var nodes = [];
        if (root.matches && root.matches('#adminmenu, #adminmenu *')) nodes.push(root);
        if (root.querySelectorAll) nodes = nodes.concat(Array.prototype.slice.call(root.querySelectorAll('#adminmenu, #adminmenu *')));
        nodes.forEach(function (node) {
            if (!node.style) return;
            ['background','background-color','border-color','border-left-color','border-right-color','border-top-color','border-bottom-color','box-shadow','text-shadow'].forEach(function (prop) {
                if (node.style.getPropertyValue(prop)) node.style.removeProperty(prop);
            });
        });
    }

    function lockMenu() {
        var menu = document.getElementById('adminmenu');
        var wrap = document.getElementById('adminmenuwrap');
        if (!menu || !cfg.runtimeBlueLock) return;
        neutralizeInlineStyles(menu);
        if (window.matchMedia && window.matchMedia('(max-width: 782px), (pointer: coarse)').matches) return;
        var pending = new Set(), frame = 0;
        function flush() { frame = 0; pending.forEach(neutralizeInlineStyles); pending.clear(); }
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes') pending.add(mutation.target);
                else Array.prototype.forEach.call(mutation.addedNodes || [], function (node) { if (node && node.nodeType === 1) pending.add(node); });
            });
            if (!frame) frame = window.requestAnimationFrame(flush);
        });
        observer.observe(wrap || menu, {subtree:true, childList:true, attributes:true, attributeFilter:['style']});
    }

    function modernizeAiEditor() {
        if (!cfg.aiEditorModern || !cfg.isAiEditor) return;
        document.body.classList.add('ncu13-ai-editor-modernized');
        var fields = document.querySelectorAll('input, textarea, select');
        Array.prototype.forEach.call(fields, function (field) {
            if (!field.getAttribute('aria-label') && !field.id) return;
            field.classList.add('ncu13-ai-field');
        });
    }

    function initialiseSettingsSections() {
        document.querySelectorAll('[data-ninecode-settings-root]').forEach(function (root) {
            root.querySelectorAll('[data-ninecode-settings-section]').forEach(function (section, index) {
                section.open = false;
                section.classList.add('ninecode-settings-section', index % 2 ? 'ninecode-tone-green' : 'ninecode-tone-red');
                var summary = section.querySelector(':scope > summary');
                if (!summary) return;
                summary.setAttribute('aria-expanded', 'false');
                section.addEventListener('toggle', function () {
                    summary.setAttribute('aria-expanded', section.open ? 'true' : 'false');
                });
            });
            root.querySelectorAll('[data-ninecode-settings-field]').forEach(function (field, index) {
                field.classList.add(index % 2 ? 'ninecode-field-green' : 'ninecode-field-red');
            });
        });
    }

    function init() {
        lockMenu();
        modernizeAiEditor();
        initialiseSettingsSections();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once:true});
    } else {
        init();
    }
})();
