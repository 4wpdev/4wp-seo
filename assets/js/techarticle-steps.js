/* global wp */

( function ( wp ) {
	console.log( '[forwp-seo] techarticle-steps.js loaded' );
	const el = wp.element.createElement;
	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;

	const STEP_TEMPLATE = [
		[ 'core/heading', { level: 3, placeholder: __( 'Step title', '4wp-seo' ) } ],
		[ 'core/paragraph', { placeholder: __( 'Short description', '4wp-seo' ) } ],
		[ 'core/code', { placeholder: __( 'Command', '4wp-seo' ) } ],
		[ 'core/paragraph', { placeholder: __( 'Explanation', '4wp-seo' ) } ],
	];

	const STEPS_TEMPLATE = [
		[ 'forwp-seo/techarticle-step', {}, STEP_TEMPLATE ],
	];

	registerBlockType( 'forwp-seo/techarticle-steps', {
		title: __( 'TechArticle Steps', '4wp-seo' ),
		icon: 'editor-ol',
		category: 'widgets',
		edit: ( { attributes, setAttributes } ) => {
			const blockProps = useBlockProps();
			return el( InnerBlocks, {
				...blockProps,
				allowedBlocks: [ 'forwp-seo/techarticle-step' ],
				template: STEPS_TEMPLATE,
			} );
		},
		save: () => null,
	} );

	registerBlockType( 'forwp-seo/techarticle-step', {
		title: __( 'TechArticle Step', '4wp-seo' ),
		icon: 'editor-ol',
		category: 'widgets',
		parent: [ 'forwp-seo/techarticle-steps' ],
		edit: () => {
			const blockProps = useBlockProps();
			return el( 'div', blockProps, [
				el( InnerBlocks, {
					allowedBlocks: [ 'core/heading', 'core/paragraph', 'core/code', 'core/list' ],
					template: STEP_TEMPLATE,
				} ),
			] );
		},
		save: () => null,
	} );
	console.log( '[forwp-seo] techarticle steps block registered' );
} )( window.wp );


