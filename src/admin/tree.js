/**
 * Pure helpers for the builder's menu tree (unit-tested in tests/js/tree.test.js).
 * Node: { id, title, url, type, object, object_id, description, target, classes, config, children[] }.
 */

let tempId = -1;

/** New temporary (negative) id for unsaved nodes. */
export function nextTempId() {
	tempId -= 1;
	return tempId;
}

export const DEFAULT_CONFIG = {
	type: 'auto',
	icon: '',
	image_id: 0,
	hide_label: false,
	hide_on_mobile: false,
	hide_on_desktop: false,
	show_description: false,
	badge: { text: '', tone: 'new', color: '' },
	columns: 4,
	panel_width: 'inherit',
	rows: [],
	aside: [],
};

/**
 * A new node.
 *
 * @param {Object} fields Fields.
 * @return {Object} Node.
 */
export function makeNode( fields = {} ) {
	return {
		id: nextTempId(),
		title: '',
		url: '#',
		type: 'custom',
		object: 'custom',
		object_id: 0,
		description: '',
		attr_title: '',
		target: '',
		classes: '',
		...fields,
		config: { ...DEFAULT_CONFIG, ...( fields.config || {} ), badge: { ...DEFAULT_CONFIG.badge, ...( ( fields.config || {} ).badge || {} ) } },
		children: fields.children || [],
	};
}

/**
 * Finds a node and its location.
 *
 * @param {Array}  nodes Nodes.
 * @param {number} id    Id.
 * @param {Array}  path  Index path (internal).
 * @return {Object|null} { node, path, parent }.
 */
export function find( nodes, id, path = [], parent = null ) {
	for ( let i = 0; i < nodes.length; i++ ) {
		if ( nodes[ i ].id === id ) {
			return { node: nodes[ i ], path: [ ...path, i ], parent, siblings: nodes, index: i };
		}
		const hit = find( nodes[ i ].children || [], id, [ ...path, i ], nodes[ i ] );
		if ( hit ) {
			return hit;
		}
	}
	return null;
}

/**
 * Immutable map over every node.
 *
 * @param {Array}    nodes Nodes.
 * @param {Function} fn    ( node, depth ) => node.
 * @param {number}   depth Depth.
 * @return {Array} Nodes.
 */
export function mapTree( nodes, fn, depth = 0 ) {
	return nodes.map( ( n ) => {
		const next = fn( n, depth );
		return { ...next, children: mapTree( next.children || [], fn, depth + 1 ) };
	} );
}

/**
 * Updates one node.
 *
 * @param {Array}           nodes Nodes.
 * @param {number}          id    Id.
 * @param {Object|Function} patch Patch or ( node ) => node.
 * @return {Array} Nodes.
 */
export function updateNode( nodes, id, patch ) {
	return mapTree( nodes, ( n ) => ( n.id === id ? ( typeof patch === 'function' ? patch( n ) : { ...n, ...patch } ) : n ) );
}

/**
 * Removes a node (and its children).
 *
 * @param {Array}  nodes Nodes.
 * @param {number} id    Id.
 * @return {Array} Nodes.
 */
export function removeNode( nodes, id ) {
	return nodes.filter( ( n ) => n.id !== id ).map( ( n ) => ( { ...n, children: removeNode( n.children || [], id ) } ) );
}

function clone( nodes ) {
	return JSON.parse( JSON.stringify( nodes ) );
}

function locate( nodes, path ) {
	let list = nodes;
	let parent = null;
	for ( let i = 0; i < path.length - 1; i++ ) {
		parent = list[ path[ i ] ];
		list = parent.children;
	}
	return { list, parent, index: path[ path.length - 1 ] };
}

/**
 * Moves a node up (-1) or down (+1) among its siblings.
 *
 * @param {Array}  nodes Nodes.
 * @param {number} id    Id.
 * @param {number} dir   -1 or 1.
 * @return {Array} Nodes.
 */
export function moveNode( nodes, id, dir ) {
	const hit = find( nodes, id );
	if ( ! hit ) {
		return nodes;
	}
	const out = clone( nodes );
	const { list, index } = locate( out, hit.path );
	const target = index + dir;
	if ( target < 0 || target >= list.length ) {
		return nodes;
	}
	[ list[ index ], list[ target ] ] = [ list[ target ], list[ index ] ];
	return out;
}

/**
 * Makes a node a child of its previous sibling.
 *
 * @param {Array}  nodes    Nodes.
 * @param {number} id       Id.
 * @param {number} maxDepth Max depth (0-based) allowed.
 * @return {Array} Nodes.
 */
export function indentNode( nodes, id, maxDepth = 5 ) {
	const hit = find( nodes, id );
	if ( ! hit || hit.index === 0 || hit.path.length + depthOf( hit.node ) > maxDepth ) {
		return nodes;
	}
	const out = clone( nodes );
	const { list, index } = locate( out, hit.path );
	const [ node ] = list.splice( index, 1 );
	list[ index - 1 ].children = [ ...( list[ index - 1 ].children || [] ), node ];
	return out;
}

/**
 * Moves a node out one level (after its parent).
 *
 * @param {Array}  nodes Nodes.
 * @param {number} id    Id.
 * @return {Array} Nodes.
 */
export function outdentNode( nodes, id ) {
	const hit = find( nodes, id );
	if ( ! hit || hit.path.length < 2 ) {
		return nodes;
	}
	const out = clone( nodes );
	const { list, index } = locate( out, hit.path );
	const [ node ] = list.splice( index, 1 );
	const parentPath = hit.path.slice( 0, -1 );
	const { list: parentList, index: parentIndex } = locate( out, parentPath );
	parentList.splice( parentIndex + 1, 0, node );
	return out;
}

/**
 * Appends nodes at the top level or under a parent.
 *
 * @param {Array}       nodes    Nodes.
 * @param {Array}       add      New nodes.
 * @param {number|null} parentId Parent id or null.
 * @return {Array} Nodes.
 */
export function addNodes( nodes, add, parentId = null ) {
	if ( ! parentId ) {
		return [ ...nodes, ...add ];
	}
	return updateNode( nodes, parentId, ( n ) => ( { ...n, children: [ ...( n.children || [] ), ...add ] } ) );
}

/** Depth below a node (0 = no children). */
export function depthOf( node ) {
	const kids = node.children || [];
	return kids.length ? 1 + Math.max( ...kids.map( depthOf ) ) : 0;
}

/** Total number of nodes. */
export function countNodes( nodes ) {
	return nodes.reduce( ( n, node ) => n + 1 + countNodes( node.children || [] ), 0 );
}

/**
 * Flat list for rendering the structure list.
 *
 * @param {Array}  nodes Nodes.
 * @param {number} depth Depth.
 * @return {Array} [ { node, depth, index, siblings } ].
 */
export function flatten( nodes, depth = 0 ) {
	return nodes.reduce( ( out, node, index ) => [ ...out, { node, depth, index, siblings: nodes.length }, ...flatten( node.children || [], depth + 1 ) ], [] );
}

/**
 * Effective layout label for a top-level node (mirrors Item_Config::layout()).
 *
 * @param {Object} node  Node.
 * @param {number} depth Depth.
 * @return {string} link|flyout|mega|tabs.
 */
export function layoutOf( node, depth = 0 ) {
	const kids = node.children || [];
	const config = node.config || DEFAULT_CONFIG;
	const hasPanel = ( config.rows && config.rows.length ) || ( config.aside && config.aside.length );
	if ( depth > 0 ) {
		return kids.length ? 'flyout' : 'link';
	}
	if ( config.type === 'mega' || config.type === 'tabs' ) {
		return kids.length || hasPanel ? config.type : 'link';
	}
	if ( config.type === 'flyout' ) {
		return kids.length ? 'flyout' : 'link';
	}
	if ( hasPanel || ( kids.length && kids.some( ( k ) => ( k.children || [] ).length ) ) ) {
		return 'mega';
	}
	return kids.length ? 'flyout' : 'link';
}

/**
 * Draft (from Build my menu) helpers: { title, ref, layout, icon, description, children }.
 */
export function draftCount( draft ) {
	return draft.reduce( ( n, d ) => n + 1 + draftCount( d.children || [] ), 0 );
}

export function updateDraft( draft, path, patch ) {
	return draft.map( ( d, i ) => {
		if ( i !== path[ 0 ] ) {
			return d;
		}
		if ( path.length === 1 ) {
			return patch === null ? null : { ...d, ...patch };
		}
		return { ...d, children: updateDraft( d.children || [], path.slice( 1 ), patch ) };
	} ).filter( Boolean );
}
