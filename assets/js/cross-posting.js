/* global wp, forwpSeoCrossPosting */

( function ( wp, settings ) {
	if ( ! settings ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect } = wp.data;
	const { useState } = wp.element;
	const { Button, TextareaControl, Spinner } = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const PLATFORMS = [
		{ id: 'devto', label: 'dev.to' },
		{ id: 'medium', label: 'Medium' },
		{ id: 'linkedin', label: 'LinkedIn' },
		{ id: 'x', label: 'X' },
		{ id: 'bsky', label: 'Bluesky' },
	];

	const CrossPostingPanel = () => {
		const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
		const [ platform, setPlatform ] = useState( '' );
		const [ content, setContent ] = useState( '' );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( '' );

		if ( settings.enabled === false ) {
			return (
				<PluginDocumentSettingPanel
				name="forwp-seo-crossposting"
					title={ __( 'Cross posting', '4wp-seo' ) }
				>
					<p>{ __( 'Cross posting module is disabled. Enable it in 4wp SEO settings.', '4wp-seo' ) }</p>
				</PluginDocumentSettingPanel>
			);
		}

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

		return (
			<PluginDocumentSettingPanel
				name="forwp-seo-crossposting"
				title={ __( 'Cross posting', '4wp-seo' ) }
				name="forwp-seo-crossposting"
				title={ __( 'Cross posting', '4wp-seo' ) }
			>
				<div style={ { display: 'flex', flexDirection: 'column', gap: '8px' } }>
					{ PLATFORMS.map( ( item ) => (
						<Button
							key={ item.id }
							variant={ platform === item.id ? 'primary' : 'secondary' }
							onClick={ () => fetchContent( item.id ) }
						>
							{ item.label }
						</Button>
					) ) }
				</div>

				{ loading && (
					<p style={ { marginTop: '12px' } }>
						<Spinner />
					</p>
				) }

				{ error && <p style={ { color: '#b32d2e' } }>{ error }</p> }

				{ content && (
					<div style={ { marginTop: '12px' } }>
						<TextareaControl
							label={ __( 'Copy-ready content', '4wp-seo' ) }
							value={ content }
							onChange={ () => {} }
							rows={ 10 }
						/>
						<Button variant="primary" onClick={ copyToClipboard }>
							{ __( 'Copy', '4wp-seo' ) }
						</Button>
					</div>
				) }
			</PluginDocumentSettingPanel>
		);
	};

	registerPlugin( 'forwp-seo-crossposting', {
		render: CrossPostingPanel,
	} );
} )( window.wp, window.forwpSeoCrossPosting );

