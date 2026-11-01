import { controlOf, initMenu } from '../../src/frontend/menu';

function setup( opts = {}, mobile = false ) {
	window.matchMedia = jest.fn( () => ( { matches: mobile, addEventListener: jest.fn(), removeEventListener: jest.fn() } ) );
	document.body.innerHTML = `
	<nav id="m" class="dev3mm dev3mm--mobile-${ opts.mobile || 'accordion' }" aria-label="Main" data-dev3mm='${ JSON.stringify( { trigger: 'click', bp: 960, delay: 0, ...opts } ) }'>
		<div class="dev3mm__bar"><button type="button" class="dev3mm__toggle" aria-expanded="false" aria-controls="d">Menu</button></div>
		<div class="dev3mm__overlay" data-dev3mm-close></div>
		<div class="dev3mm__drawer" id="d">
			<div class="dev3mm__drawer-head"><button type="button" class="dev3mm__close" data-dev3mm-close>x</button></div>
			<ul class="dev3mm__menu">
				<li class="dev3mm__item dev3mm__item--top dev3mm__item--mega" id="shop">
					<div class="dev3mm__head"><a class="dev3mm__link" href="/shop">Shop</a><button type="button" class="dev3mm__sub-toggle" aria-expanded="false" aria-controls="p1">v</button></div>
					<div class="dev3mm__panel" id="p1"><div class="dev3mm__group"><a class="dev3mm__group-title" href="/men" id="men">Men</a></div></div>
				</li>
				<li class="dev3mm__item dev3mm__item--top dev3mm__item--flyout" id="about">
					<div class="dev3mm__head"><button type="button" class="dev3mm__link dev3mm__link--button" aria-expanded="false" aria-controls="p2">About</button></div>
					<ul class="dev3mm__panel dev3mm__dropdown" id="p2">
						<li class="dev3mm__item dev3mm__item--sub" id="team"><div class="dev3mm__head"><a class="dev3mm__sublink" href="/team">Team</a></div></li>
						<li class="dev3mm__item dev3mm__item--sub" id="jobs"><div class="dev3mm__head"><a class="dev3mm__sublink" href="/jobs">Jobs</a></div></li>
					</ul>
				</li>
				<li class="dev3mm__item dev3mm__item--top dev3mm__item--tabs" id="tabsitem">
					<div class="dev3mm__head"><a class="dev3mm__link" href="/c">Catalog</a><button type="button" class="dev3mm__sub-toggle" aria-expanded="false" aria-controls="p3">v</button></div>
					<div class="dev3mm__panel" id="p3"><div class="dev3mm__tablist" role="tablist">
						<button role="tab" id="t0" class="is-active" aria-selected="true" aria-controls="t0p" tabindex="0">A</button>
						<button role="tab" id="t1" aria-selected="false" aria-controls="t1p" tabindex="-1">B</button>
					</div><div id="t0p" class="dev3mm__tabpanel is-active"></div><div id="t1p" class="dev3mm__tabpanel"></div></div>
				</li>
				<li class="dev3mm__item dev3mm__item--top" id="contact"><div class="dev3mm__head"><a class="dev3mm__link" href="/contact">Contact</a></div></li>
			</ul>
		</div>
	</nav>`;
	const nav = document.getElementById( 'm' );
	// jsdom has no layout: treat everything as visible.
	Object.defineProperty( window.HTMLElement.prototype, 'offsetWidth', { configurable: true, get: () => 10 } );
	return { nav, api: initMenu( nav ) };
}

const $ = ( sel ) => document.querySelector( sel );
const press = ( el, key, extra = {} ) => el.dispatchEvent( new window.KeyboardEvent( 'keydown', { key, bubbles: true, ...extra } ) );

afterEach( () => {
	document.body.innerHTML = '';
	document.documentElement.className = '';
} );

describe( 'front-end mega menu', () => {
	it( 'marks itself ready and is idempotent', () => {
		const { nav, api } = setup();
		expect( nav.classList.contains( 'is-ready' ) ).toBe( true );
		expect( initMenu( nav ) ).toBe( api );
	} );

	it( 'toggles a submenu with its button and keeps aria-expanded in sync', () => {
		setup();
		const btn = $( '#shop .dev3mm__sub-toggle' );
		btn.click();
		expect( $( '#shop' ).classList.contains( 'is-open' ) ).toBe( true );
		expect( btn.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		btn.click();
		expect( btn.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'opens one top-level panel at a time on desktop', () => {
		setup();
		$( '#shop .dev3mm__sub-toggle' ).click();
		$( '#about .dev3mm__link--button' ).click();
		expect( $( '#shop' ).classList.contains( 'is-open' ) ).toBe( false );
		expect( $( '#about' ).classList.contains( 'is-open' ) ).toBe( true );
	} );

	it( 'Escape closes the open panel and returns focus to its control', () => {
		setup();
		const btn = $( '#shop .dev3mm__sub-toggle' );
		btn.click();
		$( '#men' ).focus();
		press( $( '#men' ), 'Escape' );
		expect( $( '#shop' ).classList.contains( 'is-open' ) ).toBe( false );
		expect( document.activeElement ).toBe( btn );
	} );

	it( 'arrow keys move between top-level items and Down opens a panel', () => {
		setup();
		const shop = $( '#shop .dev3mm__link' );
		shop.focus();
		press( shop, 'ArrowRight' );
		expect( document.activeElement ).toBe( $( '#about .dev3mm__link--button' ) );
		press( document.activeElement, 'End' );
		expect( document.activeElement ).toBe( $( '#contact .dev3mm__link' ) );
		press( document.activeElement, 'ArrowRight' );
		expect( document.activeElement ).toBe( shop );
		press( shop, 'ArrowLeft' );
		expect( document.activeElement ).toBe( $( '#contact .dev3mm__link' ) );
		$( '#about .dev3mm__link--button' ).focus();
		press( document.activeElement, 'ArrowDown' );
		expect( $( '#about' ).classList.contains( 'is-open' ) ).toBe( true );
		expect( document.activeElement ).toBe( $( '#team a' ) );
	} );

	it( 'Up/Down move inside a flyout', () => {
		setup();
		$( '#about .dev3mm__link--button' ).click();
		$( '#team a' ).focus();
		press( $( '#team a' ), 'ArrowDown' );
		expect( document.activeElement ).toBe( $( '#jobs a' ) );
		press( document.activeElement, 'ArrowDown' );
		expect( document.activeElement ).toBe( $( '#team a' ) );
	} );

	it( 'tabs follow the ARIA tabs pattern', () => {
		setup();
		const t0 = $( '#t0' );
		t0.focus();
		press( t0, 'ArrowDown' );
		expect( $( '#t1' ).getAttribute( 'aria-selected' ) ).toBe( 'true' );
		expect( $( '#t1' ).getAttribute( 'tabindex' ) ).toBe( '0' );
		expect( t0.getAttribute( 'tabindex' ) ).toBe( '-1' );
		expect( $( '#t1p' ).classList.contains( 'is-active' ) ).toBe( true );
		expect( $( '#t0p' ).classList.contains( 'is-active' ) ).toBe( false );
	} );

	it( 'closes panels on outside click', () => {
		setup();
		$( '#shop .dev3mm__sub-toggle' ).click();
		document.body.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		expect( $( '#shop' ).classList.contains( 'is-open' ) ).toBe( false );
	} );

	it( 'opens the off-canvas drawer on mobile, locks scroll and closes with Escape', () => {
		const { nav } = setup( { mobile: 'offcanvas' }, true );
		const toggle = $( '.dev3mm__toggle' );
		toggle.click();
		expect( nav.classList.contains( 'is-mobile-open' ) ).toBe( true );
		expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( document.documentElement.classList.contains( 'dev3mm-lock' ) ).toBe( true );
		press( $( '#shop .dev3mm__link' ), 'Escape' );
		expect( nav.classList.contains( 'is-mobile-open' ) ).toBe( false );
		expect( document.documentElement.classList.contains( 'dev3mm-lock' ) ).toBe( false );
	} );

	it( 'keeps several accordion sections open on mobile', () => {
		setup( {}, true );
		$( '#shop .dev3mm__sub-toggle' ).click();
		$( '#about .dev3mm__link--button' ).click();
		expect( $( '#shop' ).classList.contains( 'is-open' ) ).toBe( true );
		expect( $( '#about' ).classList.contains( 'is-open' ) ).toBe( true );
	} );

	it( 'opens on mouse hover after the delay (hover trigger)', () => {
		jest.useFakeTimers();
		setup( { trigger: 'hover', delay: 100 } );
		const li = $( '#shop' );
		const ev = new window.Event( 'pointerenter' );
		ev.pointerType = 'mouse';
		li.dispatchEvent( ev );
		expect( li.classList.contains( 'is-open' ) ).toBe( false );
		jest.advanceTimersByTime( 120 );
		expect( li.classList.contains( 'is-open' ) ).toBe( true );
		const touch = new window.Event( 'pointerleave' );
		touch.pointerType = 'touch';
		li.dispatchEvent( touch );
		jest.advanceTimersByTime( 500 );
		expect( li.classList.contains( 'is-open' ) ).toBe( true );
		jest.useRealTimers();
	} );

	it( 'controlOf finds the control in the item head', () => {
		setup();
		expect( controlOf( $( '#about' ) ) ).toBe( $( '#about .dev3mm__link--button' ) );
		expect( controlOf( $( '#contact' ) ) ).toBeNull();
	} );
} );
