/** Nine Code Data "Form" block: pick one of your 9 Data forms and place it in content. */
( function ( wp, window ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.components || ! wp.blockEditor ) { return; }
	var NAME = 'nine-code-data/form';
	if ( wp.blocks.getBlockType( NAME ) ) { return; }

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var forms = window.NineCodeDataForms || [];

	wp.blocks.registerBlockType( NAME, {
		apiVersion: 3,
		title: __( 'Form', 'nine-code-data' ),
		description: __( 'Show one of your Nine Code Data forms.', 'nine-code-data' ),
		category: 'widgets',
		icon: 'feedback',
		keywords: [ 'form', 'contact', 'submit' ],
		attributes: { formId: { type: 'number', default: 0 } },
		supports: { html: false, align: [ 'wide', 'full' ] },
		edit: function ( props ) {
			var id = props.attributes.formId || 0;
			var options = [ { value: 0, label: __( 'Contact form (default)', 'nine-code-data' ) } ].concat(
				forms.map( function ( f ) { return { value: f.id, label: f.title || ( '#' + f.id ) }; } )
			);
			var label = options.filter( function ( o ) { return o.value === id; } )[ 0 ];
			return el( 'div', Object.assign( {}, wp.blockEditor.useBlockProps( { className: 'nc-form-block' } ) ),
				el( 'strong', null, __( 'Form', 'nine-code-data' ) + ': ' + ( label ? label.label : '#' + id ) ),
				el( wp.components.SelectControl, {
					label: __( 'Choose a form', 'nine-code-data' ),
					value: id,
					options: options,
					onChange: function ( v ) { props.setAttributes( { formId: parseInt( v, 10 ) || 0 } ); },
					__nextHasNoMarginBottom: true
				} ),
				forms.length ? null : el( 'p', { className: 'nc-form-block__hint' }, __( 'No custom forms yet. Build one under Data → Form Manager. The default contact form is used until then.', 'nine-code-data' ) )
			);
		},
		save: function () { return null; }
	} );
}( window.wp, window ) );
