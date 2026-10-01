(function () {
	'use strict';
	var toggle = document.querySelector('.ncu-nav-toggle');
	var nav = document.getElementById('ncu-primary-nav');
	if (!toggle || !nav) { return; }
	function setOpen(open) {
		nav.classList.toggle('is-open', open);
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	}
	toggle.addEventListener('click', function () { setOpen(!nav.classList.contains('is-open')); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
	});
}());
