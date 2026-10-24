/**
 * Nine Code settings UI contract.
 *
 * Sections are deliberately closed on every page load. The visitor decides
 * what to open; browser storage must never reopen a settings section.
 */
( function () {
	'use strict';

	function initialise( root ) {
		var sections = root.querySelectorAll( '[data-ninecode-settings-section]' );
		sections.forEach( function ( section, index ) {
			if ( section.dataset.ninecodeSettingsReady === '1' ) { return; }
			section.dataset.ninecodeSettingsReady = '1';
			section.open = false;
			section.classList.add( 'ninecode-settings-section', index % 2 ? 'ninecode-tone-green' : 'ninecode-tone-red' );
			var summary = section.querySelector( ':scope > summary' );
			if ( summary ) {
				summary.setAttribute( 'aria-expanded', 'false' );
				section.addEventListener( 'toggle', function () {
					summary.setAttribute( 'aria-expanded', section.open ? 'true' : 'false' );
				} );
			}
		} );

		root.querySelectorAll( '[data-ninecode-settings-field]' ).forEach( function ( field, index ) {
			field.classList.add( index % 2 ? 'ninecode-field-green' : 'ninecode-field-red' );
		} );
	}

	function start() {
		document.querySelectorAll( '[data-ninecode-settings-root]' ).forEach( initialise );
		if ( ! document.body || ! window.MutationObserver ) { return; }
		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( ! node || node.nodeType !== 1 ) { return; }
					if ( node.matches( '[data-ninecode-settings-root]' ) ) { initialise( node ); }
					var parentRoot = node.closest( '[data-ninecode-settings-root]' );
					if ( parentRoot ) { initialise( parentRoot ); }
					node.querySelectorAll( '[data-ninecode-settings-root]' ).forEach( initialise );
				} );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
