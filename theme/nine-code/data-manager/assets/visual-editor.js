/**
 * Nine Code Data - visual (WYSIWYG) content editor.
 *
 * A deliberately small Gutenberg block editor used by the Post Editor's "Post Content" tab.
 * It edits real block markup (so nothing is lost for the front end or for the native editor),
 * limits the inserter to basic blocks, and writes the serialized result back into the existing
 * #npm9-content textarea, which stays the single source of truth for the Post Editor's save flow.
 *
 * No build step: plain wp.element.createElement against WordPress's bundled editor packages.
 */
( function ( wp, window, document ) {
	'use strict';

	var needed = wp && wp.element && wp.blockEditor && wp.blocks && wp.blockLibrary && wp.components && wp.i18n;
	if ( ! needed ) { return; }

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor;
	var c = wp.components;
	var __ = wp.i18n.__;
	var cfg = window.NineCodeVisualEditorConfig || {};

	/** The "basic, not complicated" set shown in the Add block popup. */
	var ALLOWED = [
		'core/paragraph', 'core/heading', 'core/image', 'core/gallery', 'core/list', 'core/list-item',
		'core/quote', 'core/buttons', 'core/button', 'core/separator', 'core/spacer', 'core/table',
		'core/video', 'core/audio', 'core/file', 'core/columns', 'core/column', 'core/group',
		'core/shortcode', 'core/html', 'nine-code-data/form'
	];

	function ensureCoreBlocks() {
		if ( ! wp.blocks.getBlockType( 'core/paragraph' ) ) { wp.blockLibrary.registerCoreBlocks(); }
	}

	function autop( html ) { return wp.autop && wp.autop.autop ? wp.autop.autop( html ) : html; }

	/** Raw/classic HTML -> blocks (never creates a Classic/TinyMCE block, which is not loaded here). */
	function rawToBlocks( html ) {
		var out = wp.blocks.rawHandler ? wp.blocks.rawHandler( { HTML: autop( html ) } ) : [];
		return out && out.length ? out : [];
	}

	function toBlocks( content ) {
		content = String( content || '' );
		if ( ! content.trim() ) { return []; }
		if ( ! /<!--\s*wp:/.test( content ) ) { return rawToBlocks( content ); }
		var parsed = wp.blocks.parse( content );
		var out = [];
		parsed.forEach( function ( block ) {
			if ( 'core/freeform' === block.name ) {
				var inner = rawToBlocks( ( block.attributes && block.attributes.content ) || block.originalContent || '' );
				out.push.apply( out, inner );
			} else {
				out.push( block );
			}
		} );
		return out;
	}

	function Editor( props ) {
		var textarea = props.textarea;
		var blocksState = useState( function () { return toBlocks( textarea.value ); } );
		var blocks = blocksState[0];
		var setBlocks = blocksState[1];
		var modeState = useState( 'visual' );
		var mode = modeState[0];
		var setMode = modeState[1];

		// Opening a post must never mark it changed: the editor re-serializes block markup in
		// its own canonical form, so only write once the content really differs from that.
		var baseline = useMemo( function () { return blocks.length ? wp.blocks.serialize( blocks ) : ''; }, [] );
		var touched = wp.element.useRef( false );

		function writeBack( next ) {
			var html = next.length ? wp.blocks.serialize( next ) : '';
			if ( html === textarea.value ) { return; }
			if ( ! touched.current && html === baseline ) { return; }
			touched.current = true;
			textarea.value = html;
			textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}

		function onInput( next ) { setBlocks( next ); writeBack( next ); }

		var settings = useMemo( function () {
			var allowed = ALLOWED.filter( function ( name ) { return !! wp.blocks.getBlockType( name ); } );
			return {
				hasFixedToolbar: true,
				allowedBlockTypes: allowed,
				mediaUpload: wp.editor && wp.editor.mediaUpload ? wp.editor.mediaUpload : undefined,
				imageSizes: cfg.imageSizes || [],
				maxWidth: cfg.maxWidth || 1200,
				isRTL: !! cfg.isRTL,
				bodyPlaceholder: __( 'Start writing, or press + to add an image, form or other block', 'nine-code-data' ),
				supportsLayout: false,
				__experimentalFeatures: {}
			};
		}, [] );

		function switchMode( next ) {
			if ( next === mode ) { return; }
			if ( 'code' === next ) {
				// Make sure the textarea reflects the current blocks, then reveal it.
				writeBack( blocks );
			} else {
				// Back to visual: re-read whatever was typed in the code box.
				setBlocks( toBlocks( textarea.value ) );
			}
			setMode( next );
		}

		useEffect( function () {
			var field = textarea.closest( '.npm9-code-field' );
			if ( field ) { field.hidden = 'code' !== mode; }
			props.host.classList.toggle( 'is-code-mode', 'code' === mode );
		}, [ mode ] );

		var modeButtons = el( 'div', { className: 'nc-ve__modes', role: 'group', 'aria-label': __( 'Editor mode', 'nine-code-data' ) },
			el( c.Button, { variant: 'visual' === mode ? 'primary' : 'secondary', size: 'compact', onClick: function () { switchMode( 'visual' ); }, 'aria-pressed': 'visual' === mode }, __( 'Visual', 'nine-code-data' ) ),
			el( c.Button, { variant: 'code' === mode ? 'primary' : 'secondary', size: 'compact', onClick: function () { switchMode( 'code' ); }, 'aria-pressed': 'code' === mode }, __( 'Code', 'nine-code-data' ) )
		);

		return el( c.SlotFillProvider, null,
			el( be.BlockEditorProvider, { value: blocks, onInput: onInput, onChange: onInput, settings: settings },
				el( 'div', { className: 'nc-ve__bar' },
					'visual' === mode ? el( be.Inserter, {
						renderToggle: function ( t ) {
							return el( c.Button, { variant: 'primary', icon: 'plus-alt2', onClick: t.onToggle, 'aria-expanded': t.isOpen, className: 'nc-ve__add' }, __( 'Add block', 'nine-code-data' ) );
						}
					} ) : null,
					modeButtons
				),
				'visual' === mode ? el( 'div', { className: 'nc-ve__body' },
					el( be.BlockTools, null,
						el( 'div', { className: 'editor-styles-wrapper nc-ve__canvas' },
							el( be.WritingFlow, null, el( be.ObserveTyping, null, el( be.BlockList, null ) ) )
						)
					),
					el( 'details', { className: 'nc-ve__settings' },
						el( 'summary', null, __( 'Block settings', 'nine-code-data' ) ),
						el( be.BlockInspector, null )
					)
				) : null,
				el( c.Popover.Slot, null )
			)
		);
	}

	var current = null;

	window.NineCodeVisualEditor = {
		/** Mount into `host`, bound to `textarea`. Returns false if the editor could not start. */
		mount: function ( host, textarea ) {
			try {
				ensureCoreBlocks();
				this.unmount();
				var root = wp.element.createRoot ? wp.element.createRoot( host ) : null;
				if ( ! root ) { return false; }
				root.render( el( Editor, { host: host, textarea: textarea } ) );
				current = { root: root, host: host };
				host.classList.add( 'is-ready' );
				return true;
			} catch ( e ) {
				if ( window.console ) { window.console.error( 'Nine Code visual editor failed to start; using the code editor.', e ); }
				var field = textarea.closest( '.npm9-code-field' );
				if ( field ) { field.hidden = false; }
				return false;
			}
		},
		unmount: function () {
			if ( current ) {
				try { current.root.unmount(); } catch ( e ) { /* host already detached */ }
				current = null;
			}
		}
	};
}( window.wp, window, document ) );
