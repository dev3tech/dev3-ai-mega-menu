import { addNodes, countNodes, find, flatten, indentNode, layoutOf, makeNode, moveNode, outdentNode, removeNode, updateDraft, updateNode } from '../../src/admin/tree';

const build = () => [
	makeNode( { id: 1, title: 'Shop', children: [ makeNode( { id: 11, title: 'Men', children: [ makeNode( { id: 111, title: 'Shirts' } ) ] } ), makeNode( { id: 12, title: 'Women' } ) ] } ),
	makeNode( { id: 2, title: 'About' } ),
	makeNode( { id: 3, title: 'Contact' } ),
];

describe( 'menu tree helpers', () => {
	it( 'finds nodes with their path', () => {
		const hit = find( build(), 111 );
		expect( hit.node.title ).toBe( 'Shirts' );
		expect( hit.path ).toEqual( [ 0, 0, 0 ] );
		expect( find( build(), 999 ) ).toBeNull();
	} );

	it( 'moves, indents and outdents immutably', () => {
		const tree = build();
		const moved = moveNode( tree, 3, -1 );
		expect( moved.map( ( n ) => n.id ) ).toEqual( [ 1, 3, 2 ] );
		expect( tree.map( ( n ) => n.id ) ).toEqual( [ 1, 2, 3 ] );
		expect( moveNode( tree, 1, -1 ) ).toBe( tree );

		const indented = indentNode( tree, 2 );
		expect( indented.map( ( n ) => n.id ) ).toEqual( [ 1, 3 ] );
		expect( indented[ 0 ].children.map( ( n ) => n.id ) ).toEqual( [ 11, 12, 2 ] );
		expect( indentNode( tree, 1 ) ).toBe( tree );

		const out = outdentNode( tree, 11 );
		expect( out.map( ( n ) => n.id ) ).toEqual( [ 1, 11, 2, 3 ] );
		expect( out[ 1 ].children[ 0 ].id ).toBe( 111 );
	} );

	it( 'respects the max depth when indenting', () => {
		const tree = build();
		// 12 is at depth 1; indenting it under 11 makes depth 2: allowed at max 2, refused at max 1.
		expect( indentNode( tree, 12, 1 ) ).toBe( tree );
		expect( find( indentNode( tree, 12, 2 ), 12 ).path ).toEqual( [ 0, 0, 1 ] );
	} );

	it( 'adds, updates, removes and counts', () => {
		let tree = addNodes( build(), [ makeNode( { title: 'Blog' } ) ] );
		expect( countNodes( tree ) ).toBe( 7 );
		tree = addNodes( tree, [ makeNode( { title: 'Kids' } ) ], 1 );
		expect( find( tree, 1 ).node.children ).toHaveLength( 3 );
		tree = updateNode( tree, 12, { title: 'Ladies' } );
		expect( find( tree, 12 ).node.title ).toBe( 'Ladies' );
		tree = removeNode( tree, 1 );
		expect( countNodes( tree ) ).toBe( 3 );
		expect( flatten( build() ).map( ( r ) => r.depth ) ).toEqual( [ 0, 1, 2, 1, 0, 0 ] );
	} );

	it( 'new nodes get unique negative ids and default config', () => {
		const a = makeNode();
		const b = makeNode();
		expect( a.id ).toBeLessThan( 0 );
		expect( a.id ).not.toBe( b.id );
		expect( a.config.badge.tone ).toBe( 'new' );
	} );

	it( 'derives the layout like the PHP renderer', () => {
		const tree = build();
		expect( layoutOf( tree[ 0 ], 0 ) ).toBe( 'mega' ); // auto + grandchildren
		expect( layoutOf( tree[ 1 ], 0 ) ).toBe( 'link' );
		expect( layoutOf( { ...tree[ 0 ], config: { ...tree[ 0 ].config, type: 'flyout' } }, 0 ) ).toBe( 'flyout' );
		expect( layoutOf( { ...tree[ 0 ], config: { ...tree[ 0 ].config, type: 'tabs' } }, 0 ) ).toBe( 'tabs' );
		expect( layoutOf( { ...tree[ 1 ], config: { ...tree[ 1 ].config, aside: [ { type: 'promo' } ] } }, 0 ) ).toBe( 'mega' );
		expect( layoutOf( tree[ 0 ].children[ 0 ], 1 ) ).toBe( 'flyout' );
	} );

	it( 'edits and prunes AI drafts by path', () => {
		const draft = [ { title: 'Shop', ref: 'shop', children: [ { title: 'Men', ref: 'product_cat:1', children: [] } ] } ];
		expect( updateDraft( draft, [ 0, 0 ], { title: 'Mens' } )[ 0 ].children[ 0 ].title ).toBe( 'Mens' );
		expect( updateDraft( draft, [ 0, 0 ], null )[ 0 ].children ).toHaveLength( 0 );
		expect( updateDraft( draft, [ 0 ], null ) ).toHaveLength( 0 );
	} );
} );
