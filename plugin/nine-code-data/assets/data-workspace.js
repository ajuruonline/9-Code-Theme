/**
 * Nine Code Data — Data Workspace.
 *
 * Mobile-first single-page admin for the ninecode-data/v1 REST API. All values are written to the DOM as
 * text (never innerHTML), every write goes through the server's provider-owned validation, and status,
 * create and trash actions always need an explicit permission toggle.
 */
( function () {
	'use strict';

	var cfg = window.NCDWorkspace || {};
	var apiFetch = window.wp && window.wp.apiFetch;
	var NS = '/ninecode-data/v1';
	var root = document.getElementById( 'ncd-app' );
	if ( ! root || ! apiFetch ) { return; }

	/* ------------------------------------------------------------------ helpers */

	function h( tag, attrs ) {
		var el = document.createElement( tag );
		var a = attrs || {};
		Object.keys( a ).forEach( function ( k ) {
			var v = a[ k ];
			if ( v === null || v === undefined || v === false ) { return; }
			if ( k === 'class' ) { el.className = v; }
			else if ( k === 'text' ) { el.textContent = v; }
			else if ( k.indexOf( 'on' ) === 0 && typeof v === 'function' ) { el.addEventListener( k.slice( 2 ), v ); }
			else if ( k === 'value' ) { el.value = v; }
			else if ( k === 'checked' || k === 'disabled' || k === 'selected' || k === 'multiple' || k === 'readOnly' ) { el[ k ] = !! v; }
			else { el.setAttribute( k, v === true ? '' : String( v ) ); }
		} );
		for ( var i = 2; i < arguments.length; i++ ) { append( el, arguments[ i ] ); }
		return el;
	}
	function append( el, c ) {
		if ( c === null || c === undefined || c === false ) { return; }
		if ( Array.isArray( c ) ) { c.forEach( function ( x ) { append( el, x ); } ); return; }
		el.appendChild( typeof c === 'object' ? c : document.createTextNode( String( c ) ) );
	}
	function clear( el ) { while ( el.firstChild ) { el.removeChild( el.firstChild ); } return el; }
	function api( path, opts ) {
		var o = opts || {};
		var req = { path: NS + path, method: o.method || 'GET' };
		if ( o.data ) { req.data = o.data; }
		if ( o.body ) { req.body = o.body; }
		return apiFetch( req );
	}
	function qs( params ) {
		var parts = [];
		Object.keys( params ).forEach( function ( k ) {
			var v = params[ k ];
			if ( v === '' || v === null || v === undefined ) { return; }
			parts.push( encodeURIComponent( k ) + '=' + encodeURIComponent( v ) );
		} );
		return parts.length ? '?' + parts.join( '&' ) : '';
	}
	function errText( e ) { return ( e && ( e.message || e.code ) ) || 'Something went wrong.'; }
	function display( v ) {
		if ( v === null || v === undefined ) { return ''; }
		if ( typeof v === 'object' ) { return JSON.stringify( v ); }
		return String( v );
	}
	function clone( v ) { return v === undefined ? v : JSON.parse( JSON.stringify( v ) ); }
	function download( name, content, mime, base64 ) {
		var blob;
		if ( base64 ) {
			var bin = atob( content ), arr = new Uint8Array( bin.length );
			for ( var i = 0; i < bin.length; i++ ) { arr[ i ] = bin.charCodeAt( i ); }
			blob = new Blob( [ arr ], { type: mime } );
		} else {
			blob = new Blob( [ content ], { type: mime } );
		}
		var a = h( 'a', { href: URL.createObjectURL( blob ), download: name } );
		document.body.appendChild( a ); a.click();
		setTimeout( function () { URL.revokeObjectURL( a.href ); a.remove(); }, 1000 );
	}
	function csvLine( cells ) {
		return cells.map( function ( c ) {
			var s = display( c );
			if ( /^[=+\-@\t\r]/.test( s ) ) { s = "'" + s; }
			return /[",\n\r]/.test( s ) ? '"' + s.replace( /"/g, '""' ) + '"' : s;
		} ).join( ',' );
	}

	var toastBox = h( 'div', { class: 'ncd-toasts', role: 'status', 'aria-live': 'polite' } );
	document.body.appendChild( toastBox );
	function toast( msg, kind ) {
		var t = h( 'div', { class: 'ncd-toast ncd-toast--' + ( kind || 'ok' ), text: msg } );
		toastBox.appendChild( t );
		setTimeout( function () { t.remove(); }, kind === 'error' ? 8000 : 4000 );
	}
	function notice( msg, kind ) { return h( 'div', { class: 'ncd-notice ncd-notice--' + ( kind || 'info' ) }, msg ); }
	function spinner( label ) { return h( 'p', { class: 'ncd-loading', text: label || 'Loading…' } ); }
	function button( label, onclick, cls, attrs ) {
		return h( 'button', Object.assign( { type: 'button', class: 'button ' + ( cls || '' ), onclick: onclick }, attrs || {} ), label );
	}
	function toggle( label, help, onchange, checked ) {
		var input = h( 'input', { type: 'checkbox', checked: checked, onchange: function () { onchange( input.checked ); } } );
		return h( 'label', { class: 'ncd-toggle' }, input, h( 'span', null, h( 'strong', null, label ), help ? h( 'small', null, help ) : null ) );
	}
	function modal( title, body, actions ) {
		var dlg = h( 'div', { class: 'ncd-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title } );
		function close() { dlg.remove(); document.removeEventListener( 'keydown', esc ); }
		function esc( e ) { if ( e.key === 'Escape' ) { close(); } }
		document.addEventListener( 'keydown', esc );
		dlg.appendChild( h( 'div', { class: 'ncd-modal__panel' },
			h( 'div', { class: 'ncd-modal__head' }, h( 'h2', { text: title } ), button( '×', close, 'ncd-icon-btn', { 'aria-label': 'Close' } ) ),
			h( 'div', { class: 'ncd-modal__body' }, body ),
			h( 'div', { class: 'ncd-modal__foot' }, ( actions || [] ).map( function ( a ) { return button( a.label, function () { a.run( close ); }, a.cls ); } ), button( 'Cancel', close ) )
		) );
		document.body.appendChild( dlg );
		var f = dlg.querySelector( 'input,select,textarea,button' );
		if ( f ) { f.focus(); }
		return close;
	}
	function confirmBox( title, message, okLabel, cls ) {
		return new Promise( function ( resolve ) {
			var done = false;
			var close = modal( title, h( 'p', { text: message } ), [ { label: okLabel || 'Continue', cls: cls || 'button-primary', run: function ( c ) { done = true; c(); resolve( true ); } } ] );
			var obs = new MutationObserver( function () { if ( ! document.body.contains( document.querySelector( '.ncd-modal' ) ) ) { obs.disconnect(); if ( ! done ) { resolve( false ); } } } );
			obs.observe( document.body, { childList: true } );
			return close;
		} );
	}

	/* ------------------------------------------------------------------ state + routing */

	var state = {
		providers: null,
		entityKey: '',
		entity: null,
		list: { search: '', status: '', orderby: '', order: 'desc', page: 1, per_page: 25 },
		selected: {},
	};

	function route() {
		var parts = ( location.hash.replace( /^#\/?/, '' ) || '' ).split( '/' ).filter( Boolean ).map( decodeURIComponent );
		return parts;
	}
	function go( path ) { location.hash = '#/' + path; }
	window.addEventListener( 'hashchange', render );

	function header( crumbs, actions ) {
		return h( 'div', { class: 'ncd-bar' },
			h( 'nav', { class: 'ncd-crumbs', 'aria-label': 'Breadcrumb' }, crumbs.map( function ( c, i ) {
				return [ i ? h( 'span', { class: 'ncd-crumbs__sep', 'aria-hidden': 'true', text: '›' } ) : null, c.href ? h( 'a', { href: c.href, text: c.label } ) : h( 'span', { text: c.label } ) ];
			} ) ),
			h( 'div', { class: 'ncd-bar__actions' }, actions || [] )
		);
	}

	function loadProviders( force ) {
		if ( state.providers && ! force ) { return Promise.resolve( state.providers ); }
		return api( '/providers' ).then( function ( r ) { state.providers = r; return r; } );
	}
	function loadEntity( p, e ) {
		var key = p + '/' + e;
		if ( state.entity && state.entityKey === key ) { return Promise.resolve( state.entity ); }
		return api( '/entities/' + p + '/' + e ).then( function ( r ) {
			state.entity = r; state.entityKey = key; state.selected = {};
			state.list = { search: '', status: '', orderby: '', order: 'desc', page: 1, per_page: 25 };
			return r;
		} );
	}

	function render() {
		var r = route();
		clear( root );
		if ( ! r.length && cfg.initial && cfg.initial.provider && cfg.initial.entity ) {
			var init = cfg.initial; cfg.initial = null;
			go( init.provider + '/' + init.entity + ( init.id ? '/' + init.id : '' ) ); return;
		}
		if ( ! r.length ) { return viewHome(); }
		if ( r[ 0 ] === 'history' ) { return viewHistory( r[ 1 ] ); }
		if ( r.length < 2 ) { return viewHome(); }
		root.appendChild( spinner() );
		loadEntity( r[ 0 ], r[ 1 ] ).then( function () {
			clear( root );
			if ( r[ 2 ] === 'import' ) { return viewImport(); }
			if ( r[ 2 ] === 'new' ) { return viewRecord( 0 ); }
			if ( r[ 2 ] && /^\d+$/.test( r[ 2 ] ) ) { return viewRecord( parseInt( r[ 2 ], 10 ) ); }
			return viewList();
		} ).catch( function ( e ) { clear( root ); root.appendChild( notice( errText( e ), 'error' ) ); root.appendChild( h( 'p', null, h( 'a', { href: '#/', text: '← All data' } ) ) ); } );
	}

	/* ------------------------------------------------------------------ home: discovery */

	function viewHome() {
		root.appendChild( header( [ { label: 'All data' } ], [ h( 'a', { class: 'button', href: '#/history', text: 'History' } ) ] ) );
		var filter = h( 'input', { type: 'search', class: 'ncd-search', placeholder: 'Find a data type, plugin or app…', 'aria-label': 'Find a data type' } );
		var list = h( 'div', { class: 'ncd-providers' }, spinner() );
		root.appendChild( filter );
		root.appendChild( list );
		loadProviders().then( function ( rep ) {
			function draw() {
				var q = filter.value.trim().toLowerCase();
				clear( list );
				if ( rep.errors && rep.errors.length ) { list.appendChild( notice( [ h( 'strong', { text: 'Some providers could not be registered: ' } ), rep.errors.join( ' · ' ) ], 'warning' ) ); }
				var any = false;
				rep.providers.forEach( function ( p ) {
					var ents = p.entities.filter( function ( e ) { return ! q || ( p.label + ' ' + e.label + ' ' + e.object_type + ' ' + e.id ).toLowerCase().indexOf( q ) !== -1; } );
					if ( ! ents.length ) { return; }
					any = true;
					list.appendChild( h( 'section', { class: 'ncd-provider' },
						h( 'header', { class: 'ncd-provider__head' },
							h( 'h2', { text: p.label } ),
							h( 'span', { class: 'ncd-badge ncd-badge--' + p.adapter, text: p.adapter === 'generic' ? 'Auto-discovered' : ( p.adapter === 'declared' ? 'Declared' : 'Provider' + ( p.version ? ' ' + p.version : '' ) ) } )
						),
						p.description ? h( 'p', { class: 'ncd-muted', text: p.description } ) : null,
						h( 'div', { class: 'ncd-entity-grid' }, ents.map( function ( e ) {
							return h( 'a', { class: 'ncd-entity-card', href: '#/' + p.id + '/' + e.id },
								h( 'strong', { text: e.label } ),
								h( 'span', { class: 'ncd-muted', text: e.kind + ( e.object_type ? ' · ' + e.object_type : '' ) } ),
								h( 'span', { class: 'ncd-counts' },
									h( 'span', { text: e.counts.writable + ' editable' } ),
									e.counts.protected ? h( 'span', null, lock(), e.counts.protected + ' protected' ) : null,
									e.counts.acf ? h( 'span', { text: e.counts.acf + ' ACF' } ) : null
								)
							);
						} ) )
					) );
				} );
				if ( ! any ) { list.appendChild( notice( 'No data types match.', 'info' ) ); }
			}
			filter.addEventListener( 'input', draw );
			draw();
		} ).catch( function ( e ) { clear( list ); list.appendChild( notice( errText( e ), 'error' ) ); } );
	}

	/* ------------------------------------------------------------------ list */

	function viewList() {
		var e = state.entity, L = state.list, base = e.provider + '/' + e.id;
		var actions = [
			h( 'a', { class: 'button', href: '#/' + base + '/import', text: 'Import' } ),
			button( 'Export', function () { exportDialog(); } ),
		];
		if ( e.can_create ) { actions.push( h( 'a', { class: 'button button-primary', href: '#/' + base + '/new', text: 'New ' + e.singular } ) ); }
		root.appendChild( header( [ { label: 'All data', href: '#/' }, { label: e.label } ], actions ) );
		if ( e.description ) { root.appendChild( h( 'p', { class: 'ncd-muted', text: e.description } ) ); }
		( e.notes || [] ).forEach( function ( n ) { root.appendChild( notice( n, 'info' ) ); } );

		var search = h( 'input', { type: 'search', class: 'ncd-search', placeholder: 'Search ' + e.label.toLowerCase() + '…', value: L.search, 'aria-label': 'Search' } );
		var status = null;
		if ( e.kind === 'post' || ( e.statuses && e.statuses.length ) ) {
			status = h( 'select', { 'aria-label': 'Status' }, h( 'option', { value: '', text: 'All statuses' } ), ( e.statuses || [] ).concat( e.kind === 'post' ? [ 'trash' ] : [] ).map( function ( s ) { return h( 'option', { value: s, text: s, selected: L.status === s } ); } ) );
		}
		var sort = h( 'select', { 'aria-label': 'Sort' },
			[ [ '', 'Recently changed' ], [ 'title', 'Title' ], [ 'date', 'Date' ], [ 'id', 'ID' ] ].map( function ( o ) { return h( 'option', { value: o[ 0 ], text: o[ 1 ], selected: L.orderby === o[ 0 ] } ); } ) );
		var order = button( L.order === 'asc' ? '↑ Asc' : '↓ Desc', function () { L.order = L.order === 'asc' ? 'desc' : 'asc'; order.textContent = L.order === 'asc' ? '↑ Asc' : '↓ Desc'; L.page = 1; load(); }, 'ncd-order' );
		var perPage = h( 'select', { 'aria-label': 'Per page' }, [ 25, 50, 100 ].map( function ( n ) { return h( 'option', { value: n, text: n + ' / page', selected: L.per_page === n } ); } ) );
		var tools = h( 'div', { class: 'ncd-tools' }, search, status, sort, order, perPage );
		root.appendChild( tools );

		var bulkBar = h( 'div', { class: 'ncd-bulkbar', hidden: true } );
		var body = h( 'div', { class: 'ncd-list' } );
		var pager = h( 'div', { class: 'ncd-pager' } );
		root.appendChild( bulkBar );
		root.appendChild( body );
		root.appendChild( pager );

		var t;
		search.addEventListener( 'input', function () { clearTimeout( t ); t = setTimeout( function () { L.search = search.value; L.page = 1; load(); }, 300 ); } );
		if ( status ) { status.addEventListener( 'change', function () { L.status = status.value; L.page = 1; load(); } ); }
		sort.addEventListener( 'change', function () { L.orderby = sort.value; L.page = 1; load(); } );
		perPage.addEventListener( 'change', function () { L.per_page = parseInt( perPage.value, 10 ); L.page = 1; load(); } );

		function drawBulk() {
			var ids = Object.keys( state.selected );
			bulkBar.hidden = ! ids.length;
			clear( bulkBar );
			if ( ! ids.length ) { return; }
			bulkBar.appendChild( h( 'span', { text: ids.length + ' selected' } ) );
			bulkBar.appendChild( button( 'Edit selected', function () { bulkDialog( ids, load ); }, 'button-primary' ) );
			bulkBar.appendChild( button( 'Export selected', function () { exportDialog( ids ); } ) );
			bulkBar.appendChild( button( 'Clear', function () { state.selected = {}; load(); }, 'button-link' ) );
		}

		function load() {
			clear( body ).appendChild( spinner() );
			api( '/records/' + base + qs( { search: L.search, status: L.status, orderby: L.orderby, order: L.order, page: L.page, per_page: L.per_page } ) ).then( function ( r ) {
				clear( body );
				drawBulk();
				if ( ! r.rows.length ) { body.appendChild( notice( L.search || L.status ? 'No records match these filters.' : 'No records yet.', 'info' ) ); clear( pager ); return; }
				var cols = r.columns.filter( function ( c ) { return e.fields[ c ]; } );
				var all = h( 'input', { type: 'checkbox', 'aria-label': 'Select all on this page', checked: r.rows.every( function ( row ) { return state.selected[ row.summary.id ]; } ), onchange: function () {
					r.rows.forEach( function ( row ) { if ( all.checked ) { state.selected[ row.summary.id ] = true; } else { delete state.selected[ row.summary.id ]; } } );
					body.querySelectorAll( 'tbody input[type=checkbox]' ).forEach( function ( c ) { c.checked = all.checked; } );
					drawBulk();
				} } );
				var table = h( 'table', { class: 'ncd-table' },
					h( 'thead', null, h( 'tr', null, h( 'th', { class: 'ncd-col-check' }, all ), h( 'th', { text: e.singular } ), cols.filter( function ( c ) { return c !== 'post_title' && c !== 'name' && c !== 'display_name'; } ).map( function ( c ) { return h( 'th', { text: e.fields[ c ].label } ); } ) ) ),
					h( 'tbody', null, r.rows.map( function ( row ) {
						var id = row.summary.id;
						var cb = h( 'input', { type: 'checkbox', 'aria-label': 'Select ' + row.summary.label, checked: !! state.selected[ id ], onchange: function () { if ( cb.checked ) { state.selected[ id ] = true; } else { delete state.selected[ id ]; } drawBulk(); } } );
						return h( 'tr', null,
							h( 'td', { class: 'ncd-col-check' }, cb ),
							h( 'td', { class: 'ncd-col-title', 'data-label': e.singular },
								h( 'a', { href: '#/' + base + '/' + id, text: row.summary.label || '#' + id } ),
								h( 'span', { class: 'ncd-row-meta' }, '#' + id, row.summary.status ? h( 'span', { class: 'ncd-status ncd-status--' + row.summary.status, text: row.summary.status } ) : null )
							),
							cols.filter( function ( c ) { return c !== 'post_title' && c !== 'name' && c !== 'display_name'; } ).map( function ( c ) {
								return h( 'td', { 'data-label': e.fields[ c ].label, text: short( row.values[ c ], e.fields[ c ] ) } );
							} )
						);
					} ) )
				);
				body.appendChild( table );
				var pages = Math.max( 1, Math.ceil( r.total / r.per_page ) );
				clear( pager ).appendChild( h( 'span', { text: r.total + ' records · page ' + r.page + ' of ' + pages } ) );
				pager.appendChild( button( '‹ Previous', function () { L.page--; load(); }, '', { disabled: r.page <= 1 } ) );
				pager.appendChild( button( 'Next ›', function () { L.page++; load(); }, '', { disabled: r.page >= pages } ) );
			} ).catch( function ( err ) { clear( body ).appendChild( notice( errText( err ), 'error' ) ); } );
		}
		load();
	}

	function short( v, f ) {
		if ( v === null || v === undefined || v === '' ) { return '—'; }
		if ( f && ( f.type === 'boolean' || f.type === 'checkbox' ) && ! Array.isArray( v ) ) { return v ? 'Yes' : 'No'; }
		var s = Array.isArray( v ) ? v.map( display ).join( ', ' ) : display( v );
		s = s.replace( /<[^>]*>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
		return s.length > 80 ? s.slice( 0, 80 ) + '…' : s;
	}

	/* ------------------------------------------------------------------ record editor */

	var SIMPLE_INPUT = { text: 'text', email: 'email', url: 'url', number: 'number', integer: 'number', date: 'date', datetime: 'datetime-local', year: 'number' };

	function viewRecord( id ) {
		var e = state.entity, base = e.provider + '/' + e.id, isNew = ! id;
		root.appendChild( header( [ { label: 'All data', href: '#/' }, { label: e.label, href: '#/' + base }, { label: isNew ? 'New ' + e.singular : '#' + id } ] ) );
		var box = h( 'div', null, spinner() );
		root.appendChild( box );
		var load = isNew ? Promise.resolve( { values: {}, access: null, summary: { label: 'New ' + e.singular }, revision: '', can_publish: false, can_trash: false } ) : api( '/records/' + base + '/' + id );
		load.then( function ( rec ) { clear( box ); editor( box, rec, id ); } ).catch( function ( err ) { clear( box ).appendChild( notice( errText( err ), 'error' ) ); } );
	}

	function editor( box, rec, id ) {
		var e = state.entity, base = e.provider + '/' + e.id, isNew = ! id;
		var changes = {};
		var flags = { allow_status: false, allow_trash: false };
		var publishField = e.publish_field;

		box.appendChild( h( 'div', { class: 'ncd-record-head' },
			h( 'h2', { text: rec.summary.label || ( isNew ? 'New ' + e.singular : '#' + id ) } ),
			h( 'div', { class: 'ncd-record-links' },
				rec.summary.status ? h( 'span', { class: 'ncd-status ncd-status--' + rec.summary.status, text: rec.summary.status } ) : null,
				rec.summary.view_url ? h( 'a', { href: rec.summary.view_url, target: '_blank', rel: 'noopener', text: 'View' } ) : null,
				rec.summary.edit_url ? h( 'a', { href: rec.summary.edit_url, text: 'Open in WordPress' } ) : null
			)
		) );

		var errorsBox = h( 'div' );
		box.appendChild( errorsBox );

		// Group fields.
		var groups = {}, order = [];
		Object.keys( e.fields ).forEach( function ( k ) {
			var f = e.fields[ k ];
			var g = f.group || 'details';
			if ( ! groups[ g ] ) { groups[ g ] = []; order.push( g ); }
			groups[ g ].push( k );
		} );
		order.sort( function ( a, b ) { return ( a === 'protected' ) - ( b === 'protected' ); } );
		var form = h( 'form', { class: 'ncd-form', novalidate: true, onsubmit: function ( ev ) { ev.preventDefault(); save(); } } );
		order.forEach( function ( g, gi ) {
			var sec = h( 'details', { class: 'ncd-section' + ( g === 'protected' ? ' ncd-section--locked' : '' ), open: g !== 'protected' && gi < 3 },
				h( 'summary', null, g === 'protected' ? [ lock(), 'Protected & read-only (' + groups[ g ].length + ')' ] : humanize( g ) + ' (' + groups[ g ].length + ')' ) );
			groups[ g ].forEach( function ( k ) {
				var f = e.fields[ k ];
				var acc = rec.access ? rec.access[ k ] : { writable: ! f.protected && f.writable, reason: f.reason };
				if ( isNew && ( f.protected || ! f.writable ) ) { return; }
				sec.appendChild( fieldRow( f, k, rec.values[ k ], acc || { writable: false, reason: 'Read-only' }, function ( v ) {
					changes[ k ] = v;
					if ( JSON.stringify( v ) === JSON.stringify( rec.values[ k ] ) ) { delete changes[ k ]; }
					dirty();
				}, k === publishField ) );
			} );
			form.appendChild( sec );
		} );
		box.appendChild( form );

		var dirtyLabel = h( 'span', { class: 'ncd-dirty', text: 'No changes' } );
		var statusToggle = publishField && ( isNew || rec.can_publish ) ? toggle( 'Allow status change', 'Publishing, unpublishing or scheduling only happens when this is ticked.', function ( v ) { flags.allow_status = v; }, false ) : null;
		var saveBtn = button( isNew ? 'Create ' + e.singular : 'Save changes', save, 'button-primary', { disabled: true } );
		var bar = h( 'div', { class: 'ncd-savebar' }, dirtyLabel, statusToggle, h( 'div', { class: 'ncd-savebar__btns' },
			! isNew && rec.can_trash ? button( 'Move to trash', trash, 'button-link-delete' ) : null,
			saveBtn ) );
		box.appendChild( bar );

		function dirty() {
			var n = Object.keys( changes ).length;
			dirtyLabel.textContent = n ? n + ' unsaved change' + ( n > 1 ? 's' : '' ) : 'No changes';
			saveBtn.disabled = ! n;
			bar.classList.toggle( 'is-dirty', !! n );
		}
		function showErrors( errs ) {
			clear( errorsBox );
			form.querySelectorAll( '.ncd-field.has-error' ).forEach( function ( el ) { el.classList.remove( 'has-error' ); } );
			var keys = Object.keys( errs || {} );
			if ( ! keys.length ) { return; }
			errorsBox.appendChild( notice( [ h( 'strong', { text: 'Not saved. Fix these fields:' } ), h( 'ul', null, keys.map( function ( k ) {
				var row = form.querySelector( '[data-field="' + CSS.escape( k ) + '"]' );
				if ( row ) { row.classList.add( 'has-error' ); var d = row.closest( 'details' ); if ( d ) { d.open = true; } }
				return h( 'li', null, h( 'strong', { text: ( e.fields[ k ] ? e.fields[ k ].label : k ) + ': ' } ), errs[ k ] );
			} ) ) ], 'error' ) );
			errorsBox.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
		function save() {
			if ( ! Object.keys( changes ).length ) { return; }
			saveBtn.disabled = true;
			var req = isNew
				? api( '/records/' + base, { method: 'POST', data: { values: changes, allow_create: true, allow_status: flags.allow_status } } )
				: api( '/records/' + base + '/' + id, { method: 'POST', data: { changes: changes, expected_revision: rec.revision, allow_status: flags.allow_status, source: window.matchMedia( '(max-width: 782px)' ).matches ? 'mobile' : 'editor' } } );
			req.then( function ( r ) {
				if ( ! r.ok && r.errors && Object.keys( r.errors ).length ) { showErrors( r.errors ); saveBtn.disabled = false; return; }
				toast( isNew ? 'Created.' : 'Saved ' + ( r.changed || [] ).length + ' field(s).' + ( r.history_id ? ' Undo from History.' : '' ) );
				( r.warnings || [] ).forEach( function ( w ) { toast( w, 'warning' ); } );
				if ( isNew ) { go( base + '/' + r.id ); } else { render(); }
			} ).catch( function ( err ) { saveBtn.disabled = false; toast( errText( err ), 'error' ); } );
		}
		function trash() {
			confirmBox( 'Move to trash?', '“' + ( rec.summary.label || '#' + id ) + '” will be moved to the trash. You can undo this from History.', 'Move to trash', 'button-primary ncd-danger' ).then( function ( ok ) {
				if ( ! ok ) { return; }
				api( '/records/' + base + '/' + id + '/trash', { method: 'POST', data: { allow_trash: true } } ).then( function () {
					toast( 'Moved to trash. Undo from History.' ); go( base );
				} ).catch( function ( err ) { toast( errText( err ), 'error' ); } );
			} );
		}
		window.onbeforeunload = function () { return Object.keys( changes ).length ? true : undefined; };
	}

	function lock() { return h( 'span', { class: 'dashicons dashicons-lock ncd-lock', 'aria-hidden': 'true' } ); }

	function humanize( s ) { s = String( s ).replace( /[_-]+/g, ' ' ); return s.charAt( 0 ).toUpperCase() + s.slice( 1 ); }

	function fieldRow( f, key, value, acc, onchange, isStatus ) {
		var id = 'ncd-f-' + key.replace( /[^a-z0-9_-]/gi, '_' );
		var row = h( 'div', { class: 'ncd-field' + ( acc.writable ? '' : ' is-locked' ), 'data-field': key } );
		var label = h( 'label', { for: id, class: 'ncd-field__label' }, f.label, f.required ? h( 'span', { class: 'ncd-req', 'aria-hidden': 'true', text: ' *' } ) : null );
		row.appendChild( label );
		if ( ! acc.writable ) {
			row.appendChild( h( 'div', { class: 'ncd-locked-value', id: id }, short( value, f ) === '—' ? h( 'em', { text: 'empty' } ) : ( f.type === 'html' || f.type === 'json' || f.type === 'group' || f.type === 'repeater' || f.type === 'flexible' ? h( 'code', { text: short( value, f ) } ) : display( Array.isArray( value ) ? value.join( ', ' ) : value ) ) ) );
			row.appendChild( h( 'small', { class: 'ncd-lock-reason' }, lock(), acc.reason || 'Read-only' ) );
			return row;
		}
		row.appendChild( control( f, id, value, onchange, isStatus ) );
		if ( isStatus && ! f.help ) { row.appendChild( h( 'small', { class: 'ncd-help', text: 'Changing this needs “Allow status change”.' } ) ); }
		if ( f.help ) { row.appendChild( h( 'small', { class: 'ncd-help', text: f.help } ) ); }
		return row;
	}

	/** Builds an input for a field type; calls onchange(value) with the full new value. */
	function control( f, id, value, onchange, isStatus ) {
		var t = f.type;
		if ( SIMPLE_INPUT[ t ] ) {
			var v = value === null || value === undefined ? '' : value;
			if ( t === 'datetime' && v ) { v = String( v ).replace( ' ', 'T' ).slice( 0, 16 ); }
			var inp = h( 'input', { id: id, type: SIMPLE_INPUT[ t ], value: v, step: t === 'number' ? 'any' : null, min: f.min !== null && f.min !== undefined ? f.min : null, max: f.max !== null && f.max !== undefined ? f.max : null, required: f.required, placeholder: f.example || null } );
			inp.addEventListener( 'input', function () {
				var out = inp.value;
				if ( t === 'integer' || t === 'year' ) { out = out === '' ? '' : parseInt( out, 10 ); }
				if ( t === 'number' ) { out = out === '' ? '' : parseFloat( out ); }
				if ( t === 'datetime' && out ) { out = out.replace( 'T', ' ' ) + ':00'; }
				onchange( out );
			} );
			return inp;
		}
		if ( t === 'textarea' ) {
			var ta = h( 'textarea', { id: id, rows: 4, value: display( value ) } );
			ta.addEventListener( 'input', function () { onchange( ta.value ); } );
			return ta;
		}
		if ( t === 'html' ) { return htmlControl( id, value, onchange ); }
		if ( t === 'json' ) {
			var jt = h( 'textarea', { id: id, rows: 5, class: 'ncd-code', value: value === '' || value === null || value === undefined ? '' : JSON.stringify( value, null, 2 ) } );
			var jerr = h( 'small', { class: 'ncd-inline-error', hidden: true } );
			jt.addEventListener( 'input', function () {
				if ( jt.value.trim() === '' ) { jerr.hidden = true; onchange( '' ); return; }
				try { onchange( JSON.parse( jt.value ) ); jerr.hidden = true; } catch ( x ) { jerr.textContent = 'Not valid JSON yet.'; jerr.hidden = false; }
			} );
			return h( 'div', null, jt, jerr );
		}
		if ( t === 'select' || t === 'status' ) {
			var choices = f.choices || {};
			if ( t === 'status' && ! Object.keys( choices ).length ) { ( state.entity.statuses || [] ).forEach( function ( s ) { choices[ s ] = humanize( s ); } ); }
			var sel = h( 'select', { id: id }, f.required ? null : h( 'option', { value: '', text: '—' } ),
				Object.keys( choices ).map( function ( c ) { return h( 'option', { value: c, text: choices[ c ], selected: String( value ) === c } ); } ) );
			if ( value !== '' && value !== null && value !== undefined && ! Object.prototype.hasOwnProperty.call( choices, String( value ) ) ) { sel.appendChild( h( 'option', { value: String( value ), text: String( value ) + ' (current)', selected: true } ) ); }
			sel.addEventListener( 'change', function () { onchange( sel.value ); } );
			return sel;
		}
		if ( t === 'multiselect' || ( t === 'checkbox' && f.choices && Object.keys( f.choices ).length ) ) {
			var cur = Array.isArray( value ) ? value.map( String ) : ( value ? [ String( value ) ] : [] );
			var wrap = h( 'fieldset', { class: 'ncd-checks', id: id } );
			Object.keys( f.choices || {} ).forEach( function ( c ) {
				var cb = h( 'input', { type: 'checkbox', value: c, checked: cur.indexOf( c ) !== -1 } );
				cb.addEventListener( 'change', function () {
					cur = Array.prototype.map.call( wrap.querySelectorAll( 'input:checked' ), function ( x ) { return x.value; } );
					onchange( cur );
				} );
				wrap.appendChild( h( 'label', null, cb, ' ', f.choices[ c ] ) );
			} );
			return wrap;
		}
		if ( t === 'boolean' || t === 'checkbox' ) {
			var b = h( 'input', { id: id, type: 'checkbox', checked: !! value && value !== '0' } );
			b.addEventListener( 'change', function () { onchange( b.checked ); } );
			return h( 'label', { class: 'ncd-switch' }, b, h( 'span', { text: 'Yes' } ) );
		}
		if ( t === 'media' || t === 'media_list' ) { return mediaControl( f, id, value, onchange ); }
		if ( /^(post|user|term)(_list)?$/.test( t ) ) { return refControl( f, id, value, onchange ); }
		if ( t === 'group' ) { return groupControl( f, value, onchange ); }
		if ( t === 'repeater' ) { return repeaterControl( f, value, onchange ); }
		if ( t === 'flexible' ) { return flexibleControl( f, value, onchange ); }
		return h( 'div', { class: 'ncd-locked-value', text: display( value ) } );
	}

	var editorSeq = 0;
	function htmlControl( id, value, onchange ) {
		var ta = h( 'textarea', { id: id, rows: 10, class: 'ncd-html', value: display( value ) } );
		ta.addEventListener( 'input', function () { onchange( ta.value ); } );
		var wrap = h( 'div', { class: 'ncd-html-wrap' }, ta );
		if ( window.wp && window.wp.editor && window.wp.editor.initialize ) {
			var eid = id + '-' + ( ++editorSeq );
			ta.id = eid;
			setTimeout( function () {
				if ( ! document.getElementById( eid ) ) { return; }
				window.wp.editor.initialize( eid, {
					tinymce: { wpautop: true, plugins: 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview', toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,wp_more,fullscreen,wp_adv', toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo', setup: function ( ed ) { ed.on( 'change keyup undo redo SetContent', function () { onchange( ed.getContent() ); } ); } },
					quicktags: true,
					mediaButtons: true,
				} );
			}, 0 );
		}
		return wrap;
	}

	function mediaControl( f, id, value, onchange ) {
		var multi = f.type === 'media_list';
		var ids = ( multi ? ( Array.isArray( value ) ? value : [] ) : ( value ? [ value ] : [] ) ).map( function ( x ) { return parseInt( x, 10 ); } ).filter( Boolean );
		var list = h( 'div', { class: 'ncd-media' } );
		var wrap = h( 'div', { id: id }, list );
		function emit() { onchange( multi ? ids.slice() : ( ids[ 0 ] || 0 ) ); }
		function draw( items ) {
			clear( list );
			ids.forEach( function ( mid, i ) {
				var it = ( items || [] ).filter( function ( x ) { return x.id === mid; } )[ 0 ] || { id: mid, label: '#' + mid };
				list.appendChild( h( 'figure', { class: 'ncd-media__item' },
					it.thumb ? h( 'img', { src: it.thumb, alt: '' } ) : h( 'span', { class: 'ncd-media__ph dashicons dashicons-media-default', 'aria-hidden': 'true' } ),
					h( 'figcaption', { text: it.label } ),
					button( '×', function () { ids.splice( i, 1 ); emit(); draw( items ); }, 'ncd-icon-btn', { 'aria-label': 'Remove ' + it.label } )
				) );
			} );
		}
		function refresh() {
			if ( ! ids.length ) { draw( [] ); return; }
			api( '/lookup?type=media' + ids.map( function ( x ) { return '&ids[]=' + x; } ).join( '' ) ).then( draw ).catch( function () { draw( [] ); } );
		}
		var pick = button( multi ? 'Add media' : ( ids.length ? 'Replace' : 'Choose media' ), function () {
			if ( ! window.wp || ! window.wp.media ) { toast( 'The media library is not available here.', 'error' ); return; }
			var frame = window.wp.media( { title: f.label, multiple: multi ? 'add' : false, library: f.target ? { type: f.target } : {} } );
			frame.on( 'select', function () {
				var picked = frame.state().get( 'selection' ).toJSON().map( function ( a ) { return a.id; } );
				ids = multi ? ids.concat( picked.filter( function ( p ) { return ids.indexOf( p ) === -1; } ) ) : picked.slice( 0, 1 );
				emit(); refresh();
			} );
			frame.open();
		} );
		wrap.appendChild( pick );
		refresh();
		return wrap;
	}

	function refControl( f, id, value, onchange ) {
		var kind = f.type.replace( '_list', '' ), multi = /_list$/.test( f.type );
		var ids = ( multi ? ( Array.isArray( value ) ? value : ( value ? String( value ).split( ',' ) : [] ) ) : ( value ? [ value ] : [] ) ).map( function ( x ) { return parseInt( x, 10 ); } ).filter( Boolean );
		var labels = {};
		var chips = h( 'div', { class: 'ncd-chips' } );
		var input = h( 'input', { id: id, type: 'search', placeholder: 'Search ' + ( f.target || kind ) + '…', autocomplete: 'off' } );
		var results = h( 'ul', { class: 'ncd-results', role: 'listbox', hidden: true } );
		function emit() { onchange( multi ? ids.slice() : ( ids[ 0 ] || 0 ) ); }
		function draw() {
			clear( chips );
			ids.forEach( function ( x, i ) {
				chips.appendChild( h( 'span', { class: 'ncd-chip' }, labels[ x ] || '#' + x, button( '×', function () { ids.splice( i, 1 ); emit(); draw(); }, 'ncd-icon-btn', { 'aria-label': 'Remove' } ) ) );
			} );
		}
		function lookup( params ) { return api( '/lookup' + qs( Object.assign( { type: kind, target: f.target || '' }, params ) ) ); }
		if ( ids.length ) {
			api( '/lookup?type=' + kind + '&target=' + encodeURIComponent( f.target || '' ) + ids.map( function ( x ) { return '&ids[]=' + x; } ).join( '' ) ).then( function ( r ) { r.forEach( function ( it ) { labels[ it.id ] = it.label; } ); draw(); } ).catch( draw );
		}
		var t;
		input.addEventListener( 'input', function () {
			clearTimeout( t );
			var q = input.value.trim();
			if ( q.length < 2 ) { results.hidden = true; return; }
			t = setTimeout( function () {
				lookup( { search: q } ).then( function ( r ) {
					clear( results ); results.hidden = ! r.length;
					r.forEach( function ( it ) {
						results.appendChild( h( 'li', null, button( [ it.label, h( 'small', { text: ' ' + ( it.meta || '' ) } ) ], function () {
							labels[ it.id ] = it.label;
							if ( multi ) { if ( ids.indexOf( it.id ) === -1 ) { ids.push( it.id ); } } else { ids = [ it.id ]; }
							input.value = ''; results.hidden = true; emit(); draw();
						}, 'button-link' ) ) );
					} );
				} ).catch( function ( err ) { toast( errText( err ), 'error' ); } );
			}, 250 );
		} );
		draw();
		return h( 'div', { class: 'ncd-ref' }, chips, input, results );
	}

	function subFields( fields, value, onchange, prefix ) {
		var v = value && typeof value === 'object' && ! Array.isArray( value ) ? clone( value ) : {};
		var box = h( 'div', { class: 'ncd-sub' } );
		Object.keys( fields || {} ).forEach( function ( k ) {
			var sf = fields[ k ];
			var acc = { writable: ! sf.protected && sf.writable !== false, reason: sf.reason };
			box.appendChild( fieldRow( sf, prefix + '-' + k, v[ k ], acc, function ( nv ) { v[ k ] = nv; onchange( clone( v ) ); }, false ) );
		} );
		return box;
	}
	function groupControl( f, value, onchange ) { return h( 'fieldset', { class: 'ncd-group' }, subFields( f.sub_fields, value, onchange, f.key ) ); }

	function rowsControl( f, value, onchange, makeRow, addControls ) {
		var rows = Array.isArray( value ) ? clone( value ) : [];
		var list = h( 'ol', { class: 'ncd-rows' } );
		function emit() { onchange( clone( rows ) ); }
		function draw() {
			clear( list );
			rows.forEach( function ( r, i ) {
				list.appendChild( h( 'li', { class: 'ncd-rows__item' },
					h( 'div', { class: 'ncd-rows__tools' },
						h( 'span', { text: makeRow.title( r, i ) } ),
						button( '↑', function () { if ( i ) { rows.splice( i - 1, 0, rows.splice( i, 1 )[ 0 ] ); emit(); draw(); } }, 'ncd-icon-btn', { 'aria-label': 'Move up', disabled: ! i } ),
						button( '↓', function () { if ( i < rows.length - 1 ) { rows.splice( i + 1, 0, rows.splice( i, 1 )[ 0 ] ); emit(); draw(); } }, 'ncd-icon-btn', { 'aria-label': 'Move down', disabled: i === rows.length - 1 } ),
						button( 'Remove', function () { rows.splice( i, 1 ); emit(); draw(); }, 'button-link-delete' )
					),
					makeRow.body( r, i, function ( nv ) { rows[ i ] = nv; emit(); } )
				) );
			} );
		}
		draw();
		var max = f.max ? parseInt( f.max, 10 ) : 0;
		return h( 'div', { class: 'ncd-repeater' }, list, addControls( function ( row ) {
			if ( max && rows.length >= max ) { toast( 'At most ' + max + ' rows.', 'warning' ); return; }
			rows.push( row ); emit(); draw();
		} ) );
	}
	function repeaterControl( f, value, onchange ) {
		return rowsControl( f, value, onchange, {
			title: function ( r, i ) { return 'Row ' + ( i + 1 ); },
			body: function ( r, i, set ) { return subFields( f.sub_fields, r, set, f.key + '-' + i ); },
		}, function ( add ) { return button( '+ Add row', function () { add( {} ); } ); } );
	}
	function flexibleControl( f, value, onchange ) {
		var layouts = f.layouts || {};
		return rowsControl( f, value, onchange, {
			title: function ( r, i ) { var l = layouts[ r.acf_fc_layout ]; return ( i + 1 ) + '. ' + ( l ? l.label : r.acf_fc_layout || 'Layout' ); },
			body: function ( r, i, set ) {
				var l = layouts[ r.acf_fc_layout ] || { sub_fields: {} };
				return subFields( l.sub_fields, r, function ( nv ) { nv.acf_fc_layout = r.acf_fc_layout; set( nv ); }, f.key + '-' + i );
			},
		}, function ( add ) {
			var sel = h( 'select', { 'aria-label': 'Layout' }, Object.keys( layouts ).map( function ( k ) { return h( 'option', { value: k, text: layouts[ k ].label || k } ); } ) );
			return h( 'div', { class: 'ncd-inline' }, sel, button( '+ Add layout', function () { add( { acf_fc_layout: sel.value } ); } ) );
		} );
	}

	/* ------------------------------------------------------------------ bulk edit */

	var BULK_TYPES = [ 'text', 'textarea', 'number', 'integer', 'email', 'url', 'date', 'datetime', 'year', 'select', 'status', 'boolean', 'checkbox', 'term_list', 'term', 'user', 'multiselect' ];

	function bulkDialog( ids, done ) {
		var e = state.entity, base = e.provider + '/' + e.id;
		var keys = Object.keys( e.fields ).filter( function ( k ) { var f = e.fields[ k ]; return ! f.protected && f.writable && BULK_TYPES.indexOf( f.type ) !== -1; } );
		if ( ! keys.length ) { toast( 'No fields of this type can be bulk edited.', 'warning' ); return; }
		var value = '', allowStatus = false;
		var slot = h( 'div' );
		var pick = h( 'select', { 'aria-label': 'Field' }, keys.map( function ( k ) { return h( 'option', { value: k, text: e.fields[ k ].label } ); } ) );
		var statusSlot = h( 'div' );
		function drawControl() {
			var f = e.fields[ pick.value ];
			value = f.type === 'boolean' ? false : '';
			clear( slot ).appendChild( control( f, 'ncd-bulk-value', value, function ( v ) { value = v; }, pick.value === e.publish_field ) );
			clear( statusSlot );
			if ( pick.value === e.publish_field ) { statusSlot.appendChild( toggle( 'Allow status change', 'Required to publish, unpublish or schedule these records.', function ( v ) { allowStatus = v; }, false ) ); }
		}
		pick.addEventListener( 'change', drawControl );
		drawControl();
		modal( 'Edit ' + ids.length + ' record(s)', [
			h( 'p', { class: 'ncd-muted', text: 'The same value is validated and saved on every selected record. Records you cannot edit are skipped and reported. One History entry is created so the whole edit can be undone.' } ),
			h( 'label', { class: 'ncd-field__label', text: 'Field' } ), pick,
			h( 'label', { class: 'ncd-field__label', text: 'New value' } ), slot, statusSlot,
		], [ { label: 'Apply to ' + ids.length, cls: 'button-primary', run: function ( close ) {
			var ch = {}; ch[ pick.value ] = value;
			api( '/bulk/' + base, { method: 'POST', data: { ids: ids.map( Number ), changes: ch, allow_status: allowStatus } } ).then( function ( r ) {
				close();
				var failed = r.results.filter( function ( x ) { return ! x.ok; } );
				toast( r.changed_records + ' record(s) changed.' + ( failed.length ? ' ' + failed.length + ' failed.' : '' ), failed.length ? 'warning' : 'ok' );
				if ( failed.length ) {
					modal( 'Some records were not changed', h( 'ul', null, failed.map( function ( x ) { return h( 'li', null, '#' + x.id + ': ' + Object.keys( x.errors || {} ).map( function ( k ) { return x.errors[ k ]; } ).join( '; ' ) ); } ) ) );
				}
				state.selected = {};
				done();
			} ).catch( function ( err ) { toast( errText( err ), 'error' ); } );
		} } ] );
	}

	/* ------------------------------------------------------------------ export */

	function exportDialog( ids ) {
		var e = state.entity, base = e.provider + '/' + e.id, L = state.list;
		var format = 'json';
		var formats = h( 'fieldset', { class: 'ncd-choice' },
			[ [ 'json', 'AI package (JSON)', 'Records with the field schema and editing instructions for an AI assistant. Nothing is sent anywhere; you download the file.' ],
				[ 'xlsx', 'Excel (XLSX)', 'One row per record plus a Guide sheet describing every column.' ],
				[ 'csv', 'CSV', 'Plain spreadsheet; open with UTF-8.' ] ].map( function ( o, i ) {
				var r = h( 'input', { type: 'radio', name: 'ncd-format', value: o[ 0 ], checked: ! i, onchange: function () { format = o[ 0 ]; } } );
				return h( 'label', null, r, h( 'span', null, h( 'strong', { text: o[ 1 ] } ), h( 'small', { text: o[ 2 ] } ) ) );
			} ) );
		var scope = ids && ids.length ? ids.length + ' selected record(s)' : ( L.search || L.status ? 'Records matching the current search/filter' : 'All records (up to 5,000)' );
		modal( 'Export ' + e.label, [ h( 'p', null, h( 'strong', { text: 'Scope: ' } ), scope ), formats,
			h( 'p', { class: 'ncd-muted', text: 'Protected values are exported as read-only context; secrets are never exported. Record IDs and revision fingerprints let the import detect conflicts.' } ) ],
		[ { label: 'Download', cls: 'button-primary', run: function ( close ) {
			var data = { format: format };
			if ( ids && ids.length ) { data.ids = ids.map( Number ); } else { data.search = L.search; data.status = L.status; }
			api( '/export/' + base, { method: 'POST', data: data } ).then( function ( r ) {
				download( r.filename, r.content, r.mime, r.encoding === 'base64' );
				toast( 'Exported ' + r.count + ' record(s).' );
				close();
			} ).catch( function ( err ) { toast( errText( err ), 'error' ); } );
		} } ] );
	}

	/* ------------------------------------------------------------------ import */

	function viewImport() {
		var e = state.entity, base = e.provider + '/' + e.id;
		root.appendChild( header( [ { label: 'All data', href: '#/' }, { label: e.label, href: '#/' + base }, { label: 'Import' } ] ) );
		var opts = { allow_status: false, allow_create: false, allow_trash: false };
		var stage = h( 'div', { class: 'ncd-card' } );
		var file = h( 'input', { type: 'file', accept: '.json,.csv,.xlsx,application/json,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'aria-label': 'Package file' } );
		var paste = h( 'textarea', { rows: 6, class: 'ncd-code', placeholder: '…or paste the JSON package an AI assistant returned', 'aria-label': 'Paste JSON package' } );
		var go1 = button( 'Check file (nothing is changed yet)', start, 'button-primary' );
		stage.appendChild( h( 'h2', { text: '1. Choose the edited file' } ) );
		stage.appendChild( h( 'p', { class: 'ncd-muted', text: 'Use a file exported from this data type (JSON, CSV or XLSX). Data Manager compares it with the site and shows every change before anything is written.' } ) );
		stage.appendChild( file );
		stage.appendChild( paste );
		stage.appendChild( h( 'h3', { text: 'Permissions for this import' } ) );
		stage.appendChild( h( 'p', { class: 'ncd-muted', text: 'Without these, records are only updated. Structural actions are listed in the preview either way.' } ) );
		stage.appendChild( toggle( 'Allow status changes', 'Publish, unpublish or schedule records when the file changes their status.', function ( v ) { opts.allow_status = v; }, false ) );
		if ( e.allow_create ) { stage.appendChild( toggle( 'Allow creating records', 'Rows without an ID (or with _action "create") become new records.', function ( v ) { opts.allow_create = v; }, false ) ); }
		if ( e.allow_trash ) { stage.appendChild( toggle( 'Allow moving records to the trash', 'Rows with _action "trash" are trashed (undoable).', function ( v ) { opts.allow_trash = v; }, false ) ); }
		stage.appendChild( go1 );
		root.appendChild( stage );
		var out = h( 'div' );
		root.appendChild( out );

		function start() {
			var req;
			if ( file.files && file.files[ 0 ] ) {
				var fd = new window.FormData(); fd.append( 'file', file.files[ 0 ] );
				req = api( '/import/' + base + '/stage', { method: 'POST', body: fd } );
			} else if ( paste.value.trim() ) {
				req = api( '/import/' + base + '/stage', { method: 'POST', data: { package: paste.value } } );
			} else { toast( 'Choose a file or paste a package first.', 'warning' ); return; }
			go1.disabled = true;
			clear( out ).appendChild( spinner( 'Reading file…' ) );
			req.then( function ( st ) { analyse( st ); } ).catch( function ( err ) { go1.disabled = false; clear( out ).appendChild( notice( errText( err ), 'error' ) ); } );
		}

		function analyse( st ) {
			var rows = [], offset = 0, total = st.count;
			var prog = h( 'progress', { max: total || 1, value: 0 } );
			clear( out ).appendChild( h( 'div', { class: 'ncd-card' }, h( 'p', { text: 'Comparing ' + total + ' record(s) with the site…' } ), prog ) );
			( st.warnings || [] ).forEach( function ( w ) { out.appendChild( notice( w, 'warning' ) ); } );
			function next() {
				if ( offset >= total ) { return previewUI( st, rows ); }
				api( '/import/' + base + '/preview', { method: 'POST', data: Object.assign( { token: st.token, offset: offset, limit: 200 }, opts ) } ).then( function ( r ) {
					rows = rows.concat( r.rows ); offset += 200; prog.value = Math.min( offset, total ); next();
				} ).catch( function ( err ) { clear( out ).appendChild( notice( errText( err ), 'error' ) ); go1.disabled = false; } );
			}
			next();
		}

		function previewUI( st, rows ) {
			go1.disabled = false;
			var sel = {}; // index -> false | true | [keys]
			var stats = { update: 0, create: 0, trash: 0, unchanged: 0, blocked: 0, errors: 0, conflicts: 0, status: 0 };
			rows.forEach( function ( r ) {
				if ( r.blocked ) { stats.blocked++; } else if ( r.action === 'unchanged' ) { stats.unchanged++; } else { stats[ r.action ] = ( stats[ r.action ] || 0 ) + 1; }
				stats.errors += r.errors; stats.conflicts += r.conflicts;
				r.fields.forEach( function ( f ) { if ( f.status === 'status' ) { stats.status++; } } );
				var applicable = ! r.blocked && r.action !== 'unchanged' && ( r.action === 'trash' || r.fields.some( function ( f ) { return f.status === 'change'; } ) );
				sel[ r.index ] = applicable;
			} );
			clear( out );
			var summary = h( 'div', { class: 'ncd-card' },
				h( 'h2', { text: '2. Review changes' } ),
				h( 'ul', { class: 'ncd-stats' },
					h( 'li', null, h( 'strong', { text: stats.update } ), ' to update' ),
					h( 'li', { class: stats.create ? 'is-warn' : '' }, h( 'strong', { text: stats.create } ), ' to create' ),
					h( 'li', { class: stats.trash ? 'is-warn' : '' }, h( 'strong', { text: stats.trash } ), ' to trash' ),
					h( 'li', { class: stats.status ? 'is-warn' : '' }, h( 'strong', { text: stats.status } ), ' status changes' ),
					h( 'li', { class: stats.conflicts ? 'is-warn' : '' }, h( 'strong', { text: stats.conflicts } ), ' conflicts' ),
					h( 'li', { class: stats.errors ? 'is-error' : '' }, h( 'strong', { text: stats.errors } ), ' invalid / protected' ),
					h( 'li', null, h( 'strong', { text: stats.unchanged } ), ' unchanged' )
				),
				stats.status && ! opts.allow_status ? notice( 'Status changes are listed but will NOT be applied: “Allow status changes” is off.', 'warning' ) : null,
				stats.create && ! opts.allow_create ? notice( 'New records are listed but will NOT be created: “Allow creating records” is off.', 'warning' ) : null,
				stats.trash && ! opts.allow_trash ? notice( 'Trash rows are listed but will NOT be applied: “Allow moving records to the trash” is off.', 'warning' ) : null,
				stats.conflicts ? notice( 'Conflicts are fields changed on the site after export. They are unticked; tick a field to overwrite the site value.', 'warning' ) : null
			);
			out.appendChild( summary );

			var onlyChanges = true, page = 0, PER = 50;
			var listBox = h( 'div' );
			var filterToggle = toggle( 'Show only records with changes or problems', '', function ( v ) { onlyChanges = v; page = 0; drawList(); }, true );
			out.appendChild( h( 'div', { class: 'ncd-card' }, filterToggle, listBox ) );

			function visible() { return rows.filter( function ( r ) { return ! onlyChanges || r.action !== 'unchanged' || r.blocked; } ); }
			function drawList() {
				clear( listBox );
				var vis = visible(), pages = Math.max( 1, Math.ceil( vis.length / PER ) );
				vis.slice( page * PER, page * PER + PER ).forEach( function ( r ) { listBox.appendChild( recordPlan( r ) ); } );
				if ( ! vis.length ) { listBox.appendChild( notice( 'Nothing to change: the file matches the site.', 'info' ) ); }
				if ( pages > 1 ) {
					listBox.appendChild( h( 'div', { class: 'ncd-pager' }, h( 'span', { text: 'Page ' + ( page + 1 ) + ' of ' + pages } ),
						button( '‹', function () { page--; drawList(); }, '', { disabled: ! page } ), button( '›', function () { page++; drawList(); }, '', { disabled: page >= pages - 1 } ) ) );
				}
			}
			function recordPlan( r ) {
				var cb = h( 'input', { type: 'checkbox', checked: !! sel[ r.index ], disabled: !! r.blocked || r.action === 'unchanged', 'aria-label': 'Apply record ' + ( r.index + 1 ) } );
				var fieldBoxes = {};
				function picked() {
					if ( ! cb.checked ) { return false; }
					var keys = Object.keys( fieldBoxes ).filter( function ( k ) { return fieldBoxes[ k ].checked; } );
					var defaults = r.fields.filter( function ( f ) { return f.status === 'change'; } ).map( function ( f ) { return f.key; } );
					if ( r.action === 'trash' ) { return true; }
					return keys.length === defaults.length && keys.every( function ( k ) { return defaults.indexOf( k ) !== -1; } ) ? true : keys;
				}
				cb.addEventListener( 'change', function () { sel[ r.index ] = picked(); } );
				var title = r.action === 'create' ? 'New record' : ( r.label || '#' + r.id );
				var badge = { update: 'Update', create: 'Create', trash: 'Trash', unchanged: 'Unchanged', skip: 'Skip' }[ r.action ] || r.action;
				var card = h( 'div', { class: 'ncd-plan' + ( r.blocked ? ' is-blocked' : '' ) },
					h( 'div', { class: 'ncd-plan__head' }, h( 'label', null, cb, ' ', h( 'strong', { text: title } ) ),
						h( 'span', { class: 'ncd-badge ncd-badge--' + r.action, text: badge } ),
						r.id ? h( 'span', { class: 'ncd-muted', text: '#' + r.id } ) : null,
						h( 'span', { class: 'ncd-muted', text: 'row ' + ( r.index + 1 ) } ) ),
					r.blocked ? notice( r.blocked, r.errors ? 'error' : 'warning' ) : null );
				if ( r.fields.length ) {
					var tbl = h( 'table', { class: 'ncd-diff' }, h( 'thead', null, h( 'tr', null, h( 'th', { text: '' } ), h( 'th', { text: 'Field' } ), h( 'th', { text: 'Now' } ), h( 'th', { text: 'After import' } ) ) ) );
					var tb = h( 'tbody' );
					r.fields.forEach( function ( f ) {
						var can = f.status === 'change' || f.status === 'conflict';
						var fcb = can ? h( 'input', { type: 'checkbox', checked: f.status === 'change', 'aria-label': 'Apply ' + f.label, onchange: function () { sel[ r.index ] = picked(); } } ) : null;
						if ( fcb ) { fieldBoxes[ f.key ] = fcb; }
						tb.appendChild( h( 'tr', { class: 'is-' + f.status },
							h( 'td', null, fcb ),
							h( 'td', { 'data-label': 'Field' }, h( 'strong', { text: f.label } ), f.message ? h( 'small', { class: 'ncd-plan__msg', text: f.message } ) : null ),
							h( 'td', { 'data-label': 'Now', class: 'ncd-before', text: f.before === '' ? '—' : f.before } ),
							h( 'td', { 'data-label': 'After', class: 'ncd-after', text: f.after === '' ? '—' : f.after } ) ) );
					} );
					tbl.appendChild( tb );
					card.appendChild( tbl );
				}
				return card;
			}
			drawList();

			var applyBtn = button( 'Apply selected changes', apply, 'button-primary' );
			out.appendChild( h( 'div', { class: 'ncd-savebar is-dirty' }, h( 'span', { text: '3. Only ticked records and fields are written. One History entry; undo restores everything.' } ),
				h( 'div', { class: 'ncd-savebar__btns' }, button( 'Discard', function () { api( '/import/' + base + '/discard', { method: 'POST', data: { token: st.token } } ).finally( function () { go( base ); } ); }, 'button-link' ), applyBtn ) ) );

			function apply() {
				var picks = Object.keys( sel ).filter( function ( i ) { return sel[ i ] !== false && ! ( Array.isArray( sel[ i ] ) && ! sel[ i ].length ); } );
				if ( ! picks.length ) { toast( 'Nothing is ticked.', 'warning' ); return; }
				confirmBox( 'Apply ' + picks.length + ' record(s)?', 'Ticked changes will be written to the site now. You can undo the whole import from History.', 'Apply now' ).then( function ( ok ) {
					if ( ! ok ) { return; }
					applyBtn.disabled = true;
					var results = [], hid = 0, i = 0, BATCH = 100;
					var prog = h( 'progress', { max: picks.length, value: 0 } );
					clear( out ).appendChild( h( 'div', { class: 'ncd-card' }, h( 'p', { text: 'Applying…' } ), prog ) );
					function next() {
						if ( i >= picks.length ) { return finish(); }
						var batch = {};
						picks.slice( i, i + BATCH ).forEach( function ( k ) { batch[ k ] = sel[ k ]; } );
						api( '/import/' + base + '/apply', { method: 'POST', data: Object.assign( { token: st.token, selection: batch, history_id: hid }, opts ) } ).then( function ( r ) {
							results = results.concat( r.results ); hid = r.history_id || hid; i += BATCH; prog.value = Math.min( i, picks.length ); next();
						} ).catch( function ( err ) { results.push( { index: -1, ok: false, message: errText( err ) + ' (stopped; earlier batches were applied)' } ); finish(); } );
					}
					function finish() {
						api( '/import/' + base + '/discard', { method: 'POST', data: { token: st.token } } ).catch( function () {} );
						var bad = results.filter( function ( x ) { return ! x.ok || ( x.errors && Object.keys( x.errors ).length ); } );
						var good = results.filter( function ( x ) { return x.ok; } ).length;
						clear( out ).appendChild( h( 'div', { class: 'ncd-card' },
							h( 'h2', { text: 'Import finished' } ),
							h( 'p', { text: good + ' record(s) applied, ' + bad.length + ' with problems.' } ),
							bad.length ? h( 'ul', { class: 'ncd-errors' }, bad.slice( 0, 50 ).map( function ( x ) {
								return h( 'li', null, 'Row ' + ( x.index + 1 ) + ( x.id ? ' (#' + x.id + ')' : '' ) + ': ' + ( x.message || Object.keys( x.errors || {} ).map( function ( k ) { return k + ': ' + x.errors[ k ]; } ).join( '; ' ) ) );
							} ) ) : null,
							h( 'div', { class: 'ncd-inline' },
								bad.length ? button( 'Download error report', function () {
									var lines = [ csvLine( [ 'row', 'id', 'problem' ] ) ];
									bad.forEach( function ( x ) { lines.push( csvLine( [ x.index + 1, x.id || '', x.message || Object.keys( x.errors || {} ).map( function ( k ) { return k + ': ' + x.errors[ k ]; } ).join( '; ' ) ] ) ); } );
									download( 'import-errors.csv', '﻿' + lines.join( '\n' ), 'text/csv' );
								} ) : null,
								hid ? button( 'Undo this import', function () { undoEntry( hid, function () { go( base ); } ); } ) : null,
								h( 'a', { class: 'button button-primary', href: '#/' + base, text: 'Back to ' + e.label } ) ) ) );
					}
					next();
				} );
			}
		}
	}

	/* ------------------------------------------------------------------ history */

	function undoEntry( id, after, force ) {
		confirmBox( 'Undo change #' + id + '?', 'Values are restored to what they were before. Fields that changed again since then are skipped and reported.', 'Undo' ).then( function ( ok ) {
			if ( ! ok ) { return; }
			api( '/history/' + id + '/undo', { method: 'POST', data: { force: !! force } } ).then( function ( r ) {
				if ( r.conflicts && r.conflicts.length ) {
					modal( 'Partly undone', [ h( 'p', { text: 'These fields changed after #' + id + ' and were left as they are:' } ),
						h( 'ul', null, r.conflicts.map( function ( c ) { return h( 'li', { text: '#' + c.id + ' · ' + c.field } ); } ) ) ],
					[ { label: 'Overwrite them too', cls: 'button-primary', run: function ( close ) { close(); api( '/history/' + id + '/undo', { method: 'POST', data: { force: true } } ).then( function () { toast( 'Undone.' ); after(); } ).catch( function ( err ) { toast( errText( err ), 'error' ); } ); } } ] );
				} else { toast( 'Undone (' + ( r.restored || [] ).length + ' record(s)).' ); }
				Object.keys( r.errors || {} ).forEach( function ( k ) { toast( '#' + k + ': ' + display( r.errors[ k ] ), 'error' ); } );
				after();
			} ).catch( function ( err ) { toast( errText( err ), 'error' ); } );
		} );
	}

	function viewHistory( id ) {
		root.appendChild( header( [ { label: 'All data', href: '#/' }, id ? { label: 'History', href: '#/history' } : { label: 'History' } ].concat( id ? [ { label: '#' + id } ] : [] ) ) );
		var box = h( 'div', null, spinner() );
		root.appendChild( box );
		if ( id ) {
			api( '/history/' + id ).then( function ( it ) {
				clear( box );
				box.appendChild( h( 'div', { class: 'ncd-card' },
					h( 'h2', { text: it.summary } ),
					h( 'p', { class: 'ncd-muted', text: it.created_at + ' UTC · ' + it.provider + '.' + it.entity + ' · ' + it.operation + ' · ' + it.status } ),
					it.status !== 'undone' && it.operation !== 'undo' ? button( 'Undo', function () { undoEntry( it.id, render ); }, 'button-primary' ) : null ) );
				it.records.slice( 0, 200 ).forEach( function ( r ) {
					var keys = Object.keys( Object.assign( {}, r.before || {}, r.after || {} ) );
					box.appendChild( h( 'div', { class: 'ncd-plan' },
						h( 'div', { class: 'ncd-plan__head' }, h( 'strong', { text: '#' + r.id } ), h( 'span', { class: 'ncd-badge ncd-badge--' + r.action, text: r.action } ) ),
						keys.length ? h( 'table', { class: 'ncd-diff' }, h( 'thead', null, h( 'tr', null, h( 'th', { text: 'Field' } ), h( 'th', { text: 'Before' } ), h( 'th', { text: 'After' } ) ) ),
							h( 'tbody', null, keys.map( function ( k ) { return h( 'tr', null, h( 'td', { 'data-label': 'Field', text: k } ), h( 'td', { 'data-label': 'Before', class: 'ncd-before', text: short( ( r.before || {} )[ k ] ) } ), h( 'td', { 'data-label': 'After', class: 'ncd-after', text: short( ( r.after || {} )[ k ] ) } ) ); } ) ) ) : null ) );
				} );
			} ).catch( function ( err ) { clear( box ).appendChild( notice( errText( err ), 'error' ) ); } );
			return;
		}
		api( '/history' + ( cfg.isAdmin ? '?all_users=1' : '' ) ).then( function ( rows ) {
			clear( box );
			if ( ! rows.length ) { box.appendChild( notice( 'No changes recorded yet.', 'info' ) ); return; }
			box.appendChild( h( 'table', { class: 'ncd-table' },
				h( 'thead', null, h( 'tr', null, [ 'Change', 'When (UTC)', 'Who', 'Records', '' ].map( function ( t ) { return h( 'th', { text: t } ); } ) ) ),
				h( 'tbody', null, rows.map( function ( r ) {
					return h( 'tr', null,
						h( 'td', { 'data-label': 'Change', class: 'ncd-col-title' }, h( 'a', { href: '#/history/' + r.id, text: r.summary } ), h( 'span', { class: 'ncd-row-meta' }, '#' + r.id + ' · ' + r.operation + ' · ' + r.source, r.status !== 'applied' ? h( 'span', { class: 'ncd-status', text: r.status } ) : null ) ),
						h( 'td', { 'data-label': 'When', text: r.created_at } ),
						h( 'td', { 'data-label': 'Who', text: r.user } ),
						h( 'td', { 'data-label': 'Records', text: r.record_count } ),
						h( 'td', null, r.status !== 'undone' && r.operation !== 'undo' ? button( 'Undo', function () { undoEntry( r.id, render ); } ) : null ) );
				} ) ) ) );
		} ).catch( function ( err ) { clear( box ).appendChild( notice( errText( err ), 'error' ) ); } );
	}

	render();
}() );
