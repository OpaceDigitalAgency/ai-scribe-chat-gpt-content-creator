/**
 * AI-Scribe notification centre.
 *
 * One viewport-fixed, accessible outcome channel shared by the wizard,
 * settings and supporting admin screens. Server success must be confirmed by
 * the caller before show() is used; this class only presents the result.
 *
 * @package AI_Scribe
 * @since   3.1.0
 */

( function ( window, document ) {
	'use strict';

	const DEFAULT_DURATIONS = {
		success: 8000,
		info: 12000,
		warning: 0,
		error: 0
	};
	const ICONS = {
		success: '\u2713',
		info: 'i',
		warning: '!',
		error: '\u00d7'
	};
	const HEADINGS = {
		success: 'Action completed',
		info: 'Information',
		warning: 'Action needed',
		error: 'Action failed'
	};
	class AIScribeNotificationCenter {
		constructor() {
			this.items = new Map();
			this.sequence = 0;
			this.region = null;
			this.boundEscape = this.onEscape.bind( this );
		}

		ensureRegion() {
			if ( this.region && this.region.isConnected ) {
				return this.region;
			}
			this.region = document.getElementById( 'ai-scribe-notification-centre' );
			if ( ! this.region ) {
				this.region = document.createElement( 'section' );
				this.region.id = 'ai-scribe-notification-centre';
				this.region.className = 'ai-scribe-notification-centre';
				this.region.setAttribute( 'aria-label', 'Notifications' );
				this.region.setAttribute( 'aria-live', 'off' );
				document.body.appendChild( this.region );
			}
			document.removeEventListener( 'keydown', this.boundEscape );
			document.addEventListener( 'keydown', this.boundEscape );
			return this.region;
		}

		/**
		 * @param {string|Object} message Message or complete options object.
		 * @param {string}        type    success|info|warning|error.
		 * @param {number|null}   duration Override duration in milliseconds.
		 * @return {HTMLElement}
		 */
		show( message, type = 'info', duration = null ) {
			const options = typeof message === 'object' && message !== null
				? Object.assign( {}, message )
				: { message, type, duration };
			const normalType = Object.prototype.hasOwnProperty.call( HEADINGS, options.type )
				? options.type
				: 'info';
			let detail = String( options.message || '' ).trim();
			let title = String( options.title || '' ).trim();
			if ( ! title && detail ) {
				const sentenceEnd = detail.indexOf( '.' );
				if ( detail.length <= 80 ) {
					title = detail;
					detail = '';
				} else if ( sentenceEnd > 0 && sentenceEnd <= 80 ) {
					title = detail.slice( 0, sentenceEnd );
					detail = detail.slice( sentenceEnd + 1 ).trim();
				}
			}
			title = title || HEADINGS[ normalType ];
			const key = String( options.key || `${ normalType }:${ title }:${ detail }` );
			const existing = this.items.get( key );

			if ( existing ) {
				this.bump( existing );
				return existing.element;
			}

			const item = {
				id: `ai-scribe-notification-${ ++this.sequence }`,
				key,
				type: normalType,
				title,
				detail,
				announce: options.announce !== false,
				duration: this.durationFor( normalType, options.duration ),
				remaining: 0,
				startedAt: 0,
				timer: null,
				count: 1,
				element: null
			};
			item.remaining = item.duration;
			item.element = this.build( item );
			this.items.set( key, item );

			this.mount( item );
			return item.element;
		}

		durationFor( type, requested ) {
			if ( requested !== null && requested !== undefined ) {
				return Math.max( 0, Number( requested ) || 0 );
			}
			return DEFAULT_DURATIONS[ type ];
		}

		build( item ) {
			const el = document.createElement( 'article' );
			el.className = `ai-scribe-notification ai-scribe-notification-${ item.type }`;
			el.id = item.id;
			el.dataset.notificationKey = item.key;
			el.dataset.notificationType = item.type;
			el.setAttribute( 'role', item.announce ? ( item.type === 'error' ? 'alert' : 'status' ) : 'group' );
			el.setAttribute( 'aria-atomic', 'true' );

			const icon = document.createElement( 'span' );
			icon.className = 'ai-scribe-notification-icon';
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.textContent = ICONS[ item.type ];

			const copy = document.createElement( 'div' );
			copy.className = 'ai-scribe-notification-copy';
			const heading = document.createElement( 'strong' );
			heading.className = 'ai-scribe-notification-title';
			heading.textContent = item.title;
			copy.appendChild( heading );

			if ( item.detail ) {
				const detail = document.createElement( 'p' );
				detail.className = 'ai-scribe-notification-detail';
				detail.textContent = item.detail;
				copy.appendChild( detail );
			}

			const repeat = document.createElement( 'span' );
			repeat.className = 'ai-scribe-notification-repeat';
			repeat.hidden = true;
			copy.appendChild( repeat );

			const close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'ai-scribe-notification-close';
			close.setAttribute( 'aria-label', `Dismiss ${ item.title.toLowerCase() } notification` );
			close.textContent = '\u00d7';
			close.addEventListener( 'click', () => this.dismiss( item ) );

			el.addEventListener( 'mouseenter', () => this.pause( item ) );
			el.addEventListener( 'mouseleave', () => this.resume( item ) );
			el.addEventListener( 'focusin', () => this.pause( item ) );
			el.addEventListener( 'focusout', () => this.resume( item ) );
			el.appendChild( icon );
			el.appendChild( copy );
			el.appendChild( close );
			return el;
		}

		mount( item ) {
			this.ensureRegion().appendChild( item.element );
			item.element.classList.add( 'is-visible' );
			this.resume( item );
		}

		bump( item ) {
			item.count += 1;
			const repeat = item.element.querySelector( '.ai-scribe-notification-repeat' );
			if ( repeat ) {
				repeat.textContent = `Repeated ${ item.count } times`;
				repeat.hidden = false;
			}
			item.remaining = item.duration;
			this.resume( item, true );
			item.element.classList.remove( 'is-repeated' );
			window.requestAnimationFrame( () => item.element.classList.add( 'is-repeated' ) );
		}

		pause( item ) {
			if ( ! item.timer ) {
				return;
			}
			window.clearTimeout( item.timer );
			item.timer = null;
			item.remaining = Math.max( 0, item.remaining - ( Date.now() - item.startedAt ) );
		}

		resume( item, reset = false ) {
			if ( reset ) {
				window.clearTimeout( item.timer );
				item.timer = null;
			}
			if ( item.duration === 0 || item.timer || ! item.element.isConnected ) {
				return;
			}
			item.startedAt = Date.now();
			item.timer = window.setTimeout( () => this.dismiss( item ), item.remaining );
		}

		dismiss( item ) {
			window.clearTimeout( item.timer );
			item.timer = null;
			this.items.delete( item.key );
			if ( item.element.isConnected ) {
				item.element.remove();
			}
			if ( this.items.size === 0 && this.region ) {
				this.region.remove();
				this.region = null;
			}
		}

		onEscape( event ) {
			if ( event.key !== 'Escape' ) {
				return;
			}
			const focusedClose = document.activeElement && document.activeElement.closest
				? document.activeElement.closest( '.ai-scribe-notification-close' )
				: null;
			if ( focusedClose ) {
				const notification = focusedClose.closest( '.ai-scribe-notification' );
				const item = notification && this.items.get( notification.dataset.notificationKey );
				if ( item ) {
					event.preventDefault();
					this.dismiss( item );
				}
			}
		}
	}

	window.AIScribeNotificationCenter = AIScribeNotificationCenter;
	window.aiScribeNotifications = window.aiScribeNotifications || new AIScribeNotificationCenter();
}( window, document ) );
