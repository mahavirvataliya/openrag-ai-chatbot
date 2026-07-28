/**
 * OpenRag AI Chatbot chatbot widget — vanilla JS, no dependencies.
 *
 * - Streaming via fetch + ReadableStream (Server-Sent Events).
 * - Markdown rendered with a small inline parser that escapes HTML first (no XSS).
 * - Reasoning + citations + tool badges rendered when present.
 * - Feedback (👍/👎) with optional comment modal.
 * - Session history persisted in localStorage and loaded from server.
 * - All DOM is namespaced under #openrag-widget; classes prefixed openrag-.
 */
( function () {
	'use strict';

	var CFG = window.OpenRagConfig || {};
	var REST = CFG.restUrl || '/wp-json/openrag/v1';
	var SESSION_KEY = 'openrag_session';

	function $( sel, ctx ) { return ( ctx || document ).querySelector( sel ); }
	function el( tag, attrs, kids ) {
		var n = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				if ( 'class' === k ) { n.className = attrs[k]; }
				else if ( 'html' === k ) { n.innerHTML = attrs[k]; }
				else if ( null !== attrs[k] && undefined !== attrs[k] ) { n.setAttribute( k, attrs[k] ); }
			} );
		}
		( kids || [] ).forEach( function ( c ) {
			if ( 'string' === typeof c ) { n.appendChild( document.createTextNode( c ) ); }
			else if ( c ) { n.appendChild( c ); }
		} );
		return n;
	}

	/* ---------- Tiny markdown renderer (HTML-escaped first) ---------- */
	function escapeHtml( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}
	function md( text ) {
		var h = escapeHtml( text || '' );
		// Code blocks ```
		h = h.replace( /```([\s\S]*?)```/g, function ( _, code ) {
			return '<pre><code>' + code.replace( /^\n/, '' ) + '</code></pre>';
		} );
		// Inline code
		h = h.replace( /`([^`]+)`/g, '<code>$1</code>' );
		// Headings
		h = h.replace( /^### (.+)$/gm, '<h4>$1</h4>' )
		     .replace( /^## (.+)$/gm, '<h3>$1</h3>' )
		     .replace( /^# (.+)$/gm, '<h2>$1</h2>' );
		// Bold / italic
		h = h.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' )
		     .replace( /\*([^*]+)\*/g, '<em>$1</em>' );
		// Links [text](url)
		h = h.replace( /\[([^\]]+)\]\((https?:[^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>' );
		// Lists
		h = h.replace( /^\s*[-*] (.+)$/gm, '<li>$1</li>' );
		h = h.replace( /(<li>[\s\S]+?<\/li>)/g, '<ul>$1</ul>' );
		// Paragraphs (split on blank lines, but avoid double-wrapping blocks).
		var parts = h.split( /\n{2,}/ );
		h = parts.map( function ( p ) {
			if ( /^\s*<(h\d|ul|ol|pre|blockquote|li)/.test( p ) ) { return p; }
			return '<p>' + p.replace( /\n/g, '<br>' ) + '</p>';
		} ).join( '\n' );
		return h;
	}

	/* ---------- State ---------- */
	var widget, messages, input, sendBtn, launcher, window_;
	var sessionId = getSession();
	var history = [];
	var streaming = null;

	function getSession() {
		try {
			var s = localStorage.getItem( SESSION_KEY );
			if ( s ) { return s; }
		} catch ( e ) {}
		var ns = 'sess_' + Math.random().toString( 36 ).slice( 2 ) + Date.now().toString( 36 );
		try { localStorage.setItem( SESSION_KEY, ns ); } catch ( e ) {}
		return ns;
	}

	/* ---------- Boot ---------- */
	function boot() {
		widget = document.getElementById( 'openrag-widget' );
		if ( ! widget ) { return; }
		messages = $( '.openrag-messages', widget );
		input = $( '.openrag-input', widget );
		sendBtn = $( '.openrag-send', widget );
		window_ = $( '.openrag-window', widget );
		launcher = $( '.openrag-launcher', widget );

		if ( launcher ) {
			launcher.addEventListener( 'click', toggleOpen );
		}
		$( '.openrag-clear', widget ).addEventListener( 'click', clearChat );

		// Auto-resize textarea.
		input.addEventListener( 'input', function () {
			input.style.height = 'auto';
			input.style.height = Math.min( 120, input.scrollHeight ) + 'px';
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				submit();
			}
		} );
		$( '.openrag-form', widget ).addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			submit();
		} );

		// Open by default when inline (shortcode).
		if ( widget.classList.contains( 'openrag-inline' ) ) {
			widget.classList.add( 'is-open' );
			if ( window_ ) { window_.hidden = false; }
		}

		// Load prior history for this session.
		if ( sessionId ) {
			loadHistory();
		}
	}

	function toggleOpen() {
		var open = widget.classList.toggle( 'is-open' );
		if ( window_ ) { window_.hidden = ! open; }
		if ( launcher ) { launcher.setAttribute( 'aria-expanded', open ? 'true' : 'false' ); }
		if ( open ) {
			setTimeout( function () { input && input.focus(); scrollToBottom(); }, 50 );
		}
	}

	/* ---------- Rendering ---------- */
	function addMsg( role, content ) {
		var msg = el( 'div', { class: 'openrag-msg openrag-msg-' + role } );
		var bubble = el( 'div', { class: 'openrag-bubble openrag-bubble-' + role, html: role === 'user' ? escapeHtml( content ) : md( content ) } );
		msg.appendChild( bubble );
		messages.appendChild( msg );
		scrollToBottom();
		return { msg: msg, bubble: bubble };
	}

	function addTyping() {
		var msg = el( 'div', { class: 'openrag-msg openrag-msg-bot' } );
		var t = el( 'div', { class: 'openrag-bubble openrag-bubble-bot openrag-typing' }, [
			el( 'span' ), el( 'span' ), el( 'span' )
		] );
		msg.appendChild( t );
		messages.appendChild( msg );
		scrollToBottom();
		return msg;
	}

	function scrollToBottom() {
		messages.scrollTop = messages.scrollHeight;
	}

	function addBadge( text ) {
		var msg = el( 'div', { class: 'openrag-tool-badge' }, [ '🔧 ' + text ] );
		messages.appendChild( msg );
		scrollToBottom();
	}

	function addError( text ) {
		messages.appendChild( el( 'div', { class: 'openrag-error' }, [ text ] ) );
		scrollToBottom();
	}

	/* ---------- Chat ---------- */
	function submit() {
		var text = ( input.value || '' ).trim();
		if ( ! text || streaming ) { return; }

		addMsg( 'user', text );
		history.push( { role: 'user', content: text } );

		input.value = '';
		input.style.height = 'auto';
		sendBtn.disabled = true;

		var typing = addTyping();

		streamChat( text, typing );
	}

	function streamChat( text, typingNode ) {
		var ctrl = new AbortController();
		streaming = ctrl;

		fetch( REST + '/chat', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CFG.nonce || '',
			},
			body: JSON.stringify( {
				message: text,
				session_id: sessionId,
				history: history.slice( -10 ),
			} ),
			signal: ctrl.signal,
		} )
		.then( function ( resp ) {
			if ( ! resp.ok ) {
				return resp.text().then( function ( t ) {
					throw new Error( 'HTTP ' + resp.status + ': ' + t );
				} );
			}
			// Replace typing with a real bot bubble.
			if ( typingNode && typingNode.parentNode ) { typingNode.parentNode.removeChild( typingNode ); }
			var current = addMsg( 'bot', '' );
			var acc = '';
			var reasoningEl = null;

			var reader = resp.body.getReader();
			var decoder = new TextDecoder();
			var buffer = '';

			function pump() {
				return reader.read().then( function ( result ) {
					if ( result.done ) { return finishStream( current, acc ); }
					buffer += decoder.decode( result.value, { stream: true } );
					var lines = buffer.split( '\n' );
					buffer = lines.pop();

					lines.forEach( function ( line ) {
						if ( ! line ) { return; }
						if ( 0 === line.indexOf( 'event:' ) ) { return; }
						if ( 0 === line.indexOf( 'data:' ) ) {
							var data = line.slice( 5 ).trim();
							try {
								var evt = JSON.parse( data );
								handleEvent( evt, current, function ( t ) { acc += t; } );
							} catch ( e ) {}
						}
					} );
					return pump();
				} );
			}
			return pump();
		} )
		.catch( function ( err ) {
			if ( typingNode && typingNode.parentNode ) { typingNode.parentNode.removeChild( typingNode ); }
			addError( ( CFG.i18n && CFG.i18n.errorMessage ) || 'Error' );
			if ( window.console ) { console.error( '[openrag]', err ); }
		} )
		.finally( function () {
			streaming = null;
			sendBtn.disabled = false;
		} );
	}

	function handleEvent( evt, current, appendContent ) {
		if ( ! evt || ! evt.type ) { return; }
		switch ( evt.type ) {
			case 'meta':
				if ( evt.session_id ) { sessionId = evt.session_id; try { localStorage.setItem( SESSION_KEY, sessionId ); } catch ( e ) {} }
				break;
			case 'delta':
				appendContent( evt.content || '' );
				current.bubble.innerHTML = md( current.bubble.__raw = ( current.bubble.__raw || '' ) + ( evt.content || '' ) );
				scrollToBottom();
				break;
			case 'reasoning':
				if ( ! CFG.showReasoning ) { break; }
				if ( ! current.reasoningWrap ) {
					current.reasoningWrap = el( 'details', { class: 'openrag-reasoning' }, [
						el( 'summary', {}, [ ( CFG.i18n && CFG.i18n.reasoning) || 'Reasoning' ] ),
						current.reasoningBody = el( 'div', { class: 'openrag-reasoning-body' } ),
					] );
					current.msg.insertBefore( current.reasoningWrap, current.bubble );
				}
				current.reasoningBody.appendChild( document.createTextNode( evt.content || '' ) );
				current.reasoningBody.scrollTop = current.reasoningBody.scrollHeight;
				break;
			case 'citations':
				if ( ! CFG.showCitations ) { break; }
				renderCitations( current.msg, evt.sources || [] );
				break;
			case 'tool':
				addBadge( ( CFG.i18n && CFG.i18n.usingTool ) ? ( CFG.i18n.usingTool + ': ' + evt.name ) : ( '🔧 ' + evt.name ) );
				break;
			case 'tools':
				// Already handled via individual tool events; no-op.
				break;
			case 'error':
				addError( evt.message || 'Error' );
				break;
			case 'done':
				finalizeMessage( current, evt );
				break;
		}
	}

	function renderCitations( msgEl, sources ) {
		if ( ! sources || ! sources.length ) { return; }
		var wrap = el( 'div', { class: 'openrag-citations' } );
		wrap.appendChild( el( 'div', { class: 'openrag-citations-title' }, [ ( CFG.i18n && CFG.i18n.sources ) || 'Sources' ] ) );
		sources.forEach( function ( s, i ) {
			var a = el( 'a', { class: 'openrag-citation', href: s.url || '#', target: '_blank', rel: 'noopener noreferrer' }, [ '[' + ( i + 1 ) + '] ' + ( s.title || s.url ) ] );
			if ( ! s.url ) { a.removeAttribute( 'href' ); }
			wrap.appendChild( a );
		} );
		msgEl.appendChild( wrap );
		scrollToBottom();
	}

	function finalizeMessage( current, doneEvent ) {
		// Add feedback buttons.
		if ( CFG.i18n ) {
			var actions = el( 'div', { class: 'openrag-actions' } );
			var up = el( 'button', { class: 'openrag-feedback', 'data-feedback': 'up', title: CFG.i18n.feedbackGood }, [ '👍' ] );
			var down = el( 'button', { class: 'openrag-feedback', 'data-feedback': 'down', title: CFG.i18n.feedbackBad }, [ '👎' ] );
			up.addEventListener( 'click', function () { sendFeedback( doneEvent.message_id || current.msg.dataset.id, 'up', up ); } );
			down.addEventListener( 'click', function () {
				promptComment( function ( c ) { sendFeedback( doneEvent.message_id || current.msg.dataset.id, 'down', down, c ); } );
			} );
			actions.appendChild( up );
			actions.appendChild( down );
			current.msg.appendChild( actions );
		}
		// Store assistant turn in local history.
		history.push( { role: 'assistant', content: current.bubble.__raw || '' } );
		scrollToBottom();
	}

	function finishStream( current, acc ) {
		// No-op; 'done' event drives finalization.
	}

	/* ---------- Feedback ---------- */
	function sendFeedback( messageId, feedback, btnEl, comment ) {
		if ( ! messageId ) { return; }
		fetch( REST + '/feedback', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' },
			body: JSON.stringify( { message_id: parseInt( messageId, 10 ), feedback: feedback, comment: comment || '' } ),
		} ).then( function () {
			var siblings = btnEl.parentNode.querySelectorAll( '.openrag-feedback' );
			siblings.forEach( function ( s ) { s.classList.remove( 'is-active' ); } );
			btnEl.classList.add( 'is-active' );
		} ).catch( function () {} );
	}

	function promptComment( cb ) {
		var overlay = el( 'div', { class: 'openrag-feedback-overlay', style: 'position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:2147483001;display:flex;align-items:center;justify-content:center;' } );
		var box = el( 'div', { style: 'background:#fff;color:#0f172a;padding:16px;border-radius:12px;max-width:340px;width:90%;' } );
		box.appendChild( el( 'p', { style: 'margin:0 0 8px;font-weight:600;' }, [ ( CFG.i18n && CFG.i18n.feedbackBad ) || 'Feedback' ] ) );
		var ta = el( 'textarea', { rows: 3, style: 'width:100%;border:1px solid #ccc;border-radius:8px;padding:8px;' } );
		ta.setAttribute( 'placeholder', ( CFG.i18n && CFG.i18n.feedbackComment ) || '' );
		var row = el( 'div', { style: 'display:flex;gap:8px;justify-content:flex-end;margin-top:10px;' } );
		var cancel = el( 'button', { style: 'padding:6px 12px;border:1px solid #ccc;border-radius:6px;background:#fff;cursor:pointer;' }, [ 'Cancel' ] );
		var send = el( 'button', { style: 'padding:6px 12px;border:none;border-radius:6px;background:var(--openrag-primary,#3b82f6);color:#fff;cursor:pointer;' }, [ ( CFG.i18n && CFG.i18n.send ) || 'Send' ] );
		cancel.addEventListener( 'click', function () { document.body.removeChild( overlay ); } );
		send.addEventListener( 'click', function () {
			var v = ta.value.trim();
			document.body.removeChild( overlay );
			cb( v );
		} );
		row.appendChild( cancel );
		row.appendChild( send );
		box.appendChild( ta );
		box.appendChild( row );
		overlay.appendChild( box );
		document.body.appendChild( overlay );
		ta.focus();
	}

	/* ---------- History ---------- */
	function loadHistory() {
		fetch( REST + '/history?session_id=' + encodeURIComponent( sessionId ) + '&limit=20', {
			headers: { 'X-WP-Nonce': CFG.nonce || '' },
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) {
			if ( ! data || ! data.turns || ! data.turns.length ) { return; }
			// Clear the welcome message before rendering history.
			messages.innerHTML = '';
			history = [];
			data.turns.forEach( function ( t ) {
				if ( t.role === 'user' ) {
					addMsg( 'user', t.content );
					history.push( { role: 'user', content: t.content } );
				} else if ( t.role === 'assistant' ) {
					var c = addMsg( 'bot', t.content );
					history.push( { role: 'assistant', content: t.content } );
					if ( CFG.showCitations && t.citations && t.citations.length ) {
						renderCitations( c.msg, t.citations );
					}
					if ( t.id ) { c.msg.dataset.id = t.id; }
				}
			} );
			scrollToBottom();
		} )
		.catch( function () {} );
	}

	function clearChat() {
		if ( ! confirm( ( CFG.i18n && CFG.i18n.clearChat ) || 'Clear chat?' ) ) { return; }
		fetch( REST + '/history?session_id=' + encodeURIComponent( sessionId ), {
			method: 'DELETE',
			headers: { 'X-WP-Nonce': CFG.nonce || '' },
		} ).finally( function () {
			messages.innerHTML = '';
			history = [];
			if ( CFG.welcome ) {
				var m = el( 'div', { class: 'openrag-msg openrag-msg-bot' } );
				m.appendChild( el( 'div', { class: 'openrag-bubble openrag-bubble-bot', html: escapeHtml( CFG.welcome ) } ) );
				messages.appendChild( m );
			}
		} );
	}

	/* ---------- Init ---------- */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
