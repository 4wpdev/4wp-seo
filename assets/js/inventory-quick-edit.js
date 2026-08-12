( function () {
	'use strict';

	var cfg = window.forwpSeoInventoryQuickEdit;
	if ( ! cfg ) {
		return;
	}

	var activeRow = null;
	var activeInline = null;

	function escHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text == null ? '' : String( text );
		return div.innerHTML;
	}

	function excerpt( text, max ) {
		var value = text == null ? '' : String( text );
		if ( ! value ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' + escHtml( cfg.i18n.empty ) + '</span>';
		}
		if ( value.length <= max ) {
			return escHtml( value );
		}
		return escHtml( value.slice( 0, max ) ) + '…';
	}

	function scoreClass( score ) {
		if ( score >= 75 ) {
			return 'good';
		}
		if ( score >= 50 ) {
			return 'medium';
		}
		return 'low';
	}

	function ogImageCell( url ) {
		if ( ! url ) {
			return '<span class="forwp-seo-og-thumb forwp-seo-og-thumb--empty" aria-hidden="true">—</span>' +
				'<span class="screen-reader-text">' + escHtml( cfg.i18n.noOgImage ) + '</span>';
		}

		return '<a href="' + escHtml( url ) + '" class="forwp-seo-og-thumb" target="_blank" rel="noopener noreferrer">' +
			'<img src="' + escHtml( url ) + '" alt="" width="48" height="48" loading="lazy" decoding="async" />' +
			'<span class="screen-reader-text">' + escHtml( cfg.i18n.viewOgImage ) + '</span>' +
			'</a>';
	}

	function getEventElement( event ) {
		var node = event.target;

		while ( node && node.nodeType !== 1 ) {
			node = node.parentElement;
		}

		return node;
	}

	function closeInlineEdit() {
		if ( activeInline ) {
			var quickEditBtn = activeRow && activeRow.querySelector( '.forwp-seo-quick-edit-trigger' );
			if ( quickEditBtn ) {
				quickEditBtn.setAttribute( 'aria-expanded', 'false' );
			}
			activeInline.remove();
			activeInline = null;
		}
		if ( activeRow ) {
			activeRow.classList.remove( 'forwp-seo-row-editing' );
			activeRow = null;
		}
	}

	function setOgImagePreview( preview, url ) {
		if ( ! preview ) {
			return;
		}

		var placeholder = preview.querySelector( '.forwp-seo-og-image-preview__placeholder' );
		var img = preview.querySelector( 'img' );

		if ( url ) {
			if ( ! img ) {
				img = document.createElement( 'img' );
				img.alt = '';
				preview.appendChild( img );
			}
			img.src = url;
			preview.classList.add( 'has-image' );
			if ( placeholder ) {
				placeholder.style.display = 'none';
			}
			return;
		}

		if ( img ) {
			img.remove();
		}
		preview.classList.remove( 'has-image' );
		if ( placeholder ) {
			placeholder.style.display = '';
		}
	}

	function initOgImagePicker( inlineRow, item ) {
		var urlInput = inlineRow.querySelector( '.forwp-seo-field-og-image' );
		var preview = inlineRow.querySelector( '.forwp-seo-og-image-preview' );
		var selectBtn = inlineRow.querySelector( '.forwp-seo-og-image-select' );
		var removeBtn = inlineRow.querySelector( '.forwp-seo-og-image-remove' );

		if ( ! urlInput || ! selectBtn || ! removeBtn ) {
			return;
		}

		urlInput.value = item.og_image || '';
		setOgImagePreview( preview, urlInput.value );
		removeBtn.hidden = ! urlInput.value;

		selectBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}

			var frame = wp.media( {
				title: cfg.i18n.selectOgImage,
				button: { text: cfg.i18n.useImage },
				multiple: false,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				urlInput.value = attachment.url || '';
				setOgImagePreview( preview, urlInput.value );
				removeBtn.hidden = ! urlInput.value;
			} );

			frame.open();
		} );

		removeBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			urlInput.value = '';
			setOgImagePreview( preview, '' );
			removeBtn.hidden = true;
		} );
	}

	function buildInlineRow( item, colspan ) {
		var tr = document.createElement( 'tr' );
		tr.className = 'inline-edit-row forwp-seo-inline-edit-row';
		tr.innerHTML =
			'<td colspan="' + colspan + '" class="colspanchange">' +
				'<div class="forwp-seo-quick-edit-panel">' +
					'<div class="forwp-seo-quick-edit-panel__header">' +
						'<span class="forwp-seo-quick-edit-panel__legend">' + escHtml( cfg.i18n.quickEdit ) + '</span>' +
					'</div>' +
					'<div class="forwp-seo-quick-edit-panel__layout">' +
						'<div class="forwp-seo-quick-edit-panel__main">' +
							'<div class="forwp-seo-quick-edit-panel__main-grid">' +
								'<label class="forwp-seo-quick-edit-panel__field">' +
									'<span class="forwp-seo-quick-edit-panel__label">' + escHtml( cfg.i18n.seoTitle ) + '</span>' +
									'<input type="text" class="forwp-seo-field-seo-title" value="" autocomplete="off" />' +
								'</label>' +
								'<label class="forwp-seo-quick-edit-panel__field">' +
									'<span class="forwp-seo-quick-edit-panel__label">' + escHtml( cfg.i18n.focusKw ) + '</span>' +
									'<input type="text" class="forwp-seo-field-focus-keyword" value="" autocomplete="off" />' +
								'</label>' +
								'<label class="forwp-seo-quick-edit-panel__field forwp-seo-quick-edit-panel__field--wide">' +
									'<span class="forwp-seo-quick-edit-panel__label">' + escHtml( cfg.i18n.metaDesc ) + '</span>' +
									'<textarea class="forwp-seo-field-meta-description" rows="4"></textarea>' +
								'</label>' +
							'</div>' +
						'</div>' +
						'<div class="forwp-seo-quick-edit-panel__aside">' +
							'<div class="forwp-seo-quick-edit-panel__field forwp-seo-quick-edit-panel__field--og">' +
								'<span class="forwp-seo-quick-edit-panel__label">' + escHtml( cfg.i18n.ogImage ) + '</span>' +
								'<div class="forwp-seo-og-image-control">' +
									'<div class="forwp-seo-og-image-preview" role="img">' +
										'<span class="forwp-seo-og-image-preview__placeholder">' + escHtml( cfg.i18n.noImage ) + '</span>' +
									'</div>' +
									'<div class="forwp-seo-og-image-actions">' +
										'<input type="hidden" class="forwp-seo-field-og-image" value="" />' +
										'<button type="button" class="button forwp-seo-og-image-select">' + escHtml( cfg.i18n.selectOgImage ) + '</button>' +
										'<button type="button" class="button-link forwp-seo-og-image-remove">' + escHtml( cfg.i18n.removeImage ) + '</button>' +
									'</div>' +
								'</div>' +
							'</div>' +
						'</div>' +
					'</div>' +
					'<div class="forwp-seo-quick-edit-panel__actions">' +
						'<button type="button" class="button button-primary forwp-seo-inline-save">' + escHtml( cfg.i18n.save ) + '</button>' +
						'<button type="button" class="button forwp-seo-inline-cancel">' + escHtml( cfg.i18n.cancel ) + '</button>' +
						'<span class="spinner"></span>' +
						'<span class="forwp-seo-inline-notice" aria-live="polite"></span>' +
					'</div>' +
				'</div>' +
			'</td>';

		tr.querySelector( '.forwp-seo-field-seo-title' ).value = item.seo_title || '';
		tr.querySelector( '.forwp-seo-field-meta-description' ).value = item.meta_description || '';
		tr.querySelector( '.forwp-seo-field-focus-keyword' ).value = item.focus_keyword || '';
		initOgImagePicker( tr, item );

		return tr;
	}

	function updateRowCells( row, item ) {
		var seoTitle = row.querySelector( '.column-seo_title' );
		var metaDesc = row.querySelector( '.column-meta_description' );
		var focusKw = row.querySelector( '.column-focus_keyword' );
		var ogImage = row.querySelector( '.column-og_image' );
		var score = row.querySelector( '.column-completeness' );
		var missing = row.querySelector( '.column-missing' );

		if ( seoTitle ) {
			seoTitle.innerHTML = excerpt( item.seo_title, 80 );
		}
		if ( metaDesc ) {
			metaDesc.innerHTML = excerpt( item.meta_description, 80 );
		}
		if ( focusKw ) {
			focusKw.innerHTML = excerpt( item.focus_keyword, 80 );
		}
		if ( ogImage ) {
			ogImage.innerHTML = ogImageCell( item.og_image || '' );
		}
		if ( score ) {
			var value = parseInt( item.completeness, 10 ) || 0;
			score.innerHTML = '<span class="forwp-seo-score forwp-seo-score--' + scoreClass( value ) + '">' + value + '%</span>';
		}
		if ( missing ) {
			var list = Array.isArray( item.missing ) ? item.missing : [];
			missing.textContent = list.join( ', ' );
		}

		row.setAttribute( 'data-item', JSON.stringify( {
			post_id: item.post_id,
			seo_title: item.seo_title || '',
			meta_description: item.meta_description || '',
			focus_keyword: item.focus_keyword || '',
			og_image: item.og_image || '',
		} ) );
	}

	function saveInlineEdit( row, inlineRow ) {
		var postId = row.getAttribute( 'data-post-id' );
		var spinner = inlineRow.querySelector( '.spinner' );
		var notice = inlineRow.querySelector( '.forwp-seo-inline-notice' );
		var saveBtn = inlineRow.querySelector( '.forwp-seo-inline-save' );

		var payload = {
			seo_title: inlineRow.querySelector( '.forwp-seo-field-seo-title' ).value,
			meta_description: inlineRow.querySelector( '.forwp-seo-field-meta-description' ).value,
			focus_keyword: inlineRow.querySelector( '.forwp-seo-field-focus-keyword' ).value,
			og_image: inlineRow.querySelector( '.forwp-seo-field-og-image' ).value,
		};

		spinner.classList.add( 'is-active' );
		saveBtn.disabled = true;
		notice.textContent = cfg.i18n.saving;
		notice.className = 'forwp-seo-inline-notice';

		fetch( cfg.restUrl + postId, {
			method: 'PATCH',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( payload ),
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				spinner.classList.remove( 'is-active' );
				saveBtn.disabled = false;

				if ( ! result.ok || ! result.data || ! result.data.item ) {
					var message = cfg.i18n.error;
					if ( result.data && result.data.message ) {
						message = result.data.message;
					}
					notice.textContent = message;
					notice.className = 'forwp-seo-inline-notice forwp-seo-inline-notice--error';
					return;
				}

				updateRowCells( row, result.data.item );
				notice.textContent = cfg.i18n.saved;
				notice.className = 'forwp-seo-inline-notice forwp-seo-inline-notice--success';

				window.setTimeout( closeInlineEdit, 600 );
			} )
			.catch( function () {
				spinner.classList.remove( 'is-active' );
				saveBtn.disabled = false;
				notice.textContent = cfg.i18n.error;
				notice.className = 'forwp-seo-inline-notice forwp-seo-inline-notice--error';
			} );
	}

	function openInlineEdit( row ) {
		closeInlineEdit();

		var item;
		try {
			item = JSON.parse( row.getAttribute( 'data-item' ) || '{}' );
		} catch ( e ) {
			item = {};
		}

		item.post_id = row.getAttribute( 'data-post-id' );

		var colspan = parseInt( cfg.colspan, 10 ) || row.children.length;
		var inlineRow = buildInlineRow( item, colspan );

		row.classList.add( 'forwp-seo-row-editing' );
		row.after( inlineRow );

		var quickEditBtn = row.querySelector( '.forwp-seo-quick-edit-trigger' );
		if ( quickEditBtn ) {
			quickEditBtn.setAttribute( 'aria-expanded', 'true' );
		}

		activeRow = row;
		activeInline = inlineRow;

		inlineRow.querySelector( '.forwp-seo-inline-save' ).addEventListener( 'click', function () {
			saveInlineEdit( row, inlineRow );
		} );
		inlineRow.querySelector( '.forwp-seo-inline-cancel' ).addEventListener( 'click', closeInlineEdit );

		var firstInput = inlineRow.querySelector( '.forwp-seo-field-seo-title' );
		if ( firstInput ) {
			firstInput.focus();
		}
	}

	function bindInventoryEvents() {
		var list = document.getElementById( 'the-list' );
		if ( ! list ) {
			return;
		}

		list.addEventListener( 'click', function ( event ) {
			var target = getEventElement( event );
			if ( ! target ) {
				return;
			}

			var quickEdit = target.closest( '.forwp-seo-quick-edit-trigger' );
			if ( quickEdit ) {
				event.preventDefault();
				event.stopPropagation();
				var row = quickEdit.closest( 'tr.forwp-seo-inventory-row' );
				if ( row ) {
					openInlineEdit( row );
				}
				return;
			}

			if ( target.closest( '.forwp-seo-inline-cancel' ) ) {
				event.preventDefault();
				closeInlineEdit();
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bindInventoryEvents );
	} else {
		bindInventoryEvents();
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && activeInline ) {
			closeInlineEdit();
		}
	} );
}() );
