import apiFetch from '@wordpress/api-fetch';
import { matchIcon, previewDocument, ICON_NAMES } from '../../src/admin/shared';
import Audit from '../../src/admin/ai/Audit';
import Labels from '../../src/admin/ai/Labels';
import { DraftTree } from '../../src/admin/ai/BuildMenu';
import { ToastProvider } from '../../src/core3/ui';
import { change, click, render } from './render';

afterEach( () => {
	document.body.innerHTML = '';
	apiFetch.mockReset();
	apiFetch.mockImplementation( () => new Promise( () => {} ) );
} );

const MENUS = [ { id: 5, name: 'Main', count: 3, locations: [] } ];

describe( 'admin helpers', () => {
	it( 'matches icons by keyword', () => {
		expect( matchIcon( 'Contact us' ) ).toBe( 'phone' );
		expect( matchIcon( 'Shop' ) ).toBe( 'shop' );
		expect( matchIcon( 'Gift cards' ) ).toBe( 'gift' );
		expect( matchIcon( 'Zzzz' ) ).toBe( '' );
		expect( ICON_NAMES.length ).toBeGreaterThan( 50 );
	} );

	it( 'builds a preview document with the front-end assets and blocks navigation', () => {
		window.dev3mmConfig.frontendCss = '/build/frontend.css';
		window.dev3mmConfig.frontendJs = '/build/frontend.js';
		const doc = previewDocument( '<nav class="dev3mm"></nav>', true );
		expect( doc ).toContain( '/build/frontend.css' );
		expect( doc ).toContain( '/build/frontend.js' );
		expect( doc ).toContain( 'preventDefault' );
		expect( doc ).toContain( '<nav class="dev3mm"></nav>' );
	} );
} );

describe( 'UX audit tool', () => {
	it( 'shows the score and issues, and applies a rename fix', async () => {
		apiFetch.mockImplementation( ( { url, data } ) => {
			if ( url.includes( 'audit' ) ) {
				return Promise.resolve( {
					score: 78,
					grade: 'good',
					counts: { items: 12, top: 5, depth: 2 },
					ai: { used: true, error: null },
					issues: [
						{ id: 'a', severity: 'medium', code: 'unclear_label', item_id: 9, title: '“Stuff” is a vague label', detail: 'Say what it is.', source: 'ai', fix: { type: 'rename', label: 'Accessories', ref: '' } },
						{ id: 'b', severity: 'low', code: 'missing_link', item_id: 0, title: 'No link to the home page', detail: 'x', source: 'rules', fix: { type: 'add_link', label: 'Home', ref: 'home' } },
					],
				} );
			}
			return Promise.resolve( { updated: 1, tree: [], data } );
		} );
		const view = await render(
			<ToastProvider>
				<Audit menus={ MENUS } menuId={ 5 } setMenuId={ () => {} } onUsage={ () => {} } aiReady />
			</ToastProvider>
		);
		await click( view.getByText( 'Audit with DEV3 AI' ).closest( 'button' ) );
		await view.flush();
		expect( view.getByText( '“Stuff” is a vague label' ) ).toBeTruthy();
		expect( view.getByText( 'Accessories' ) ).toBeTruthy();
		expect( view.$( 'svg[role="img"]' ).getAttribute( 'aria-label' ) ).toContain( '78' );
		await click( view.getByText( 'Apply label' ).closest( 'button' ) );
		await view.flush();
		const call = apiFetch.mock.calls.find( ( [ o ] ) => o.url.includes( 'menus/5/items' ) );
		expect( call[ 0 ].data.items ).toEqual( [ { id: 9, title: 'Accessories' } ] );
		expect( view.getByText( 'Fixed' ) ).toBeTruthy();
		await click( view.getByText( 'Add “Home”' ).closest( 'button' ) );
		await view.flush();
		const add = apiFetch.mock.calls.find( ( [ o ] ) => o.url.includes( 'build/apply' ) );
		expect( add[ 0 ].data.target ).toEqual( { mode: 'append', menu_id: 5 } );
		expect( add[ 0 ].data.draft[ 0 ].ref ).toBe( 'home' );
		view.unmount();
	} );

	it( 'disables the AI audit without a key but allows the quick audit', async () => {
		const view = await render(
			<ToastProvider>
				<Audit menus={ MENUS } menuId={ 5 } setMenuId={ () => {} } onUsage={ () => {} } aiReady={ false } />
			</ToastProvider>
		);
		expect( view.getByText( 'Audit with DEV3 AI' ).closest( 'button' ).disabled ).toBe( true );
		expect( view.getByText( 'Quick audit' ).closest( 'button' ).disabled ).toBe( false );
		view.unmount();
	} );
} );

describe( 'labels tool', () => {
	it( 'reviews suggestions and applies only the selected, edited rows', async () => {
		apiFetch.mockImplementation( ( { url } ) => {
			if ( url.includes( 'ai/labels' ) ) {
				return Promise.resolve( {
					language: 'English (en_US)',
					meta: { model: 'gemini-2.5-flash', tokens: 100 },
					items: [
						{ id: 1, title: 'Prods', description: '', depth: 0, label: 'Products', new_description: 'Everything we sell' },
						{ id: 2, title: 'Contact', description: '', depth: 0, label: 'Contact', new_description: '' },
					],
				} );
			}
			return Promise.resolve( { updated: 1 } );
		} );
		const view = await render(
			<ToastProvider>
				<Labels menus={ MENUS } menuId={ 5 } setMenuId={ () => {} } onUsage={ () => {} } aiReady />
			</ToastProvider>
		);
		await click( view.getByText( 'Write labels with DEV3 AI' ).closest( 'button' ) );
		await view.flush();
		expect( view.getByText( 'Not saved yet' ) ).toBeTruthy();
		const inputs = view.$$( 'input[type="text"]' ).filter( ( i ) => i.value === 'Products' );
		await change( inputs[ 0 ], 'Our products' );
		await click( view.getByText( 'Apply 1 selected' ).closest( 'button' ) );
		await view.flush();
		const call = apiFetch.mock.calls.find( ( [ o ] ) => o.url.includes( 'menus/5/items' ) );
		expect( call[ 0 ].data.items ).toHaveLength( 1 );
		expect( call[ 0 ].data.items[ 0 ] ).toMatchObject( { id: 1, title: 'Our products', description: 'Everything we sell' } );
		view.unmount();
	} );
} );

describe( 'build my menu draft tree', () => {
	it( 'edits labels and removes nodes', async () => {
		const onChange = jest.fn();
		const draft = [ { title: 'Shop', ref: 'shop', layout: 'mega', icon: 'shop', children: [ { title: 'Men', ref: 'product_cat:3', layout: 'link', children: [] } ] } ];
		const view = await render( <DraftTree draft={ draft } onChange={ onChange } /> );
		expect( view.getByText( 'Mega panel' ) ).toBeTruthy();
		const men = view.$$( 'input' ).find( ( i ) => i.value === 'Men' );
		await change( men, 'Menswear' );
		expect( onChange.mock.calls[ 0 ][ 0 ][ 0 ].children[ 0 ].title ).toBe( 'Menswear' );
		await click( view.$$( 'button[aria-label="Remove from draft"]' )[ 1 ] );
		expect( onChange.mock.calls[ 1 ][ 0 ][ 0 ].children ).toHaveLength( 0 );
		view.unmount();
	} );
} );
