(function () {
    'use strict';
    function syncSelect(select) {
        var kind = select.form && select.form.querySelector('[data-ncu-dm-kind]');
        if (!kind) { return; }
        var wanted = kind.value === 'term' ? 'Taxonomies' : 'Post types';
        Array.prototype.forEach.call(select.options, function (option) {
            var group = option.parentNode && option.parentNode.label;
            option.hidden = group && group !== wanted;
        });
        var current = select.options[select.selectedIndex];
        if (!current || current.hidden) {
            var first = Array.prototype.slice.call(select.options).filter(function (option) { return !option.hidden; })[0];
            if (first) { select.value = first.value; }
        }
    }
    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-ncu-dm-kind]'), function (kind) {
            var select = kind.form && kind.form.querySelector('[data-ncu-dm-type]');
            if (!select) { return; }
            var refresh = function () { syncSelect(select); };
            kind.addEventListener('change', refresh);
            refresh();
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
