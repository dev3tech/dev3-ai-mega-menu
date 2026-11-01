/**
 * DEV3 AI Mega Menu: front-end behaviour (vanilla JS, no dependencies).
 *
 * Disclosure navigation pattern: every submenu has a button with aria-expanded/aria-controls.
 * - Hover (desktop, mouse only) or click opens panels; Escape closes and returns focus.
 * - Left/Right/Home/End move between top-level items, Down opens a panel and moves into it,
 *   Up/Down move inside flyouts; tabs use the ARIA tabs pattern.
 * - Below the breakpoint: a toggle opens the accordion or the off-canvas drawer (focus trapped).
 */

const OPEN = 'is-open';
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';

function parseOptions( nav ) {
	try {
		return JSON.parse( nav.getAttribute( 'data-dev3mm' ) || '{}' );
	} catch ( e ) {
		return {};
	}
}

function visible( el ) {
	return !! ( el && ( el.offsetWidth || el.offsetHeight || el.getClientRects().length ) );
}

/**
 * The control (link-button or sub-toggle) that owns a panel inside an item.
 *
 * @param {Element} li Item.
 * @return {Element|null} Control.
 */
export function controlOf( li ) {
	const head = li && li.querySelector( ':scope > .dev3mm__head' );
	return head ? head.querySelector( '[aria-controls]' ) : null;
}

/**
 * First focusable element in an item's head (the link, or the button when there is no link).
 *
 * @param {Element} li Item.
 * @return {Element|null} Element.
 */
function headFocusable( li ) {
	const head = li && li.querySelector( ':scope > .dev3mm__head' );
	return head ? head.querySelector( 'a[href], button' ) : null;
}

/**
 * Initialises one menu. Safe to call twice.
 *
 * @param {HTMLElement} nav nav.dev3mm element.
 * @return {Object|null} API { open, close, closeAll, isMobile, destroy }.
 */
export function initMenu( nav ) {
	if ( ! nav || nav.dev3mm ) {
		return nav ? nav.dev3mm : null;
	}
	const win = nav.ownerDocument.defaultView || window;
	const doc = nav.ownerDocument;
	const opts = parseOptions( nav );
	const bp = Number( opts.bp ) || 0;
	const mq = bp && win.matchMedia ? win.matchMedia( `(max-width: ${ bp - 0.02 }px)` ) : null;
	const isMobile = () => !! ( mq && mq.matches );
	const hover = opts.trigger !== 'click';
	const delay = typeof opts.delay === 'number' ? opts.delay : 150;
	const menu = nav.querySelector( '.dev3mm__menu' );
	const toggle = nav.querySelector( '.dev3mm__toggle' );
	const drawer = nav.querySelector( '.dev3mm__drawer' );
	const timers = new WeakMap();
	const cleanups = [];

	const on = ( el, type, fn, options ) => {
		el.addEventListener( type, fn, options );
		cleanups.push( () => el.removeEventListener( type, fn, options ) );
	};

	function setOpen( li, open ) {
		const control = controlOf( li );
		if ( ! control ) {
			return;
		}
		li.classList.toggle( OPEN, open );
		control.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( ! open ) {
			li.querySelectorAll( `.${ OPEN }` ).forEach( ( child ) => setOpen( child, false ) );
		} else if ( ! isMobile() ) {
			// One open branch per level on desktop.
			const parent = li.parentElement;
			Array.from( parent ? parent.children : [] ).forEach( ( sib ) => {
				if ( sib !== li && sib.classList.contains( OPEN ) ) {
					setOpen( sib, false );
				}
			} );
		}
		if ( open ) {
			keepInViewport( li );
		}
	}

	function closeAll() {
		menu.querySelectorAll( `.dev3mm__item.${ OPEN }` ).forEach( ( li ) => setOpen( li, false ) );
	}

	/* Flips nested flyouts that would leave the viewport. */
	function keepInViewport( li ) {
		const panel = li.querySelector( ':scope > .dev3mm__panel--flyout' );
		if ( ! panel || isMobile() ) {
			return;
		}
		panel.style.removeProperty( 'left' );
		panel.style.removeProperty( 'right' );
		const rect = panel.getBoundingClientRect();
		const width = doc.documentElement.clientWidth;
		if ( rect.right > width - 8 ) {
			if ( li.classList.contains( 'dev3mm__item--sub' ) ) {
				panel.style.left = 'auto';
				panel.style.right = 'calc(100% + 6px)';
			} else {
				panel.style.left = 'auto';
				panel.style.right = '0';
			}
		}
	}

	/* Full-width panels: measure the nav offset and viewport width (no 100vw scrollbar overflow). */
	function measure() {
		const rect = nav.getBoundingClientRect();
		nav.style.setProperty( '--dev3mm-offset', `${ Math.round( rect.left ) }px` );
		nav.style.setProperty( '--dev3mm-vw', `${ doc.documentElement.clientWidth }px` );
		const bar = doc.getElementById( 'wpadminbar' );
		const top = bar && win.getComputedStyle( bar ).position === 'fixed' ? bar.offsetHeight : 0;
		nav.style.setProperty( '--dev3mm-top', `${ top }px` );
	}

	// Submenu toggles (click / tap / Enter / Space).
	on( menu, 'click', ( event ) => {
		const control = event.target.closest( '[aria-controls][aria-expanded]' );
		if ( control && menu.contains( control ) && ! control.matches( '[role="tab"]' ) ) {
			event.preventDefault();
			const li = control.closest( '.dev3mm__item' );
			setOpen( li, ! li.classList.contains( OPEN ) );
			return;
		}
		const tab = event.target.closest( '[role="tab"]' );
		if ( tab ) {
			activateTab( tab );
		}
	} );

	// Hover (mouse pointers only, so touch taps keep working).
	if ( hover ) {
		const enter = ( event ) => {
			if ( event.pointerType && event.pointerType !== 'mouse' ) {
				return;
			}
			if ( isMobile() ) {
				return;
			}
			const li = event.currentTarget;
			clearTimeout( timers.get( li ) );
			timers.set( li, setTimeout( () => setOpen( li, true ), li.classList.contains( 'dev3mm__item--top' ) ? delay : 60 ) );
		};
		const leave = ( event ) => {
			if ( ( event.pointerType && event.pointerType !== 'mouse' ) || isMobile() ) {
				return;
			}
			const li = event.currentTarget;
			clearTimeout( timers.get( li ) );
			timers.set( li, setTimeout( () => setOpen( li, false ), Math.max( 120, delay ) ) );
		};
		menu.querySelectorAll( '.dev3mm__item' ).forEach( ( li ) => {
			if ( controlOf( li ) ) {
				on( li, 'pointerenter', enter );
				on( li, 'pointerleave', leave );
			}
		} );
		// Hovering a tab activates it.
		menu.querySelectorAll( '[role="tab"]' ).forEach( ( tab ) => {
			on( tab, 'pointerenter', ( event ) => {
				if ( event.pointerType === 'mouse' && ! isMobile() ) {
					activateTab( tab );
				}
			} );
		} );
	}

	// Close when focus leaves an open branch (desktop).
	on( menu, 'focusout', ( event ) => {
		if ( isMobile() ) {
			return;
		}
		const next = event.relatedTarget;
		menu.querySelectorAll( `.dev3mm__item.${ OPEN }` ).forEach( ( li ) => {
			if ( ! next || ! li.contains( next ) ) {
				setOpen( li, false );
			}
		} );
	} );

	// Click outside.
	on( doc, 'click', ( event ) => {
		if ( ! nav.contains( event.target ) && ! isMobile() ) {
			closeAll();
		}
	} );

	// Keyboard.
	on( nav, 'keydown', ( event ) => {
		const key = event.key;
		if ( key === 'Escape' || key === 'Esc' ) {
			const open = event.target.closest( `.dev3mm__item.${ OPEN }` ) || menu.querySelector( `.dev3mm__item--top.${ OPEN }` );
			if ( open ) {
				setOpen( open, false );
				const control = controlOf( open );
				if ( control ) {
					control.focus();
				}
				event.preventDefault();
				return;
			}
			if ( nav.classList.contains( 'is-mobile-open' ) ) {
				setDrawer( false );
				event.preventDefault();
			}
			return;
		}
		if ( event.target.matches( '[role="tab"]' ) ) {
			tabKeys( event );
			return;
		}
		if ( key === 'Tab' && isMobile() && nav.classList.contains( 'dev3mm--mobile-offcanvas' ) && nav.classList.contains( 'is-mobile-open' ) ) {
			trap( event );
			return;
		}
		if ( isMobile() || event.target.matches( 'input, textarea, select' ) ) {
			return;
		}
		const li = event.target.closest( '.dev3mm__item' );
		if ( ! li ) {
			return;
		}
		const vertical = nav.classList.contains( 'dev3mm--vertical' );
		const inHead = event.target.closest( '.dev3mm__head' ) && event.target.closest( '.dev3mm__head' ).parentElement === li;
		if ( li.classList.contains( 'dev3mm__item--top' ) && inHead ) {
			const tops = Array.from( menu.children ).filter( ( el ) => el.classList.contains( 'dev3mm__item--top' ) && visible( el ) );
			const index = tops.indexOf( li );
			const nextKey = vertical ? 'ArrowDown' : 'ArrowRight';
			const prevKey = vertical ? 'ArrowUp' : 'ArrowLeft';
			const openKey = vertical ? 'ArrowRight' : 'ArrowDown';
			let target = null;
			if ( key === nextKey ) {
				target = tops[ ( index + 1 ) % tops.length ];
			} else if ( key === prevKey ) {
				target = tops[ ( index - 1 + tops.length ) % tops.length ];
			} else if ( key === 'Home' ) {
				target = tops[ 0 ];
			} else if ( key === 'End' ) {
				target = tops[ tops.length - 1 ];
			} else if ( key === openKey && controlOf( li ) ) {
				event.preventDefault();
				setOpen( li, true );
				const panel = li.querySelector( ':scope > .dev3mm__panel' );
				const first = panel && Array.from( panel.querySelectorAll( FOCUSABLE ) ).find( visible );
				if ( first ) {
					first.focus();
				}
				return;
			}
			if ( target ) {
				event.preventDefault();
				const focusable = headFocusable( target );
				if ( focusable ) {
					focusable.focus();
				}
			}
			return;
		}
		// Inside a flyout: Up/Down between sibling items, Right opens a nested flyout, Left closes it.
		const dropdown = event.target.closest( '.dev3mm__dropdown' );
		if ( dropdown && ( key === 'ArrowDown' || key === 'ArrowUp' ) ) {
			const links = Array.from( dropdown.children ).map( headFocusable ).filter( Boolean );
			const current = headFocusable( li );
			const index = links.indexOf( current );
			if ( index > -1 ) {
				event.preventDefault();
				links[ ( index + ( key === 'ArrowDown' ? 1 : -1 ) + links.length ) % links.length ].focus();
			}
			return;
		}
		if ( dropdown && key === 'ArrowRight' && controlOf( li ) && inHead ) {
			event.preventDefault();
			setOpen( li, true );
			const first = li.querySelector( ':scope > .dev3mm__dropdown > li' );
			if ( first && headFocusable( first ) ) {
				headFocusable( first ).focus();
			}
			return;
		}
		if ( dropdown && key === 'ArrowLeft' ) {
			const owner = dropdown.closest( '.dev3mm__item' );
			if ( owner && owner.classList.contains( 'dev3mm__item--sub' ) ) {
				event.preventDefault();
				setOpen( owner, false );
				const control = controlOf( owner );
				if ( control ) {
					control.focus();
				}
			}
		}
	} );

	// Tabs.
	function activateTab( tab ) {
		const list = tab.closest( '[role="tablist"]' );
		if ( ! list ) {
			return;
		}
		list.querySelectorAll( '[role="tab"]' ).forEach( ( t ) => {
			const active = t === tab;
			t.classList.toggle( 'is-active', active );
			t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			t.setAttribute( 'tabindex', active ? '0' : '-1' );
			const panel = doc.getElementById( t.getAttribute( 'aria-controls' ) );
			if ( panel ) {
				panel.classList.toggle( 'is-active', active );
			}
		} );
	}

	function tabKeys( event ) {
		const tabs = Array.from( event.target.closest( '[role="tablist"]' ).querySelectorAll( '[role="tab"]' ) );
		const index = tabs.indexOf( event.target );
		let next = -1;
		if ( event.key === 'ArrowDown' || event.key === 'ArrowRight' ) {
			next = ( index + 1 ) % tabs.length;
		} else if ( event.key === 'ArrowUp' || event.key === 'ArrowLeft' ) {
			next = ( index - 1 + tabs.length ) % tabs.length;
		} else if ( event.key === 'Home' ) {
			next = 0;
		} else if ( event.key === 'End' ) {
			next = tabs.length - 1;
		}
		if ( next > -1 ) {
			event.preventDefault();
			tabs[ next ].focus();
			activateTab( tabs[ next ] );
		}
	}

	// Mobile drawer / accordion.
	function setDrawer( open ) {
		if ( ! toggle ) {
			return;
		}
		nav.classList.toggle( 'is-mobile-open', open );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		const offcanvas = nav.classList.contains( 'dev3mm--mobile-offcanvas' );
		doc.documentElement.classList.toggle( 'dev3mm-lock', open && offcanvas && isMobile() );
		if ( open && offcanvas ) {
			const first = Array.from( menu.querySelectorAll( FOCUSABLE ) ).find( visible ) || Array.from( drawer.querySelectorAll( FOCUSABLE ) ).find( visible );
			if ( first ) {
				setTimeout( () => first.focus(), 30 );
			}
		} else if ( ! open ) {
			closeAll();
			if ( offcanvas && drawer.contains( doc.activeElement ) ) {
				toggle.focus();
			}
		}
	}

	function trap( event ) {
		const items = Array.from( drawer.querySelectorAll( FOCUSABLE ) ).filter( visible );
		if ( ! items.length ) {
			return;
		}
		const first = items[ 0 ];
		const last = items[ items.length - 1 ];
		if ( event.shiftKey && doc.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && doc.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	if ( toggle ) {
		on( toggle, 'click', () => setDrawer( ! nav.classList.contains( 'is-mobile-open' ) ) );
	}
	nav.querySelectorAll( '[data-dev3mm-close]' ).forEach( ( el ) => on( el, 'click', () => setDrawer( false ) ) );

	// Sticky.
	let placeholder = null;
	let navTop = 0;
	function stickyCheck() {
		if ( ! opts.sticky ) {
			return;
		}
		const allowed = ! isMobile() || opts.stickyMobile;
		if ( ! nav.classList.contains( 'is-stuck' ) ) {
			navTop = nav.getBoundingClientRect().top + win.scrollY;
		}
		const top = parseFloat( nav.style.getPropertyValue( '--dev3mm-top' ) ) || 0;
		const stick = allowed && win.scrollY + top > navTop && navTop > 0;
		if ( stick && ! nav.classList.contains( 'is-stuck' ) ) {
			placeholder = placeholder || doc.createElement( 'div' );
			placeholder.className = 'dev3mm-placeholder';
			placeholder.style.height = `${ nav.offsetHeight }px`;
			nav.parentNode.insertBefore( placeholder, nav );
			nav.classList.add( 'is-stuck' );
		} else if ( ! stick && nav.classList.contains( 'is-stuck' ) ) {
			nav.classList.remove( 'is-stuck' );
			if ( placeholder && placeholder.parentNode ) {
				placeholder.parentNode.removeChild( placeholder );
			}
		}
	}

	let frame = 0;
	const schedule = () => {
		if ( frame ) {
			return;
		}
		frame = win.requestAnimationFrame( () => {
			frame = 0;
			measure();
			stickyCheck();
		} );
	};
	on( win, 'resize', schedule, { passive: true } );
	if ( opts.sticky ) {
		on( win, 'scroll', schedule, { passive: true } );
	}
	if ( mq ) {
		const reset = () => {
			closeAll();
			nav.classList.remove( 'is-mobile-open' );
			doc.documentElement.classList.remove( 'dev3mm-lock' );
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
		};
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', reset );
			cleanups.push( () => mq.removeEventListener( 'change', reset ) );
		} else if ( mq.addListener ) {
			mq.addListener( reset );
		}
	}

	measure();
	stickyCheck();
	nav.classList.add( 'is-ready' );

	const api = {
		open: ( li ) => setOpen( li, true ),
		close: ( li ) => setOpen( li, false ),
		closeAll,
		setDrawer,
		isMobile,
		destroy: () => {
			cleanups.forEach( ( fn ) => fn() );
			delete nav.dev3mm;
			nav.classList.remove( 'is-ready' );
		},
	};
	nav.dev3mm = api;
	return api;
}

/**
 * Initialises every menu inside a root.
 *
 * @param {ParentNode} root Root (default: document).
 * @return {Array} APIs.
 */
export function initAll( root = document ) {
	return Array.from( root.querySelectorAll( 'nav.dev3mm' ) ).map( initMenu );
}
