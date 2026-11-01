/* Post settings panel: opens from a row's "Post settings" link, edits every setting, saves and closes. */
(function () {
	'use strict';
	var cfg = window.oschPostSettings || {};
	var panel = null, form = null, title = null, status = null, currentRow = null, busy = false;

	function build() {
		panel = document.createElement('div');
		panel.className = 'osch-ps';
		panel.hidden = true;
		panel.innerHTML = '<div class="osch-ps-backdrop" data-osch-ps-close></div>' +
			'<section class="osch-ps-panel" role="dialog" aria-modal="true" aria-labelledby="osch-ps-title">' +
			'<header class="osch-ps-head"><h2 id="osch-ps-title">Post settings</h2><button type="button" class="osch-ps-x" data-osch-ps-close aria-label="Close">×</button></header>' +
			'<form class="osch-ps-form" novalidate><div class="osch-ps-body"><p class="osch-ps-loading">Loading…</p></div>' +
			'<p class="osch-ps-message" role="status" aria-live="polite"></p>' +
			'<footer class="osch-ps-foot"><button type="button" class="button" data-osch-ps-close>Close</button><button type="submit" class="button button-primary">Save</button></footer></form></section>';
		document.body.appendChild(panel);
		form = panel.querySelector('form');
		title = panel.querySelector('#osch-ps-title');
		status = panel.querySelector('.osch-ps-message');
		panel.querySelectorAll('[data-osch-ps-close]').forEach(function (node) { node.addEventListener('click', close); });
		form.addEventListener('submit', save);
	}

	function setMessage(text, isError) {
		status.textContent = text || '';
		status.classList.toggle('is-error', !!isError);
	}

	function open(row, postId) {
		if (!panel) build();
		currentRow = row;
		panel.hidden = false;
		document.body.classList.add('osch-ps-open');
		setMessage('', false);
		panel.querySelector('.osch-ps-body').innerHTML = '<p class="osch-ps-loading">Loading…</p>';
		var body = new URLSearchParams({ action: 'osch_post_settings_form', nonce: cfg.nonce, post_id: postId });
		fetch(cfg.ajaxUrl + '?' + body.toString(), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) { throw new Error(json.data && json.data.message ? json.data.message : 'Could not load the settings.'); }
				panel.querySelector('.osch-ps-body').innerHTML = json.data.html;
				title.textContent = 'Post settings: ' + json.data.title;
				panel.querySelector('input[name="post_title"]').focus();
			})
			.catch(function (error) { panel.querySelector('.osch-ps-body').innerHTML = ''; setMessage(error.message, true); });
	}

	function close() {
		if (!panel || panel.hidden || busy) { return; }
		panel.hidden = true;
		document.body.classList.remove('osch-ps-open');
		if (currentRow) { var link = currentRow.querySelector('.osch-ps-link'); if (link) { link.focus(); } }
	}

	function save(event) {
		event.preventDefault();
		if (busy) { return; }
		busy = true;
		panel.querySelector('button[type="submit"]').disabled = true;
		setMessage('Saving…', false);
		var data = new FormData(form);
		data.append('action', 'osch_post_settings_save');
		data.append('nonce', cfg.nonce);
		fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) { throw new Error(json.data && json.data.message ? json.data.message : cfg.failed); }
				if (currentRow) {
					var name = currentRow.querySelector('.row-title');
					if (name && json.data.title) { name.textContent = json.data.title; }
				}
				busy = false;
				panel.querySelector('button[type="submit"]').disabled = false;
				close();
			})
			.catch(function (error) {
				busy = false;
				panel.querySelector('button[type="submit"]').disabled = false;
				setMessage(error.message || cfg.failed, true);
			});
	}

	document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { close(); } });

	/* Replace WordPress's inline Quick Edit with this panel, on every row. */
	function relabel() {
		document.querySelectorAll('#the-list .row-actions .editinline:not(.osch-ps-link)').forEach(function (link) {
			link.textContent = 'Post settings';
			link.classList.add('osch-ps-link');
		});
	}
	document.addEventListener('click', function (event) {
		var link = event.target.closest && event.target.closest('#the-list .row-actions .editinline');
		if (!link) { return; }
		event.preventDefault();
		event.stopImmediatePropagation();
		var row = link.closest('tr');
		var id = row && row.id ? row.id.replace('post-', '') : '';
		if (id) { open(row, id); }
	}, true);

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', relabel); } else { relabel(); }
	var list = document.getElementById('the-list');
	if (list) { new MutationObserver(relabel).observe(list, { childList: true, subtree: true }); }
}());
