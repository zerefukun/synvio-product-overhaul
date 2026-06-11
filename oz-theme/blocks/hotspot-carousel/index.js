/**
 * Editor for the oz/hotspot-carousel block.
 *
 * Plain JS using wp.element.createElement (no JSX, no extra build step).
 * Patrick edits projects + hotspots via repeater UI in the block sidebar.
 *
 * Each project: title, image (MediaUpload), hotspots[].
 * Each hotspot: x/y in percent, label, product URL (or product ID).
 *
 * Click on the preview image to add a hotspot at that position.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useState, useRef } = wp.element;
	const {
		InspectorControls,
		MediaUpload,
		MediaUploadCheck,
		useBlockProps,
	} = wp.blockEditor;
	const {
		PanelBody,
		Button,
		TextControl,
		__experimentalNumberControl,
		Card,
		CardBody,
		CardHeader,
		CardDivider,
	} = wp.components;
	const NumberControl = wp.components.__experimentalNumberControl || TextControl;
	const { __ } = wp.i18n;

	registerBlockType( 'oz/hotspot-carousel', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { eyebrow, title, projects } = attributes;
			const blockProps = useBlockProps( { className: 'oz-hotspot-carousel-edit' } );
			const [ activeProject, setActiveProject ] = useState( 0 );

			const updateProject = ( index, patch ) => {
				const next = projects.slice();
				next[ index ] = Object.assign( {}, next[ index ], patch );
				setAttributes( { projects: next } );
			};
			const removeProject = ( index ) => {
				const next = projects.slice();
				next.splice( index, 1 );
				setAttributes( { projects: next } );
				if ( activeProject >= next.length ) setActiveProject( Math.max( 0, next.length - 1 ) );
			};
			const addProject = () => {
				const next = projects.concat( [ {
					title: '',
					imageId: 0,
					imageUrl: '',
					hotspots: [],
				} ] );
				setAttributes( { projects: next } );
				setActiveProject( next.length - 1 );
			};

			const updateHotspot = ( pIdx, hIdx, patch ) => {
				const proj = Object.assign( {}, projects[ pIdx ] );
				proj.hotspots = proj.hotspots.slice();
				proj.hotspots[ hIdx ] = Object.assign( {}, proj.hotspots[ hIdx ], patch );
				updateProject( pIdx, { hotspots: proj.hotspots } );
			};
			const removeHotspot = ( pIdx, hIdx ) => {
				const hs = projects[ pIdx ].hotspots.slice();
				hs.splice( hIdx, 1 );
				updateProject( pIdx, { hotspots: hs } );
			};
			const addHotspotAt = ( pIdx, x, y ) => {
				const hs = ( projects[ pIdx ].hotspots || [] ).concat( [ {
					x: x, y: y, label: '', productUrl: '', productId: 0,
				} ] );
				updateProject( pIdx, { hotspots: hs } );
			};

			const onClickImage = ( pIdx, ev ) => {
				const rect = ev.currentTarget.getBoundingClientRect();
				const x = ( ( ev.clientX - rect.left ) / rect.width ) * 100;
				const y = ( ( ev.clientY - rect.top ) / rect.height ) * 100;
				addHotspotAt( pIdx, Math.round( x * 10 ) / 10, Math.round( y * 10 ) / 10 );
			};

			// ─── INSPECTOR (sidebar) ───────────────────────────────────────
			const sidebar = el( InspectorControls, {},
				el( PanelBody, { title: __( 'Header', 'oz-theme' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Eyebrow', 'oz-theme' ),
						value: eyebrow,
						onChange: ( v ) => setAttributes( { eyebrow: v } ),
					} ),
					el( TextControl, {
						label: __( 'Title', 'oz-theme' ),
						value: title,
						onChange: ( v ) => setAttributes( { title: v } ),
					} )
				),
				projects[ activeProject ] && el( PanelBody, {
					title: __( 'Hotspots — project ', 'oz-theme' ) + ( activeProject + 1 ),
					initialOpen: true,
				},
					el( 'p', { style: { fontSize: 12, color: '#666' } },
						__( 'Click anywhere on the image to add a hotspot. Then fill in the label + product URL below.', 'oz-theme' )
					),
					( projects[ activeProject ].hotspots || [] ).map( ( hs, hIdx ) =>
						el( Card, { key: hIdx, size: 'small', style: { marginBottom: 8 } },
							el( CardBody, { size: 'small' },
								el( 'div', { style: { display: 'flex', gap: 6, marginBottom: 6, fontSize: 12, color: '#666' } },
									'Hotspot ' + ( hIdx + 1 ) + ' — ' + Math.round( hs.x ) + '%, ' + Math.round( hs.y ) + '%'
								),
								el( TextControl, {
									label: __( 'Label', 'oz-theme' ),
									value: hs.label || '',
									onChange: ( v ) => updateHotspot( activeProject, hIdx, { label: v } ),
								} ),
								el( TextControl, {
									label: __( 'Product URL', 'oz-theme' ),
									help: __( 'Full URL of the product page', 'oz-theme' ),
									value: hs.productUrl || '',
									onChange: ( v ) => updateHotspot( activeProject, hIdx, { productUrl: v } ),
								} ),
								el( Button, {
									isDestructive: true,
									isSmall: true,
									onClick: () => removeHotspot( activeProject, hIdx ),
								}, __( 'Remove hotspot', 'oz-theme' ) )
							)
						)
					)
				)
			);

			// ─── BLOCK PREVIEW ─────────────────────────────────────────────
			const projectTabs = el( 'div', { className: 'oz-hsc-edit__tabs' },
				projects.map( ( p, i ) =>
					el( 'button', {
						key: i,
						type: 'button',
						className: 'oz-hsc-edit__tab' + ( i === activeProject ? ' is-active' : '' ),
						onClick: () => setActiveProject( i ),
					}, ( p.title || ( __( 'Project ', 'oz-theme' ) + ( i + 1 ) ) ) )
				),
				el( 'button', {
					type: 'button',
					className: 'oz-hsc-edit__tab oz-hsc-edit__tab--add',
					onClick: addProject,
				}, '+ ' + __( 'Add project', 'oz-theme' ) )
			);

			const activeP = projects[ activeProject ];
			const projectEditor = activeP ? el( 'div', { className: 'oz-hsc-edit__project' },
				el( 'div', { className: 'oz-hsc-edit__project-meta' },
					el( TextControl, {
						label: __( 'Project title (caption)', 'oz-theme' ),
						value: activeP.title || '',
						onChange: ( v ) => updateProject( activeProject, { title: v } ),
					} ),
					el( MediaUploadCheck, {},
						el( MediaUpload, {
							onSelect: ( media ) => updateProject( activeProject, { imageId: media.id, imageUrl: media.url } ),
							allowedTypes: [ 'image' ],
							value: activeP.imageId,
							render: ( { open } ) => el( Button, {
								onClick: open,
								variant: 'secondary',
							}, activeP.imageUrl ? __( 'Replace image', 'oz-theme' ) : __( 'Select image', 'oz-theme' ) )
						} )
					),
					el( Button, {
						isDestructive: true,
						isSmall: true,
						onClick: () => removeProject( activeProject ),
					}, __( 'Delete project', 'oz-theme' ) )
				),
				activeP.imageUrl ? el( 'div', {
					className: 'oz-hsc-edit__image-wrap',
					onClick: ( ev ) => onClickImage( activeProject, ev ),
				},
					el( 'img', { src: activeP.imageUrl, className: 'oz-hsc-edit__image' } ),
					( activeP.hotspots || [] ).map( ( hs, hIdx ) =>
						el( 'span', {
							key: hIdx,
							className: 'oz-hsc-edit__hotspot',
							style: { left: hs.x + '%', top: hs.y + '%' },
							title: hs.label || ( 'Hotspot ' + ( hIdx + 1 ) ),
						}, hIdx + 1 )
					)
				) : el( 'p', { style: { padding: 24, color: '#666', textAlign: 'center' } },
					__( 'Select an image to start placing hotspots.', 'oz-theme' )
				)
			) : el( 'div', { style: { padding: 32, textAlign: 'center' } },
				el( 'p', {}, __( 'No projects yet. Add the first one to get started.', 'oz-theme' ) ),
				el( Button, { variant: 'primary', onClick: addProject }, __( 'Add project', 'oz-theme' ) )
			);

			return el( 'div', blockProps,
				sidebar,
				el( 'div', { className: 'oz-hsc-edit__header' },
					el( 'strong', {}, eyebrow + ' — ' + title )
				),
				projectTabs,
				projectEditor
			);
		},

		// Server-rendered: save returns null and the PHP render_callback emits HTML.
		save: function () { return null; },
	} );
}( window.wp ) );
