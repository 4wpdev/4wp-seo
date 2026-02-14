/* global wp */

( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect } = wp.data;
	const { __ } = wp.i18n;
	const { Fragment } = wp.element;

	const flattenBlocks = ( blocks ) => {
		let flat = [];
		blocks.forEach( ( block ) => {
			flat.push( block );
			if ( Array.isArray( block.innerBlocks ) && block.innerBlocks.length ) {
				flat = flat.concat( flattenBlocks( block.innerBlocks ) );
			}
		} );
		return flat;
	};

	const StatusPanel = () => {
		const { hasCode, hasSteps } = useSelect( ( select ) => {
			const blocks = select( 'core/block-editor' ).getBlocks();
			const flat = flattenBlocks( blocks );
			let foundCode = false;
			let foundSteps = false;

			flat.forEach( ( block ) => {
				if ( block.name === 'core/code' ) {
					foundCode = true;
				}
				if ( block.name === 'forwp-seo/techarticle-steps' ) {
					foundSteps = true;
				}
			} );

			return {
				hasCode: foundCode,
				hasSteps: foundSteps,
			};
		}, [] );

		return (
			<PluginDocumentSettingPanel
				name="forwp-seo-status"
				title={ __( '4wp SEO Status', '4wp-seo' ) }
				className="forwp-seo-status-panel"
			>
				<ul style={ { margin: 0, paddingLeft: '18px' } }>
					<li>
						{ hasCode ? '✅ ' : '⚠️ ' }
						{ __( 'Core Code block', '4wp-seo' ) }
					</li>
					<li>
						{ hasSteps ? '✅ ' : '⚠️ ' }
						{ __( 'TechArticle Steps block', '4wp-seo' ) }
					</li>
				</ul>
			</PluginDocumentSettingPanel>
		);
	};

	registerPlugin( 'forwp-seo-status-panel', {
		render: () => (
			<Fragment>
				<StatusPanel />
			</Fragment>
		),
	} );
} )( window.wp );


