( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, Placeholder } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const schemas = ( window.OZ_FORMS_SCHEMAS || [] ).map( ( s ) => ( {
		label: s.title,
		value: s.id,
	} ) );

	registerBlockType( 'oz/form', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			const options = [ { label: __( '— Kies een formulier —', 'oz-forms' ), value: '' } ].concat( schemas );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Form', 'oz-forms' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Form type', 'oz-forms' ),
							value: attributes.formId,
							options: options,
							onChange: ( v ) => setAttributes( { formId: v } ),
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( Placeholder, {
						icon: 'email-alt',
						label: __( 'OZ Form', 'oz-forms' ),
						instructions: attributes.formId
							? __( 'Form: ', 'oz-forms' ) + attributes.formId
							: __( 'Choose a form type in the sidebar.', 'oz-forms' ),
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
