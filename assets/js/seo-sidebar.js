/* global wp, forwpSeoSidebar */

( function ( wp, settings ) {
	console.log( '[forwp-seo] seo-sidebar.js loaded', settings );
	if ( ! settings ) {
		return;
	}

	const el = wp.element.createElement;
	const { registerPlugin } = wp.plugins;
	const editorApi = wp.editor || wp.editPost;
	if ( ! editorApi ) {
		console.warn( '[forwp-seo] editor API missing' );
		return;
	}

	const { PluginSidebar, PluginSidebarMoreMenuItem } = editorApi;
	const { createBlock } = wp.blocks || {};
	const { useSelect } = wp.data;
	const { useState } = wp.element;
	const { PanelBody, Button, TextareaControl, Spinner, Notice } = wp.components;
	const { __ } = wp.i18n;
	const chartBar = wp.icons ? wp.icons.chartBar : null;
	const apiFetch = wp.apiFetch;

	const PLATFORMS = [
		{ id: 'devto', label: 'dev.to' },
		{ id: 'medium', label: 'Medium' },
		{ id: 'linkedin', label: 'LinkedIn' },
		{ id: 'x', label: 'X' },
		{ id: 'bsky', label: 'Bluesky' },
	];

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

	const buildLlmsPreview = ( title, url, summary, steps, codeSamples, tags ) => {
		const lines = [];
		lines.push( '# TechArticle: ' + title );
		lines.push( '' );
		lines.push( '## URL' );
		lines.push( url );
		lines.push( '' );
		lines.push( '## Description' );
		lines.push( summary || '' );
		lines.push( '' );
		lines.push( '## Steps' );
		if ( steps.length ) {
			steps.forEach( ( step, index ) => {
				lines.push( ( index + 1 ) + '. ' + step );
			} );
		}
		lines.push( '' );
		lines.push( '## Code Samples' );
		if ( codeSamples.length ) {
			codeSamples.forEach( ( code, index ) => {
				lines.push( index + 1 + '. ' + code );
			} );
		}
		lines.push( '' );
		if ( tags.length ) {
			lines.push( '## Tags' );
			tags.forEach( ( tag ) => {
				lines.push( '- ' + tag );
			} );
			lines.push( '' );
		}
		lines.push( '## Updated' );
		lines.push( new Date().toISOString().replace( 'T', ' ' ).replace( 'Z', ' UTC' ) );

		return lines.join( '\n' );
	};

	const SeoSidebar = () => {
		const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
		const post = useSelect( ( select ) => select( 'core/editor' ).getCurrentPost(), [] );
		const postTitle = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '',
			[]
		);
		const postExcerpt = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'excerpt' ) || '',
			[]
		);
		const postUrl = post?.link || '';
		const authorId = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'author' ),
			[]
		);
		const author = useSelect(
			( select ) =>
				authorId ? select( 'core' ).getEntityRecord( 'root', 'user', authorId ) : null,
			[ authorId ]
		);
		const tagIds = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'tags' ) || [],
			[]
		);
		const tags = useSelect(
			( select ) => {
				if ( ! tagIds.length ) {
					return [];
				}
				const records = select( 'core' ).getEntityRecords( 'taxonomy', 'post_tag', {
					include: tagIds,
					per_page: tagIds.length,
				} );
				if ( ! records ) {
					return [];
				}
				return records.map( ( record ) => record.name );
			},
			[ tagIds ]
		);

		const { hasCode, hasSteps } = useSelect( ( select ) => {
			const blocks = select( 'core/block-editor' ).getBlocks();
			const flat = flattenBlocks( blocks );
			let foundCode = false;
			let foundSteps = false;

			flat.forEach( ( block ) => {
				if ( block.name === 'core/code' ) {
					foundCode = true;
				}
				if ( block.name === 'forwp-seo/techarticle-steps' || block.name === 'forwp-seo/techarticle-step' ) {
					foundSteps = true;
				}
			} );

			return {
				hasCode: foundCode,
				hasSteps: foundSteps,
			};
		}, [] );

		const invalidBlocks = useSelect( ( select ) => {
			const blocks = select( 'core/block-editor' ).getBlocks();
			const flat = flattenBlocks( blocks );
			return flat.filter( ( block ) => {
				if ( block.isValid !== false ) {
					return false;
				}
				return block.name === 'core/comments' || block.name === 'core/separator';
			} );
		}, [] );

		const { steps, codeSamples, summary } = useSelect( ( select ) => {
			const blocks = select( 'core/block-editor' ).getBlocks();
			const flat = flattenBlocks( blocks );
			const extractedSteps = [];
			const extractedCodes = [];
			let extractedSummary = '';

			flat.forEach( ( block ) => {
				if ( block.name === 'core/paragraph' && ! extractedSummary ) {
					const text = block?.attributes?.content?.replace( /<[^>]*>/g, '' ) || '';
					if ( text.trim() ) {
						extractedSummary = text.trim();
					}
				}
				if ( block.name === 'core/code' ) {
					const code = block?.attributes?.content || '';
					if ( code.trim() ) {
						extractedCodes.push( code.trim() );
					}
				}
				if ( block.name === 'forwp-seo/techarticle-steps' && block?.attributes?.steps ) {
					const blockSteps = block?.attributes?.steps || [];
					blockSteps.forEach( ( step ) => {
						const text = step?.text?.replace( /<[^>]*>/g, '' ) || '';
						if ( text.trim() ) {
							extractedSteps.push( text.trim() );
						}
					} );
				}

				if ( block.name === 'forwp-seo/techarticle-step' ) {
					const parts = [];
					const walk = ( innerBlocks ) => {
						innerBlocks.forEach( ( inner ) => {
							const html = inner?.attributes?.content || inner?.innerHTML || '';
							const text = html.replace( /<[^>]*>/g, '' ).trim();
							if ( text ) {
								parts.push( text );
							}
							if ( inner.innerBlocks?.length ) {
								walk( inner.innerBlocks );
							}
						} );
					};
					walk( block.innerBlocks || [] );
					if ( parts.length ) {
						extractedSteps.push( parts.join( '\n' ) );
					}
				}
			} );

			if ( ! extractedSummary && postExcerpt ) {
				extractedSummary = postExcerpt.replace( /<[^>]*>/g, '' ).trim();
			}

			return {
				steps: extractedSteps,
				codeSamples: extractedCodes,
				summary: extractedSummary,
			};
		}, [ postExcerpt ] );

		const [ platform, setPlatform ] = useState( '' );
		const [ content, setContent ] = useState( '' );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( '' );

		const fetchContent = async ( nextPlatform ) => {
			if ( ! postId ) {
				setError( __( 'Save the post before generating content.', '4wp-seo' ) );
				return;
			}

			setLoading( true );
			setError( '' );
			setContent( '' );

			try {
				const response = await apiFetch( {
					url: settings.baseUrl + '/crosspost?post_id=' + postId + '&platform=' + nextPlatform,
					method: 'GET',
					headers: {
						'X-WP-Nonce': settings.nonce,
					},
				} );
				setContent( response.content || '' );
				setPlatform( nextPlatform );
			} catch ( err ) {
				setError( err?.message || __( 'Failed to generate content.', '4wp-seo' ) );
			} finally {
				setLoading( false );
			}
		};

		const copyToClipboard = async () => {
			if ( ! content ) {
				return;
			}
			try {
				await navigator.clipboard.writeText( content );
			} catch ( err ) {
				setError( __( 'Copy failed. Try manual copy.', '4wp-seo' ) );
			}
		};

		const isValid = hasCode && hasSteps;
		const jsonPreview = {
			'@context': 'https://schema.org',
			'@type': 'TechArticle',
			headline: postTitle || '...',
			author: author?.name
				? {
					'@type': 'Person',
					name: author.name,
				}
				: undefined,
			softwareCode: codeSamples.map( ( code ) => ( {
				'@type': 'SoftwareSourceCode',
				codeSampleType: 'full',
				programmingLanguage: 'auto',
				text: code,
			} ) ),
			hasPart: [
				{
					'@type': 'HowTo',
					step: steps.map( ( step ) => ( {
						'@type': 'HowToStep',
						text: step,
					} ) ),
				},
			],
			about: tags.map( ( tag ) => ( {
				'@type': 'Thing',
				name: tag,
			} ) ),
		};

		const llmsPreview = buildLlmsPreview(
			postTitle || '...',
			postUrl || '',
			summary || '',
			steps,
			codeSamples,
			tags
		);

		const repairInvalidBlocks = () => {
			if ( ! createBlock ) {
				console.warn( '[forwp-seo] wp.blocks.createBlock missing' );
				return;
			}
			const dispatch = wp.data.dispatch( 'core/block-editor' );
			if ( ! dispatch || ! dispatch.replaceBlock ) {
				console.warn( '[forwp-seo] block editor dispatch missing' );
				return;
			}
			invalidBlocks.forEach( ( block ) => {
				const next = createBlock( block.name, block.attributes || {} );
				dispatch.replaceBlock( block.clientId, next );
			} );
		};

		const statusNotice = isValid
			? el(
				Notice,
				{ status: 'success', isDismissible: false },
				__( 'TechArticle is ready. JSON-LD will be added to the page.', '4wp-seo' )
			)
			: el(
				Notice,
				{ status: 'warning', isDismissible: false },
				__( 'Missing required blocks. JSON-LD will not be added.', '4wp-seo' )
			);

		const statusList = el(
			'ul',
			{ style: { margin: 0, paddingLeft: '18px' } },
			el(
				'li',
				null,
				( hasCode ? '✅ ' : '⚠️ ' ) + __( 'Core Code block', '4wp-seo' )
			),
			el(
				'li',
				null,
				( hasSteps ? '✅ ' : '⚠️ ' ) + __( 'TechArticle Steps block', '4wp-seo' )
			)
		);

		const repairBlock = invalidBlocks.length
			? el(
				'div',
				{ style: { marginTop: '12px' } },
				el(
					Notice,
					{ status: 'warning', isDismissible: false },
					__( 'Invalid blocks detected. Click to repair.', '4wp-seo' )
				),
				el(
					Button,
					{ variant: 'secondary', onClick: repairInvalidBlocks },
					__( 'Repair invalid blocks', '4wp-seo' )
				)
			)
			: null;

		const jsonPreviewBlock = el(
			'pre',
			{
				style: {
					background: '#f0f0f1',
					padding: '10px',
					borderRadius: '4px',
					fontSize: '12px',
					overflow: 'auto',
					maxHeight: '240px',
				},
			},
			JSON.stringify( jsonPreview, null, 2 )
		);

		const validationButtons = el(
			'div',
			{ style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
			el(
				Button,
				{
					variant: 'secondary',
					href: postUrl
						? 'https://validator.schema.org/#url=' + encodeURIComponent( postUrl )
						: 'https://validator.schema.org/',
					target: '_blank',
					rel: 'noopener noreferrer',
				},
				__( 'Validate Schema.org', '4wp-seo' )
			),
			el(
				Button,
				{
					variant: 'secondary',
					href: postUrl
						? 'https://search.google.com/test/rich-results?url=' + encodeURIComponent( postUrl )
						: 'https://search.google.com/test/rich-results',
					target: '_blank',
					rel: 'noopener noreferrer',
				},
				__( 'Google Rich Results Test', '4wp-seo' )
			)
		);

		const llmsPreviewBlock = el(
			'pre',
			{
				style: {
					background: '#f0f0f1',
					padding: '10px',
					borderRadius: '4px',
					fontSize: '12px',
					overflow: 'auto',
					maxHeight: '240px',
				},
			},
			llmsPreview
		);

		const crosspostingList = el(
			'div',
			{ style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
			...PLATFORMS.map( ( item ) =>
				el(
					Button,
					{
						key: item.id,
						variant: platform === item.id ? 'primary' : 'secondary',
						onClick: () => fetchContent( item.id ),
					},
					item.label
				)
			)
		);

		const loadingBlock = loading
			? el( 'p', { style: { marginTop: '12px' } }, el( Spinner ) )
			: null;

		const errorBlock = error
			? el( 'p', { style: { color: '#b32d2e' } }, error )
			: null;

		const contentBlock = content
			? el(
				'div',
				{ style: { marginTop: '12px' } },
				el( TextareaControl, {
					label: __( 'Copy-ready content', '4wp-seo' ),
					value: content,
					readOnly: true,
					rows: 10,
				} ),
				el(
					Button,
					{ variant: 'primary', onClick: copyToClipboard },
					__( 'Copy', '4wp-seo' )
				)
			)
			: null;

		const crosspostingBody = settings.crosspostingEnabled
			? [ crosspostingList, loadingBlock, errorBlock, contentBlock ].filter( Boolean )
			: [
				el(
					'p',
					null,
					__( 'Cross posting module is disabled. Enable it in 4wp SEO settings.', '4wp-seo' )
				),
			];

		return el(
			wp.element.Fragment,
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: 'forwp-seo-sidebar', icon: chartBar || undefined },
				__( '4wp SEO', '4wp-seo' )
			),
			el(
				PluginSidebar,
				{ name: 'forwp-seo-sidebar', title: __( '4wp SEO', '4wp-seo' ), icon: chartBar || undefined },
				el(
					PanelBody,
					{ title: __( 'Schema.org (TechArticle)', '4wp-seo' ), initialOpen: true },
					statusNotice,
					statusList,
					repairBlock
				),
				el(
					PanelBody,
					{ title: __( 'JSON-LD Preview', '4wp-seo' ), initialOpen: false },
					jsonPreviewBlock
				),
				el(
					PanelBody,
					{ title: __( 'Validation Tools', '4wp-seo' ), initialOpen: false },
					validationButtons
				),
				el(
					PanelBody,
					{ title: __( 'LLMS.txt Preview', '4wp-seo' ), initialOpen: false },
					llmsPreviewBlock
				),
				el(
					PanelBody,
					{ title: __( 'Cross posting', '4wp-seo' ), initialOpen: true },
					...crosspostingBody
				)
			)
		);
	};

	if ( ! PluginSidebar || ! PluginSidebarMoreMenuItem ) {
		console.warn( '[forwp-seo] sidebar components missing' );
		return;
	}

	registerPlugin( 'forwp-seo-sidebar', {
		render: SeoSidebar,
	} );
	console.log( '[forwp-seo] seo-sidebar plugin registered' );
} )( window.wp, window.forwpSeoSidebar );

