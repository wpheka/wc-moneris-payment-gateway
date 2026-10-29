/**
 * WPHEKA admin UI kit: behaviour for the plugin screens.
 *
 * Progressive enhancement only. Every form posts and every tab is a link, so the
 * screens work with this file blocked. Plain JavaScript, no dependencies.
 *
 * @package WPHEKA\AdminUI
 */

( function () {
	'use strict';

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}
	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}
	function fire( el, name ) {
		var ev;
		try {
			ev = new Event( name, { bubbles: true } );
		} catch ( e ) {
			ev = document.createEvent( 'Event' );
			ev.initEvent( name, true, true );
		}
		el.dispatchEvent( ev );
	}

	function init( ui ) {
		form( ui );
		chipfind( ui );
		kvfind( ui );
		subs( ui );
		units( ui );
		scrollspy( ui );
		find( ui );
		more( ui );
		dismiss( ui );
		toast( ui );
	}

	// Unsaved-changes bar: Save submits the settings form, Discard resets it.
	function form( ui ) {
		var f = $( 'form[data-w-form]', ui );
		var bar = $( '.w-savebar', ui );
		if ( ! f || ! bar ) {
			return;
		}
		var dirty = false;
		var submitting = false;

		function show() {
			if ( ! dirty ) {
				dirty = true;
				bar.classList.add( 'is-shown' );
			}
		}
		function hide() {
			dirty = false;
			bar.classList.remove( 'is-shown' );
		}
		// Fields owned by another form (an add-on's own form="" box) or marked
		// data-w-nodirty (a search box) are not settings, so they do not count.
		function changed( e ) {
			var t = e.target;
			if ( ( t.form && t.form !== f ) || ( t.hasAttribute && t.hasAttribute( 'data-w-nodirty' ) ) ) {
				return;
			}
			show();
		}
		f.addEventListener( 'input', changed );
		f.addEventListener( 'change', changed );
		f.addEventListener( 'submit', function () {
			submitting = true;
		} );
		f.addEventListener( 'reset', function () {
			// Let the browser restore values first, then resync dependent parts.
			window.setTimeout( function () {
				$$( 'input, select, textarea', f ).forEach( function ( el ) {
					fire( el, 'w:sync' );
				} );
				hide();
			}, 0 );
		} );
		var discard = $( '[data-w-discard]', bar );
		if ( discard ) {
			discard.addEventListener( 'click', function () {
				f.reset();
			} );
		}
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty && ! submitting ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.metaKey || e.ctrlKey ) && 's' === e.key && dirty ) {
				e.preventDefault();
				if ( f.requestSubmit ) {
					f.requestSubmit();
				} else {
					f.submit();
				}
			}
		} );
	}

	// A box that narrows a long list of chips. Ticked chips always stay in view.
	function chipfind( ui ) {
		$$( '[data-w-chipfind]', ui ).forEach( function ( box ) {
			var list = document.getElementById( box.getAttribute( 'data-w-chipfind' ) );
			if ( ! list ) {
				return;
			}
			box.addEventListener( 'input', function () {
				var q = box.value.trim().toLowerCase();
				$$( '.w-chip', list ).forEach( function ( chip ) {
					var input = $( 'input', chip );
					chip.hidden = !! q && ! input.checked && -1 === chip.textContent.toLowerCase().indexOf( q );
				} );
			} );
			// Enter in the box would submit the settings form.
			box.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key ) {
					e.preventDefault();
				}
			} );
		} );
	}

	// Narrow key/value rows as you type; hide sections with no match.
	function kvfind( ui ) {
		$$( '[data-w-kvfind]', ui ).forEach( function ( box ) {
			var area = document.getElementById( box.getAttribute( 'data-w-kvfind' ) );
			if ( ! area ) {
				return;
			}
			var empty = $( '[data-w-kv-empty]', area );
			box.addEventListener( 'input', function () {
				var q = box.value.trim().toLowerCase();
				var shown = 0;
				$$( '.w-kv > div', area ).forEach( function ( r ) {
					r.hidden = !! q && -1 === r.textContent.toLowerCase().indexOf( q );
					if ( ! r.hidden ) {
						shown++;
					}
				} );
				$$( '.w-section', area ).forEach( function ( s ) {
					s.hidden = !! q && ! $( '.w-kv > div:not([hidden])', s );
				} );
				if ( empty ) {
					empty.hidden = ! q || shown > 0;
				}
			} );
		} );
	}

	// Rows that only apply while a switch is on.
	function subs( ui ) {
		$$( '[data-w-controls]', ui ).forEach( function ( sw ) {
			var target = document.getElementById( sw.getAttribute( 'data-w-controls' ) );
			if ( ! target ) {
				return;
			}
			function sync() {
				target.classList.toggle( 'is-off', ! sw.checked );
				sw.setAttribute( 'aria-expanded', sw.checked ? 'true' : 'false' );
			}
			sw.addEventListener( 'change', sync );
			sw.addEventListener( 'w:sync', sync );
			sync();
		} );
	}

	// "= 1 hour" under a minutes field.
	function units( ui ) {
		var l10n = window.wphekaUiL10n || {};
		$$( '[data-w-minutes]', ui ).forEach( function ( input ) {
			var hint = document.getElementById( input.getAttribute( 'data-w-minutes' ) );
			if ( ! hint ) {
				return;
			}
			function round( n ) {
				return Math.round( n * 10 ) / 10;
			}
			function human() {
				var m = parseInt( input.value, 10 ) || 0;
				var text;
				if ( m >= 1440 ) {
					text = ( 1440 === m ? l10n.day || '%s day' : l10n.days || '%s days' ).replace( '%s', round( m / 1440 ) );
				} else if ( m >= 60 ) {
					text = ( 60 === m ? l10n.hour || '%s hour' : l10n.hours || '%s hours' ).replace( '%s', round( m / 60 ) );
				} else {
					text = ( 1 === m ? l10n.minute || '%s minute' : l10n.minutes || '%s minutes' ).replace( '%s', m );
				}
				hint.textContent = '= ' + text;
			}
			input.addEventListener( 'input', human );
			input.addEventListener( 'w:sync', human );
			human();
		} );
	}

	// Highlight the section in view; nav links glide to their section.
	function scrollspy( ui ) {
		var links = $$( '.w-nav-link[href^="#"]', ui );
		var held = false;
		var timer;
		if ( ! links.length ) {
			return;
		}

		function mark( link ) {
			links.forEach( function ( l ) {
				var on = l === link;
				l.classList.toggle( 'is-active', on );
				if ( on ) {
					l.setAttribute( 'aria-current', 'true' );
					var nav = l.parentNode;
					if ( nav.scrollWidth > nav.clientWidth ) {
						nav.scrollTo( { left: l.offsetLeft - 16, behavior: 'smooth' } );
					}
				} else {
					l.removeAttribute( 'aria-current' );
				}
			} );
		}

		// Hold the highlight on the clicked link until the page stops moving.
		function settle() {
			clearTimeout( timer );
			timer = setTimeout( function () {
				held = false;
				window.removeEventListener( 'scroll', settle );
			}, 120 );
		}

		links.forEach( function ( l ) {
			l.addEventListener( 'click', function ( ev ) {
				var s = document.getElementById( l.getAttribute( 'href' ).slice( 1 ) );
				if ( ! s || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button ) {
					return;
				}
				ev.preventDefault();
				var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
				held = true;
				mark( l );
				window.addEventListener( 'scroll', settle );
				settle();
				s.scrollIntoView( { behavior: still ? 'auto' : 'smooth', block: 'start' } );
				if ( window.history && history.pushState ) {
					history.pushState( null, '', l.getAttribute( 'href' ) );
				}
				// Keyboard and screen reader users land in the section too.
				if ( ! s.hasAttribute( 'tabindex' ) ) {
					s.setAttribute( 'tabindex', '-1' );
				}
				s.focus( { preventScroll: true } );
			} );
		} );

		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		var io = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( e ) {
					if ( held || ! e.isIntersecting ) {
						return;
					}
					mark( links.filter( function ( l ) {
						return l.getAttribute( 'href' ) === '#' + e.target.id;
					} )[ 0 ] );
				} );
			},
			{ rootMargin: '-25% 0px -65% 0px' }
		);
		links.forEach( function ( l ) {
			var s = document.getElementById( l.getAttribute( 'href' ).slice( 1 ) );
			if ( s ) {
				io.observe( s );
			}
		} );
	}

	// Filter settings rows as you type; "/" focuses the box.
	function find( ui ) {
		var box = $( '[data-w-find]', ui );
		if ( ! box ) {
			return;
		}
		var empty = $( '[data-w-find-empty]', ui );
		document.addEventListener( 'keydown', function ( e ) {
			var t = document.activeElement;
			if ( '/' === e.key && ! /INPUT|TEXTAREA|SELECT/.test( t.tagName ) && ! t.isContentEditable ) {
				e.preventDefault();
				box.focus();
			}
		} );
		box.addEventListener( 'input', function () {
			var q = box.value.trim().toLowerCase();
			var shown = 0;
			$$( 'form[data-w-form] .w-section', ui ).forEach( function ( s ) {
				var any = false;
				$$( '.w-row', s ).forEach( function ( r ) {
					var hit = ! q || -1 !== r.textContent.toLowerCase().indexOf( q );
					r.classList.toggle( 'is-filtered', ! hit );
					if ( hit ) {
						any = true;
					}
				} );
				$$( '.w-sub', s ).forEach( function ( sub ) {
					if ( q ) {
						sub.classList.remove( 'is-off' );
					}
				} );
				s.hidden = !! q && ! any;
				if ( ! s.hidden ) {
					shown++;
				}
			} );
			if ( empty ) {
				empty.hidden = ! q || shown > 0;
			}
			if ( ! q ) {
				$$( '[data-w-controls]', ui ).forEach( function ( sw ) {
					fire( sw, 'w:sync' );
				} );
			}
		} );
	}

	// "More from WPHEKA": arrows and page dots.
	function more( ui ) {
		var track = $( '.w-track', ui );
		if ( ! track ) {
			return;
		}
		var prev = $( '[data-w-prev]', ui );
		var next = $( '[data-w-next]', ui );
		var dots = $( '[data-w-dots]', ui );
		// Right to left, scrollLeft runs from 0 down to negative numbers.
		function rtl() {
			return 'rtl' === window.getComputedStyle( track ).direction;
		}
		function update() {
			var max = track.scrollWidth - track.clientWidth - 2;
			var pos = Math.abs( track.scrollLeft );
			if ( prev ) {
				prev.disabled = pos <= 2;
			}
			if ( next ) {
				next.disabled = pos >= max;
			}
			if ( dots ) {
				var n = Math.max( 1, Math.round( track.scrollWidth / track.clientWidth ) );
				var at = max <= 0 ? 0 : Math.round( ( pos / max ) * ( n - 1 ) );
				if ( dots.children.length !== n ) {
					dots.innerHTML = new Array( n + 1 ).join( '<span></span>' );
				}
				$$( 'span', dots ).forEach( function ( d, i ) {
					d.classList.toggle( 'is-on', i === at );
				} );
				dots.hidden = n < 2;
			}
		}
		function step( dir ) {
			track.scrollBy( { left: ( rtl() ? -dir : dir ) * ( track.clientWidth + 16 ), behavior: 'smooth' } );
		}
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				step( -1 );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				step( 1 );
			} );
		}
		track.addEventListener(
			'scroll',
			function () {
				window.requestAnimationFrame( update );
			},
			{ passive: true }
		);
		window.addEventListener( 'resize', update );
		update();
	}

	// Dismiss links (for example "Getting started"): hide at once, record on the server.
	function dismiss( ui ) {
		$$( '[data-w-dismiss]', ui ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				var target = a.closest( a.getAttribute( 'data-w-dismiss' ) );
				if ( ! target || ! window.fetch ) {
					return;
				}
				e.preventDefault();
				target.remove();
				// keepalive: the request finishes even if the page is left straight away.
				window.fetch( a.href, { credentials: 'same-origin', keepalive: true } );
			} );
		} );
	}

	// The "Settings saved" toast arrives with the page after a save and fades by itself.
	// An error toast stays until its close button is used.
	function toast( ui ) {
		var err = $( '.w-toast--error.is-shown', ui );
		if ( err ) {
			var close = $( '[data-w-toast-close]', err );
			if ( close ) {
				close.addEventListener( 'click', function () {
					err.classList.remove( 'is-shown' );
				} );
			}
		}
		var t = $( '.w-toast.is-shown:not(.w-toast--error)', ui );
		if ( t ) {
			window.setTimeout( function () {
				t.classList.remove( 'is-shown' );
			}, 2800 );
		}
		if ( ( t || err ) && window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.delete( 'w-saved' );
			url.searchParams.delete( 'w-error' );
			window.history.replaceState( null, '', url.toString() );
		}
	}

	function boot() {
		$$( '.wpheka-ui' ).forEach( init );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
