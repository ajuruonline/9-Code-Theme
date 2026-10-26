(function () {
	'use strict';
	var cfg = window.OTPostEditor || {};
	var KEEP = /^(save|update|publish|schedule|view|add block|block inserter|back|settings|undo|redo|exit)/i;
	var HELP = {
		postcustom: 'Extra details: custom information attached to this item. Most sites do not need it.',
		slugdiv: 'Web address: the short name at the end of this page’s link.',
		postexcerpt: 'Summary: a short description shown in lists and search results.',
		postimagediv: 'Featured image: the main picture for this item.',
		pageparentdiv: 'Parent page: place this page under another page in the menu.',
		authordiv: 'Author: the person credited with this content.',
		commentsdiv: 'Comments: visitor comments on this item.',
		trackbacksdiv: 'Links from other sites that mention this item.',
		revisionsdiv: 'Earlier versions you can go back to.',
		categorydiv: 'Categories: the main sections this item belongs to.',
		'tagsdiv-post_tag': 'Tags: keywords that group related items.',
		formatdiv: 'Format: how the content is laid out, such as standard or gallery.'
	};
	var DEFAULT_HELP = 'Extra settings for this item. Open only if you need them.';
	var menu = null, toggle = null, list = null, observer = null, scheduled = 0;

	function clean(text) { return String(text || '').replace(/\s+/g, ' ').trim(); }
	function labelOf(el) { return clean(el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent); }
	function header() { return document.querySelector('.editor-header, .edit-post-header'); }

	function icon() {
		var src = cfg.icon || '';
		if (src) { return '<img class="ot-brand-icon" src="' + src.replace(/"/g, '') + '" alt="" width="22" height="22">'; }
		return '<svg class="ot-brand-icon" viewBox="0 0 64 64" width="22" height="22" aria-hidden="true" focusable="false"><polygon points="32,12 60,24 32,36 4,24" fill="currentColor"/><path d="M16 30v14c0 4 7 8 16 8s16-4 16-8V30L32 37z" fill="currentColor" opacity=".85"/></svg>';
	}

	function ensureMenu() {
		if (menu && document.body.contains(menu)) { return; }
		menu = document.createElement('div');
		menu.className = 'ot-menu';
		menu.setAttribute('role', 'dialog');
		menu.setAttribute('aria-label', 'Editor menu');
		menu.innerHTML = '<header class="ot-menu-head"><span class="ot-brand">' + icon() + '</span><div><small>OPEN THEME</small><strong>' + (cfg.menuTitle || 'Editor menu') + '</strong></div><button type="button" class="ot-menu-close" aria-label="Close menu">×</button></header><ul class="ot-menu-list"></ul><footer class="ot-menu-foot">Press the menu button again to close.</footer>';
		list = menu.querySelector('.ot-menu-list');
		menu.querySelector('.ot-menu-close').addEventListener('click', closeMenu);
		document.body.appendChild(menu);
	}

	function ensureToggle() {
		if (toggle && document.body.contains(toggle)) { return; }
		var head = header();
		if (!head) { return; }
		toggle = document.createElement('button');
		toggle.type = 'button';
		toggle.className = 'ot-menu-toggle';
		toggle.setAttribute('aria-expanded', 'false');
		toggle.setAttribute('aria-label', 'Open editor menu');
		toggle.setAttribute('title', 'Editor menu');
		toggle.innerHTML = icon();
		toggle.addEventListener('click', function () { menu && menu.classList.contains('is-open') ? closeMenu() : openMenu(); });
		var settings = head.querySelector('.editor-header__settings, .edit-post-header__settings') || head;
		settings.appendChild(toggle);
	}

	function openMenu() { ensureMenu(); menu.classList.add('is-open'); toggle && toggle.setAttribute('aria-expanded', 'true'); }
	function closeMenu() { if (menu) { menu.classList.remove('is-open'); } toggle && toggle.setAttribute('aria-expanded', 'false'); }

	function rebuild() {
		scheduled = 0;
		var head = header();
		if (!head) { return; }
		ensureToggle();
		ensureMenu();
		while (list.firstChild) { list.removeChild(list.firstChild); }
		var seen = {};
		head.querySelectorAll('a[href], button').forEach(function (el) {
			if (el === toggle || el.closest('.ot-menu') || el.closest('.ot-menu-toggle')) { return; }
			var text = labelOf(el);
			if (!text || text.indexOf('\u00b7') !== -1 || KEEP.test(text) || el.matches('.editor-post-publish-button,.editor-post-publish-panel__toggle,.editor-post-save-draft')) { return; }
			el.setAttribute('data-ot-moved', '1');
			if (seen[text]) { return; }
			seen[text] = true;
			var item = document.createElement(el.tagName === 'A' ? 'a' : 'button');
			item.className = 'ot-menu-item';
			if (el.tagName === 'A') { item.href = el.getAttribute('href') || '#'; if (el.target) { item.target = el.target; } }
			else { item.type = 'button'; }
			item.textContent = text;
			item.addEventListener('click', function (event) {
				if (el.tagName !== 'A') { event.preventDefault(); el.click(); closeMenu(); }
				else { closeMenu(); }
			});
			li(item);
		});
		if (!list.children.length) {
			var empty = document.createElement('li');
			empty.className = 'ot-menu-empty';
			empty.textContent = 'Nothing else to show here.';
			list.appendChild(empty);
		}
	}

	function li(item) { var row = document.createElement('li'); row.appendChild(item); list.appendChild(row); }

	function scheduleRebuild() {
		if (scheduled) { return; }
		scheduled = window.requestAnimationFrame(rebuild);
	}

	function accordionBoxes() {
		return document.querySelectorAll('.edit-post-meta-boxes-area .postbox, #poststuff .postbox, .postbox-container .postbox');
	}

	function setupAccordions() {
		accordionBoxes().forEach(function (box) {
			if (box.getAttribute('data-ot-acc') === '1') { return; }
			var head = box.querySelector('.postbox-header, .hndle, h2');
			if (!head) { return; }
			box.setAttribute('data-ot-acc', '1');
			box.classList.add('closed', 'ot-acc');
			var id = (box.id || '').replace(/^(?:edit-)?/, '');
			var help = document.createElement('p');
			help.className = 'ot-acc-help';
			help.textContent = HELP[id] || DEFAULT_HELP;
			head.parentNode.insertBefore(help, head.nextSibling);
			head.setAttribute('role', 'button');
			head.setAttribute('tabindex', '0');
			head.setAttribute('aria-expanded', 'false');
			var toggleBox = function () {
				var open = box.classList.toggle('closed') === false;
				head.setAttribute('aria-expanded', open ? 'true' : 'false');
			};
			head.addEventListener('click', function (event) { if (!event.target.closest('a,button,input,select,textarea')) { toggleBox(); } });
			head.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggleBox(); } });
		});
	}

	function init() {
		setupAccordions();
		rebuild();
		observer = new MutationObserver(function (records) {
			var outside = records.some(function (r) { return !(r.target.closest && r.target.closest('.ot-menu')); });
			if (!outside) { return; }
			scheduleRebuild();
			setupAccordions();
		});
		observer.observe(document.body, { childList: true, subtree: true });
		document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { closeMenu(); } });
		window.addEventListener('resize', scheduleRebuild, { passive: true });
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init, { once: true }); }
	else { init(); }
}());
