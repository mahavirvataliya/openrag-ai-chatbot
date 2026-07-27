/**
 * WP OpenRag admin JS (jQuery-based; WP ships jQuery).
 */
( function ( $ ) {
	'use strict';

	var A = window.WPOpenRagAdmin || {};
	var REST = A.restUrl || '/wp-json/wporag/v1';
	var HEADERS = function () {
		return {
			'Content-Type': 'application/json',
			'X-WP-Nonce': A.nonce || '',
		};
	};

	/* -------- Provider fieldset visibility -------- */
	function showProviderFields( selectSel, wrapSel ) {
		var $sel = $( selectSel );
		if ( ! $sel.length ) { return; }
		function update() {
			var v = $sel.val();
			$( wrapSel + ' .wporag-provider-fieldset' ).removeClass( 'is-active' );
			$( wrapSel + ' .wporag-provider-fieldset[data-provider="' + v + '"]' ).addClass( 'is-active' );
		}
		$sel.on( 'change', update );
		update();
	}

	/* -------- Fetch models / test connection -------- */
	function restPost( path, body ) {
		return $.ajax( {
			url: REST + path,
			method: 'POST',
			headers: HEADERS(),
			data: JSON.stringify( body || {} ),
		} );
	}

	$( function () {
		showProviderFields( '#wporag-llm-provider', '.wporag-provider-fields' );
		showProviderFields( '#wporag-emb-provider', '.wporag-provider-fields' );

		/* Fetch models — collect the active provider's creds from the visible fieldset. */
		$( '#wporag-fetch-models' ).on( 'click', function () {
			var $btn = $( this );
			var provider = $( '#wporag-llm-provider' ).val();
			// For demo simplicity, call the server endpoint that resolves the active settings.
			$btn.prop( 'disabled', true ).text( A.i18n.fetching );
			$.ajax( {
				url: REST + '/admin/models?scope=llm',
				method: 'GET',
				headers: { 'X-WP-Nonce': A.nonce || '' },
			} ).done( function ( data ) {
				var $sel = $( '.wporag-provider-fieldset.is-active input[name$="[openai_model]"], .wporag-provider-fieldset.is-active input[name$="[groq_model]"], .wporag-provider-fieldset.is-active input[name$="[compatible_model]"], .wporag-provider-fieldset.is-active input[name$="[anthropic_model]"], .wporag-provider-fieldset.is-active input[name$="[ollama_model]"]' ).first();
				var models = ( data && data.models ) || [];
				if ( models.length && $sel.length ) {
					$sel.attr( 'list', 'wporag-model-list' );
					if ( ! $( '#wporag-model-list' ).length ) { $( 'body' ).append( '<datalist id="wporag-model-list"></datalist>' ); }
					var $dl = $( '#wporag-model-list' ).empty();
					models.forEach( function ( m ) { $dl.append( '<option value="' + m + '">' ); } );
					alert( 'Found ' + models.length + ' models. Start typing in the model field to pick one.' );
				} else {
					alert( 'No models returned or current provider not configured.' );
				}
			} ).fail( function ( xhr ) {
				alert( 'Fetch failed: ' + ( xhr.responseText || xhr.statusText ) );
			} ).always( function () {
				$btn.prop( 'disabled', false ).text( 'Fetch models' );
			} );
		} );

		$( '#wporag-test-connection' ).on( 'click', function () {
			var $btn = $( this );
			$btn.prop( 'disabled', true ).text( A.i18n.saving );
			restPost( '/admin/test', {} ).done( function ( r ) {
				alert( r.ok ? 'Connection OK: ' + ( r.model || '' ) : ( 'Failed: ' + ( r.error || 'unknown' ) ) );
			} ).fail( function ( xhr ) {
				alert( 'Test failed: ' + ( xhr.responseText || xhr.statusText ) );
			} ).always( function () {
				$btn.prop( 'disabled', false ).text( 'Test connection' );
			} );
		} );

		/* Create Vectorize index */
		$( '#wporag-create-index' ).on( 'click', function () {
			var $btn = $( this );
			$btn.prop( 'disabled', true );
			restPost( '/vector-store/create-index', {} ).done( function ( r ) {
				alert( r.ok ? 'Index ready.' : ( 'Failed: ' + ( r.error || 'unknown' ) ) );
			} ).fail( function ( xhr ) {
				alert( 'Failed: ' + ( xhr.responseText || xhr.statusText ) );
			} ).always( function () { $btn.prop( 'disabled', false ); } );
		} );

		/* KB: add document (file or URL) */
		$( '#wporag-add-doc' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var $f = $( this );
			var data = {
				type:      'pdf',
				title:     $f.find( '[name=title]' ).val(),
				source_url:$f.find( '[name=source_url]' ).val(),
				file_path: $f.find( '[name=file_path]' ).val(),
				mime_type: $f.find( '[name=mime_type]' ).val(),
				queue:     $f.find( '[name=queue]' ).is( ':checked' ),
			};
			// Determine type by mime if a file was uploaded.
			if ( data.file_path ) {
				data.source_url = '';
				data.type = ( data.mime_type.indexOf( 'pdf' ) >= 0 ) ? 'pdf' :
				            ( data.mime_type.indexOf( 'word' ) >= 0 || data.mime_type.indexOf( 'officedocument' ) >= 0 ) ? 'docx' : 'txt';
			} else if ( data.source_url ) {
				var ext = data.source_url.split('.').pop().toLowerCase();
				data.type = ( { pdf: 'pdf', docx: 'docx', doc: 'docx', txt: 'txt', md: 'txt', markdown: 'txt' } )[ ext ] || 'url';
				if ( 'url' === data.type ) { data.mime_type = 'text/html'; }
			}
			restPost( '/documents', data ).done( function () {
				alert( A.i18n.indexing || 'Queued' );
				location.reload();
			} ).fail( function ( xhr ) { alert( 'Failed: ' + ( xhr.responseText || xhr.statusText ) ); } );
		} );

		/* KB: add URLs */
		$( '#wporag-add-urls' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var raw = $( this ).find( '[name=urls]' ).val().split( /\r?\n/ ).map( function ( l ) { return l.trim(); } ).filter( Boolean );
			var queue = $( this ).find( '[name=queue]' ).is( ':checked' );
			var chain = $.when();
			raw.forEach( function ( line ) {
				chain = chain.then( function () {
					var title = '', url = line;
					if ( line.indexOf( ',' ) >= 0 ) { var p = line.split( ',' ); title = p[0]; url = p[1] || p[0]; }
					return restPost( '/documents', { type: 'url', title: title, source_url: url, mime_type: 'text/html', queue: queue } );
				} );
			} );
			chain.done( function () { alert( A.i18n.indexing || 'Queued' ); location.reload(); } )
			     .fail( function ( xhr ) { alert( 'Failed: ' + ( xhr.responseText || xhr.statusText ) ); } );
		} );

		/* KB: reindex */
		$( document ).on( 'click', '.wporag-reindex', function () {
			var id = $( this ).data( 'id' );
			restPost( '/documents/' + id + '/process', {} ).done( function () { location.reload(); } );
		} );

		/* KB: delete */
		$( document ).on( 'click', '.wporag-delete', function () {
			if ( ! confirm( A.i18n.delete ) ) { return; }
			var id = $( this ).data( 'id' );
			$.ajax( { url: REST + '/documents/' + id, method: 'DELETE', headers: HEADERS() } )
				.done( function () { location.reload(); } );
		} );

		/* KB: view chunks */
		$( document ).on( 'click', '.wporag-view-chunks', function () {
			var id = $( this ).data( 'id' );
			$.getJSON( REST + '/documents/' + id, { _wpnonce: A.nonce } ).done( function ( data ) {
				var html = '<h3>' + ( data.document.title || 'Document' ) + '</h3>';
				( data.chunks || [] ).forEach( function ( c ) {
					html += '<div style="margin-bottom:10px;padding:8px;border:1px solid #e2e8f0;border-radius:6px;">';
					html += '<div style="font-size:11px;color:#64748b;margin-bottom:4px;">Chunk #' + c.chunk_index + ' · ' + c.token_count + ' tokens</div>';
					html += '<div style="white-space:pre-wrap;">' + $( '<div>' ).text( c.content ).html() + '</div>';
					html += '</div>';
				} );
				$( '#wporag-chat-detail' ).html( html );
				$( '#wporag-chat-modal' ).show();
			} );
		} );

		/* KB: index WP content now */
		$( '#wporag-index-now' ).on( 'click', function () {
			restPost( '/posts/index', {} ).done( function ( r ) {
				$( '#wporag-index-now-msg' ).text( 'Queued ' + ( r.queued || 0 ) + ' posts.' );
			} );
		} );

		/* Media picker for logo / avatar */
		$( '.wporag-media' ).on( 'click', function ( e ) {
			e.preventDefault();
			var target = $( this ).data( 'target' );
			var frame = wp.media( { title: 'Select image', multiple: false, library: { type: 'image' }, button: { text: 'Use' } } );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				$( '#' + target ).val( att.url );
			} );
			frame.open();
		} );

		/* Media picker for KB upload */
		var mediaFrame;
		$( '#wporag-upload-btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			mediaFrame = wp.media( { title: 'Select document', multiple: false, button: { text: 'Use' } } );
			mediaFrame.on( 'select', function () {
				var att = mediaFrame.state().get( 'selection' ).first().toJSON();
				$( '#wporag-file-path' ).val( att.url );
				$( '#wporag-file-mime' ).val( att.mime );
				$( '#wporag-file-name' ).text( att.filename );
			} );
			mediaFrame.open();
		} );

		/* Appearance: apply preset */
		$( '#wporag-apply-preset' ).on( 'click', function () {
			var preset = $( '#wporag-theme-preset' ).val();
			var data = ( window.wporagThemePresets || {} )[ preset ];
			if ( ! data || ! data.colors ) { return; }
			Object.keys( data.colors ).forEach( function ( k ) {
				$( '.wporag-color-picker[data-key="' + k + '"]' ).val( data.colors[ k ] );
			} );
		} );

		/* MCP: add server */
		$( '#wporag-mcp-add' ).on( 'click', function () {
			restPost( '/mcp/servers', {
				name:      $( '#wporag-mcp-name' ).val(),
				url:       $( '#wporag-mcp-url' ).val(),
				transport: $( '#wporag-mcp-transport' ).val(),
				auth_header:$( '#wporag-mcp-auth' ).val(),
				enabled:   true,
			} ).done( function () { location.reload(); } )
			   .fail( function ( xhr ) { alert( 'Failed: ' + ( xhr.responseText || xhr.statusText ) ); } );
		} );

		$( document ).on( 'click', '.wporag-mcp-discover', function () {
			var id = $( this ).data( 'id' );
			var $b = $( this ).prop( 'disabled', true ).text( A.i18n.discovering );
			restPost( '/mcp/servers/' + id + '/discover', {} )
				.done( function ( r ) { alert( 'Discovered ' + ( r.tools || 0 ) + ' tools' + ( r.error ? ' (error: ' + r.error + ')' : '' ) ); location.reload(); } )
				.fail( function ( x ) { alert( 'Failed: ' + ( x.responseText || x.statusText ) ); } )
				.always( function () { $b.prop( 'disabled', false ).text( 'Discover' ); } );
		} );

		$( document ).on( 'click', '.wporag-mcp-delete', function () {
			if ( ! confirm( A.i18n.delete ) ) { return; }
			var id = $( this ).data( 'id' );
			$.ajax( { url: REST + '/mcp/servers/' + id, method: 'DELETE', headers: HEADERS() } )
				.done( function () { location.reload(); } );
		} );

		$( document ).on( 'click', '.wporag-mcp-toggle', function () {
			var id = $( this ).data( 'id' );
			restPost( '/mcp/servers/' + id, { enabled: false } ).done( function () { location.reload(); } );
		} );

		/* Chats: view detail */
		$( document ).on( 'click', '.wporag-view-chat', function () {
			var id = $( this ).data( 'id' );
			$.getJSON( REST + '/history?limit=200' ).done( function ( data ) {
				var turns = ( data.turns || [] );
				// Find the user turn with this id and surrounding assistant reply.
				var idx = -1;
				for ( var i = 0; i < turns.length; i++ ) { if ( parseInt( turns[i].id, 10 ) === id ) { idx = i; break; } }
				var html = '';
				if ( idx >= 0 ) {
					for ( var j = Math.max(0, idx - 1); j <= Math.min( turns.length - 1, idx + 1 ); j++ ) {
						var t = turns[j];
						html += '<div class="wporag-chat-msg wporag-chat-msg-' + t.role + '">';
						html += '<strong>' + t.role.toUpperCase() + '</strong><div style="white-space:pre-wrap;margin-top:4px;">' + $( '<div>' ).text( t.content ).html() + '</div>';
						if ( t.reasoning ) {
							html += '<details class="wporag-chat-reasoning"><summary>Reasoning</summary>' + $( '<div>' ).text( t.reasoning ).html() + '</details>';
						}
						if ( t.citations && t.citations.length ) {
							html += '<div class="wporag-chat-citations"><strong>Sources:</strong> ';
							t.citations.forEach( function ( c ) {
								html += '<a href="' + c.url + '" target="_blank">' + c.title + '</a>';
							} );
							html += '</div>';
						}
						html += '<div class="wporag-chat-msg-meta">' + t.createdAt + ( t.model ? ' · ' + t.model : '' ) + ( t.feedback ? ' · ' + t.feedback : '' ) + '</div>';
						html += '</div>';
					}
				}
				$( '#wporag-chat-detail' ).html( html || 'No data.' );
				$( '#wporag-chat-modal' ).show();
			} );
		} );

		/* Modal close */
		$( document ).on( 'click', '.wporag-modal-close', function () { $( this ).closest( '.wporag-modal' ).hide(); } );
		$( document ).on( 'click', '.wporag-modal', function ( e ) { if ( e.target === this ) { $( this ).hide(); } } );
	} );
} )( jQuery );
