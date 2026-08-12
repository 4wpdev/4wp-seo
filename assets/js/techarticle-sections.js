/* global wp */

( function ( wp ) {
	const el = wp.element.createElement;
	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;

	function registerSectionBlock( name, title, className, template ) {
		registerBlockType( name, {
			title,
			icon: 'text-page',
			category: 'widgets',
			edit: () => {
				const blockProps = useBlockProps( { className } );
				return el(
					'section',
					blockProps,
					el( InnerBlocks, {
						template,
						templateLock: false,
					} )
				);
			},
			save: () => null,
		} );
	}

	registerSectionBlock(
		'forwp-seo/techarticle-goal',
		__( 'TechArticle Goal', '4wp-seo' ),
		'forwp-seo-techarticle-goal',
		[
			[
				'core/heading',
				{
					level: 2,
					content: __( 'Goal', '4wp-seo' ),
				},
			],
			[
				'core/paragraph',
				{
					placeholder: __(
						'What should the learner accomplish in this scenario?',
						'4wp-seo'
					),
				},
			],
		]
	);

	registerSectionBlock(
		'forwp-seo/techarticle-context',
		__( 'TechArticle Context', '4wp-seo' ),
		'forwp-seo-techarticle-context',
		[
			[
				'core/heading',
				{
					level: 2,
					content: __( 'Context', '4wp-seo' ),
				},
			],
			[
				'core/paragraph',
				{
					placeholder: __(
						'When and why this workflow matters.',
						'4wp-seo'
					),
				},
			],
		]
	);

	registerSectionBlock(
		'forwp-seo/techarticle-issues',
		__( 'TechArticle Common Mistakes', '4wp-seo' ),
		'forwp-seo-techarticle-issues',
		[
			[
				'core/heading',
				{
					level: 2,
					content: __( 'Common mistakes', '4wp-seo' ),
				},
			],
			[
				'core/list',
				{
					placeholder: __( 'Typical errors and how to avoid them.', '4wp-seo' ),
				},
			],
		]
	);
} )( window.wp );
