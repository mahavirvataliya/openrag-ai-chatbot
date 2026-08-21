/**
 * ItihRag AI Chatbot admin JS (jQuery-based; WP ships jQuery).
 */
( function ( $ ) {
	'use strict';

	var A = window.ItihRagAdmin || {};
	var REST = A.restUrl || '/wp-json/itih/v1';
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
			$( wrapSel + ' .openrag-provider-fieldset' ).removeClass( 'is-active' );
			$( wrapSel + ' .openrag-provider-fieldset[data-provider="' + v + '"]' ).addClass( 'is-active' );
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
		showProviderFields( '#openrag-llm-provider', '.openrag-provider-fields' );
		showProviderFields( '#openrag-emb-provider', '.openrag-provider-fields' );

		/* Fetch models — collect the active provider's creds from the visible fieldset. */
		$( '#openrag-fetch-models' ).on( 'click', function () {
			var $btn = $( this );
			var provider = $( '#openrag-llm-provider' ).val();
			// For demo simplicity, call the server endpoint that resolves the active settings.
			$btn.prop( 'disabled', true ).text( A.i18n.fetching );
			$.ajax( {
				url: REST + '/admin/models?scope=llm',
				method: 'GET',
				headers: { 'X-WP-Nonce': A.nonce || '' },
			} ).done( function ( data ) {
				var $sel = $( '.openrag-provider-fieldset.is-active input[name$="[openai_model]"], .openrag-provider-fieldset.is-active input[name$="[groq_model]"], .openrag-provider-fieldset.is-active input[name$="[compatible_model]"], .openrag-provider-fieldset.is-active input[name$="[anthropic_model]"], .openrag-provider-fieldset.is-active input[name$="[ollama_model]"]' ).first();
				var models = ( data && data.models ) || [];
				if ( models.length && $sel.length ) {
					$sel.attr( 'list', 'openrag-model-list' );
					if ( ! $( '#openrag-model-list' ).length ) { $( 'body' ).append( '<datalist id="openrag-model-list"></datalist>' ); }
					var $dl = $( '#openrag-model-list' ).empty();
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

		$( '#openrag-test-connection' ).on( 'click', function () {
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
		$( '#openrag-create-index' ).on( 'click', function () {
			var $btn = $( this );
			$btn.prop( 'disabled', true );
			restPost( '/vector-store/create-index', {} ).done( function ( r ) {
				alert( r.ok ? 'Index ready.' : ( 'Failed: ' + ( r.error || 'unknown' ) ) );
			} ).fail( function ( xhr ) {
				alert( 'Failed: ' + ( xhr.responseText || xhr.statusText ) );
			} ).always( function () { $btn.prop( 'disabled', false ); } );
		} );

		/* KB: add document (file or URL) */
		$( '#openrag-add-doc' ).on( 'submit', function ( e ) {
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
		$( '#openrag-add-urls' ).on( 'submit', function ( e ) {
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
		$( document ).on( 'click', '.openrag-reindex', function () {
			var id = $( this ).data( 'id' );
			restPost( '/documents/' + id + '/process', {} ).done( function () { location.reload(); } );
		} );

		/* KB: delete */
		$( document ).on( 'click', '.openrag-delete', function () {
			if ( ! confirm( A.i18n.delete ) ) { return; }
			var id = $( this ).data( 'id' );
			$.ajax( { url: REST + '/documents/' + id, method: 'DELETE', headers: HEADERS() } )
				.done( function () { location.reload(); } );
		} );

		/* KB: view chunks */
		$( document ).on( 'click', '.openrag-view-chunks', function () {
			var id = $( this ).data( 'id' );
			$.getJSON( REST + '/documents/' + id, { _wpnonce: A.nonce } ).done( function ( data ) {
				var html = '<h3>' + ( data.document.title || 'Document' ) + '</h3>';
				( data.chunks || [] ).forEach( function ( c ) {
					html += '<div style="margin-bottom:10px;padding:8px;border:1px solid #e2e8f0;border-radius:6px;">';
					html += '<div style="font-size:11px;color:#64748b;margin-bottom:4px;">Chunk #' + c.chunk_index + ' · ' + c.token_count + ' tokens</div>';
					html += '<div style="white-space:pre-wrap;">' + $( '<div>' ).text( c.content ).html() + '</div>';
					html += '</div>';
				} );
				$( '#openrag-chat-detail' ).html( html );
				$( '#openrag-chat-modal' ).show();
			} );
		} );

		/* KB: index WP content now */
		$( '#openrag-index-now' ).on( 'click', function () {
			restPost( '/posts/index', {} ).done( function ( r ) {
				$( '#openrag-index-now-msg' ).text( ( A.i18n.queuedPosts || 'Queued %d posts.' ).replace( '%d', r.queued || 0 ) );
			} );
		} );

		/* Media picker for logo / avatar */
		$( '.openrag-media' ).on( 'click', function ( e ) {
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
		$( '#openrag-upload-btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			mediaFrame = wp.media( { title: 'Select document', multiple: false, button: { text: 'Use' } } );
			mediaFrame.on( 'select', function () {
				var att = mediaFrame.state().get( 'selection' ).first().toJSON();
				$( '#openrag-file-path' ).val( att.url );
				$( '#openrag-file-mime' ).val( att.mime );
				$( '#openrag-file-name' ).text( att.filename );
			} );
			mediaFrame.open();
		} );

		/* Appearance: apply preset */
		$( '#openrag-apply-preset' ).on( 'click', function () {
			var preset = $( '#openrag-theme-preset' ).val();
			var data = ( A.themePresets || {} )[ preset ];
			if ( ! data || ! data.colors ) { return; }
			Object.keys( data.colors ).forEach( function ( k ) {
				$( '.openrag-color-picker[data-key="' + k + '"]' ).val( data.colors[ k ] );
			} );
		} );

		/* MCP: add server */
		$( '#openrag-mcp-add' ).on( 'click', function () {
			restPost( '/mcp/servers', {
				name:      $( '#openrag-mcp-name' ).val(),
				url:       $( '#openrag-mcp-url' ).val(),
				transport: $( '#openrag-mcp-transport' ).val(),
				auth_header:$( '#openrag-mcp-auth' ).val(),
				enabled:   true,
			} ).done( function () { location.reload(); } )
			   .fail( function ( xhr ) { alert( 'Failed: ' + ( xhr.responseText || xhr.statusText ) ); } );
		} );

		$( document ).on( 'click', '.openrag-mcp-discover', function () {
			var id = $( this ).data( 'id' );
			var $b = $( this ).prop( 'disabled', true ).text( A.i18n.discovering );
			restPost( '/mcp/servers/' + id + '/discover', {} )
				.done( function ( r ) { alert( 'Discovered ' + ( r.tools || 0 ) + ' tools' + ( r.error ? ' (error: ' + r.error + ')' : '' ) ); location.reload(); } )
				.fail( function ( x ) { alert( 'Failed: ' + ( x.responseText || x.statusText ) ); } )
				.always( function () { $b.prop( 'disabled', false ).text( 'Discover' ); } );
		} );

		$( document ).on( 'click', '.openrag-mcp-delete', function () {
			if ( ! confirm( A.i18n.delete ) ) { return; }
			var id = $( this ).data( 'id' );
			$.ajax( { url: REST + '/mcp/servers/' + id, method: 'DELETE', headers: HEADERS() } )
				.done( function () { location.reload(); } );
		} );

		$( document ).on( 'click', '.openrag-mcp-toggle', function () {
			var id = $( this ).data( 'id' );
			restPost( '/mcp/servers/' + id, { enabled: false } ).done( function () { location.reload(); } );
		} );

		/* Chats: view detail — rendered from row data attributes (no extra REST call) */
		function esc( s ) { return $( '<div>' ).text( s == null ? '' : String( s ) ).html(); }
		$( document ).on( 'click', '.openrag-view-chat', function () {
			var $b = $( this );
			var citations = [];
			try { citations = JSON.parse( $b.attr( 'data-citations' ) || '[]' ) || []; } catch ( e ) { citations = []; }
			var html = '';
			html += '<div class="openrag-chat-msg openrag-chat-msg-user">';
			html += '<strong>USER</strong><div style="white-space:pre-wrap;margin-top:4px;">' + esc( $b.attr( 'data-content' ) ) + '</div>';
			html += '<div class="openrag-chat-msg-meta">' + esc( $b.attr( 'data-created-at' ) ) + '</div>';
			html += '</div>';
			if ( $b.attr( 'data-reply' ) ) {
				html += '<div class="openrag-chat-msg openrag-chat-msg-assistant">';
				html += '<strong>ASSISTANT</strong><div style="white-space:pre-wrap;margin-top:4px;">' + esc( $b.attr( 'data-reply' ) ) + '</div>';
				if ( $b.attr( 'data-reasoning' ) ) {
					html += '<details class="openrag-chat-reasoning"><summary>' + esc( A.i18n.reasoning || 'Reasoning' ) + '</summary>' + esc( $b.attr( 'data-reasoning' ) ) + '</details>';
				}
				if ( citations.length ) {
					html += '<div class="openrag-chat-citations"><strong>' + esc( A.i18n.sources || 'Sources:' ) + '</strong> ';
					citations.forEach( function ( c ) {
						html += '<a href="' + esc( c.url ) + '" target="_blank" rel="noopener">' + esc( c.title ) + '</a> ';
					} );
					html += '</div>';
				}
				var metaParts = [];
				if ( $b.attr( 'data-model' ) ) { metaParts.push( esc( $b.attr( 'data-model' ) ) ); }
				var tot = parseInt( $b.attr( 'data-tokens' ), 10 ) || 0;
				if ( tot > 0 ) { metaParts.push( ( A.i18n.tokens || '%d tokens' ).replace( '%d', tot ) ); }
				var ms = parseInt( $b.attr( 'data-response-ms' ), 10 ) || 0;
				if ( ms > 0 ) { metaParts.push( ( A.i18n.ms || '%d ms' ).replace( '%d', ms ) ); }
				if ( $b.attr( 'data-feedback' ) ) { metaParts.push( esc( $b.attr( 'data-feedback' ) ) ); }
				if ( metaParts.length ) {
					html += '<div class="openrag-chat-msg-meta">' + metaParts.join( ' · ' ) + '</div>';
				}
				html += '</div>';
			}
			$( '#openrag-chat-detail' ).html( html || ( A.i18n.noData || 'No data.' ) );
			$( '#openrag-chat-modal' ).show();
		} );

		/* Modal close */
		$( document ).on( 'click', '.openrag-modal-close', function () { $( this ).closest( '.openrag-modal' ).hide(); } );
		$( document ).on( 'click', '.openrag-modal', function ( e ) { if ( e.target === this ) { $( this ).hide(); } } );
	} );
} )( jQuery );
