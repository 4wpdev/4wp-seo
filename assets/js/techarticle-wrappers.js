/* global wp */

( function ( wp ) {
	const el = wp.element.createElement;
	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;

	/**
	 * Schema.org TechArticle section wrapper — accepts any inner blocks (4wp-faq pattern).
	 *
	 * @param {string} name      Block name.
	 * @param {string} title     Block title.
	 * @param {string} className Wrapper class on the front end.
	 * @param {string} icon      Dashicon slug.
	 */
	function registerWrapper( name, title, className, icon, options = {} ) {
		const { tagName = 'section', attributes = {} } = options;

		registerBlockType( name, {
			title,
			icon,
			category: 'forwp-seo-techarticle',
			description: __(
				'TechArticle section wrapper. Add any blocks inside; used for Schema.org markup.',
				'4wp-seo'
			),
			attributes,
			supports: {
				html: false,
				className: true,
			},
			edit: () => {
				const blockProps = useBlockProps( { className } );
				return el(
					tagName,
					blockProps,
					el( InnerBlocks, {
						templateLock: false,
					} )
				);
			},
			save: () => el( InnerBlocks.Content ),
		} );
	}

	if ( wp.blocks && wp.blocks.registerBlockCategory ) {
		wp.blocks.registerBlockCategory( 'forwp-seo-techarticle', {
			title: __( 'TechArticle', '4wp-seo' ),
		} );
	} else if ( wp.blocks && wp.blocks.setCategories ) {
		const cats = wp.blocks.getCategories ? wp.blocks.getCategories() : [];
		if ( ! cats.some( ( c ) => c.slug === 'forwp-seo-techarticle' ) ) {
			wp.blocks.setCategories( [
				...cats,
				{ slug: 'forwp-seo-techarticle', title: __( 'TechArticle', '4wp-seo' ) },
			] );
		}
	}

	registerWrapper(
		'forwp-seo/techarticle-goal',
		__( 'TechArticle Goal', '4wp-seo' ),
		'forwp-seo-techarticle-goal',
		'flag'
	);

	registerWrapper(
		'forwp-seo/techarticle-context',
		__( 'TechArticle Context', '4wp-seo' ),
		'forwp-seo-techarticle-context',
		'text-page'
	);

	registerWrapper(
		'forwp-seo/techarticle-issues',
		__( 'TechArticle Common Mistakes', '4wp-seo' ),
		'forwp-seo-techarticle-issues',
		'warning'
	);

	registerWrapper(
		'forwp-seo/techarticle-steps',
		__( 'TechArticle Steps', '4wp-seo' ),
		'forwp-seo-techarticle-steps',
		'editor-ol'
	);

	registerWrapper(
		'forwp-seo/techarticle-step',
		__( 'TechArticle Step', '4wp-seo' ),
		'forwp-seo-techarticle-step',
		'marker',
		{
			tagName: 'div',
			attributes: {
				stepNumber: {
					type: 'number',
				},
			},
		}
	);
} )( window.wp );
