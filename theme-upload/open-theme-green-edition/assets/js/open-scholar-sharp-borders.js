/** OS-STD border geometry: a box with a visible border/outline has square corners.
 * Borderless surfaces may remain rounded. Covers WordPress/plugin markup created after load.
 */
(function () {
    'use strict';
    var pending = new Set(), frame = 0, watched = false, previousInline = new WeakMap();
    function hasLine(s) {
        var sides = ['Top','Right','Bottom','Left'];
        for (var i = 0; i < sides.length; i++) {
            var edge = sides[i];
            if (s['border' + edge + 'Style'] !== 'none' && s['border' + edge + 'Style'] !== 'hidden' && parseFloat(s['border' + edge + 'Width']) > 0) return true;
        }
        return s.outlineStyle !== 'none' && s.outlineStyle !== 'hidden' && parseFloat(s.outlineWidth) > 0;
    }
    function inspect(element) {
        if (!element || element.nodeType !== 1 || element.namespaceURI !== 'http://www.w3.org/1999/xhtml') return;
        // Surfaces marked data-keep-radius (the Open Scholar menu sheet) keep their rounded corners.
        if (element.closest && element.closest('[data-keep-radius]')) return;
        var s = getComputedStyle(element), outlined = hasLine(s);
        if (element.classList.contains('osch-sharp-lined') !== outlined) element.classList.toggle('osch-sharp-lined', outlined);
        if (outlined && !previousInline.has(element)) {
            previousInline.set(element, {value:element.style.getPropertyValue('border-radius'), priority:element.style.getPropertyPriority('border-radius')});
            element.style.setProperty('border-radius', '0px', 'important');
        } else if (!outlined && previousInline.has(element)) {
            var old = previousInline.get(element);
            if (old.value) element.style.setProperty('border-radius', old.value, old.priority);
            else element.style.removeProperty('border-radius');
            previousInline.delete(element);
        }
    }
    /* Initial scan runs in idle time, a few elements at a time, so the first paint is not held up. */
    function scanInIdle(root) {
        var list = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));
        var index = 0;
        var later = window.requestIdleCallback ? function (fn) { window.requestIdleCallback(fn, {timeout: 400}); } : function (fn) { window.setTimeout(fn, 16); };
        (function step() {
            var end = performance.now() + 8;
            while (index < list.length && performance.now() < end) { inspect(list[index++]); }
            if (index < list.length) later(step);
        })();
    }
    function queue(element, shallow) {
        if (!element || element.nodeType !== 1) return;
        if (shallow) { inspect(element); return; }
        pending.add(element);
        if (!frame) frame = requestAnimationFrame(function () {
            frame = 0;
            // All new child elements are inspected so dynamically mounted popups remain compliant.
            pending.forEach(function (node) {
                inspect(node);
                node.querySelectorAll && node.querySelectorAll('*').forEach(inspect);
            });
            pending.clear();
        });
    }
    function start() {
        if (watched || !document.body) return;
        watched = true;
        scanInIdle(document.body);
        // Borders/outline can appear on hover or keyboard focus without a DOM mutation. Only the hovered element changes.
        ['focusin','focusout','pointerover','pointerout'].forEach(function (type) {
            document.addEventListener(type, function (event) { queue(event.target, true); }, true);
        });
        new MutationObserver(function (changes) {
            changes.forEach(function (mutation) {
                if (mutation.type === 'attributes') {
                    // Our own class change is idempotent; next inspection performs no mutation.
                    queue(mutation.target);
                } else {
                    mutation.addedNodes.forEach(queue);
                }
            });
        }).observe(document.body, {childList:true,subtree:true,attributes:true,attributeFilter:['class','style']});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
    else start();
})();
