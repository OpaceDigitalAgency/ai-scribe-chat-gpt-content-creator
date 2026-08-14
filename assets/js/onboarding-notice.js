/**
 * AI-Scribe onboarding / model-remap notice dismissal (REFACTOR.md §15.2).
 *
 * Persists dismissal server-side so the notice never re-shows. Handles both
 * the WordPress core X button (injected for .is-dismissible) and the inline
 * "Dismiss" button. No dependencies; enqueued only while a notice renders.
 */
( function () {
	'use strict';

	function persistDismiss( noticeEl ) {
		var notice = noticeEl.getAttribute( 'data-ai-scribe-notice' );
		var nonce = noticeEl.getAttribute( 'data-ai-scribe-nonce' );
		if ( ! notice || ! nonce || noticeEl.dataset.aiScribeDismissed ) {
			return;
		}
		noticeEl.dataset.aiScribeDismissed = '1';
		var body = new URLSearchParams();
		body.set( 'action', 'ai_scribe_dismiss_notice' );
		body.set( 'notice', notice );
		body.set( 'nonce', nonce );
		window
			.fetch( window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
			.catch( function () {
				/* Non-fatal: the notice simply re-renders next load. */
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}

		// Inline "Dismiss" button inside our notice.
		var inline = target.closest( '[data-ai-scribe-dismiss]' );
		if ( inline ) {
			var noticeEl = inline.closest( '[data-ai-scribe-notice]' );
			if ( noticeEl ) {
				persistDismiss( noticeEl );
				noticeEl.remove();
			}
			return;
		}

		// WP core dismiss X (added by common.js for .is-dismissible).
		var coreDismiss = target.closest( '.notice-dismiss' );
		if ( coreDismiss ) {
			var wrapped = coreDismiss.closest( '[data-ai-scribe-notice]' );
			if ( wrapped ) {
				persistDismiss( wrapped );
			}
		}
	} );
} )();
