window.NCDCap = function ( label ) {
	return '<div class="ncd-cap-loader" role="status" aria-live="polite">' +
		'<svg viewBox="0 0 64 64" aria-hidden="true" focusable="false">' +
		'<polygon points="32,12 60,24 32,36 4,24"/>' +
		'<path d="M16 30v14c0 4 7 8 16 8s16-4 16-8V30L32 37z" opacity=".85"/>' +
		'<g class="ncd-cap-tassel"><path d="M32 24L54 26v14" stroke="currentColor" stroke-width="2" fill="none"/><rect x="51" y="40" width="6" height="9" rx="2"/></g>' +
		'</svg><span>' + ( label || 'Loading…' ) + '</span></div>';
};
