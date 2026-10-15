/**
 * Editor placeholder for the server-rendered catering form (no build step).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var Placeholder = wp.components.Placeholder;

	wp.blocks.registerBlockType( 'twisted-oak/catering-form', {
		edit: function () {
			return el(
				'div',
				useBlockProps(),
				el(
					Placeholder,
					{
						icon: 'food',
						label: __( 'Catering Inquiry Form', 'twisted-oak-catering' ),
						instructions: __(
							'The multi-step catering form renders here on the site. Packages, recipient email and messages are set under Settings → Catering.',
							'twisted-oak-catering'
						),
					}
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
