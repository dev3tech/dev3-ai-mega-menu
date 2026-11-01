import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import config from '../core3/config';
import { Button, Checkbox, EmptyState, Modal, Select, Skeleton, Tabs, TextField } from '../core3/ui';
import { makeNode } from './tree';

const SOURCES = [
	{ id: 'page', label: __( 'Pages', 'dev3-ai-mega-menu' ) },
	{ id: 'post', label: __( 'Posts', 'dev3-ai-mega-menu' ) },
	{ id: 'category', label: __( 'Categories', 'dev3-ai-mega-menu' ) },
	{ id: 'product_cat', label: __( 'Product categories', 'dev3-ai-mega-menu' ), woo: true },
	{ id: 'product', label: __( 'Products', 'dev3-ai-mega-menu' ), woo: true },
	{ id: 'custom', label: __( 'Custom link', 'dev3-ai-mega-menu' ) },
];

/**
 * "Add items" modal: pick pages, posts, categories, products, or a custom link.
 *
 * @param {Object}   props         Props.
 * @param {boolean}  props.open    Open.
 * @param {Function} props.onClose Close.
 * @param {Function} props.onAdd   ( nodes, parentId ) => void.
 * @param {Array}    props.parents Possible parents [ { id, title } ].
 * @param {number}   props.parent  Default parent id.
 * @return {Element} Modal.
 */
export default function AddItems( { open, onClose, onAdd, parents = [], parent = 0 } ) {
	const sources = SOURCES.filter( ( s ) => ! s.woo || config.woo );
	const [ tab, setTab ] = useState( 'page' );
	const [ search, setSearch ] = useState( '' );
	const [ rows, setRows ] = useState( null );
	const [ picked, setPicked ] = useState( {} );
	const [ under, setUnder ] = useState( parent || 0 );
	const [ custom, setCustom ] = useState( { title: '', url: '' } );

	useEffect( () => setUnder( parent || 0 ), [ parent, open ] );

	useEffect( () => {
		if ( ! open || tab === 'custom' ) {
			return;
		}
		setRows( null );
		const t = setTimeout( () => {
			request( `content?type=${ tab }&per_page=40&search=${ encodeURIComponent( search ) }` )
				.then( setRows )
				.catch( () => setRows( [] ) );
		}, 250 );
		return () => clearTimeout( t );
	}, [ tab, search, open ] );

	const key = ( r ) => `${ r.object }:${ r.id }`;
	const selected = Object.values( picked );

	const add = () => {
		let nodes;
		if ( tab === 'custom' ) {
			if ( ! custom.title.trim() ) {
				return;
			}
			nodes = [ makeNode( { title: custom.title.trim(), url: custom.url.trim() || '#', type: 'custom', object: 'custom' } ) ];
		} else {
			nodes = selected.map( ( r ) => makeNode( { title: r.title, url: r.url, type: r.type, object: r.object, object_id: r.id } ) );
		}
		if ( ! nodes.length ) {
			return;
		}
		onAdd( nodes, Number( under ) || null );
		setPicked( {} );
		setCustom( { title: '', url: '' } );
		onClose();
	};

	return (
		<Modal
			open={ open }
			onClose={ onClose }
			size="lg"
			title={ __( 'Add menu items', 'dev3-ai-mega-menu' ) }
			description={ __( 'Pick content from your site or add a custom link.', 'dev3-ai-mega-menu' ) }
			footer={
				<>
					<Button variant="ghost" onClick={ onClose }>
						{ __( 'Cancel', 'dev3-ai-mega-menu' ) }
					</Button>
					<Button variant="primary" icon="plus" onClick={ add } disabled={ tab === 'custom' ? ! custom.title.trim() : ! selected.length }>
						{ tab === 'custom' || selected.length < 2 ? __( 'Add to menu', 'dev3-ai-mega-menu' ) : sprintf( /* translators: %d: count */ __( 'Add %d items', 'dev3-ai-mega-menu' ), selected.length ) }
					</Button>
				</>
			}
		>
			<Select
				label={ __( 'Add under', 'dev3-ai-mega-menu' ) }
				value={ under }
				onChange={ ( v ) => setUnder( Number( v ) ) }
				options={ [ { value: 0, label: __( 'Top level', 'dev3-ai-mega-menu' ) }, ...parents.map( ( p ) => ( { value: p.id, label: p.label } ) ) ] }
			/>
			<Tabs tabs={ sources } value={ tab } onChange={ setTab } label={ __( 'Content type', 'dev3-ai-mega-menu' ) } variant="pills" />
			{ tab === 'custom' ? (
				<div className="dev3mm-stack">
					<TextField label={ __( 'Label', 'dev3-ai-mega-menu' ) } value={ custom.title } onChange={ ( v ) => setCustom( { ...custom, title: v } ) } required maxLength={ 120 } />
					<TextField label={ __( 'URL', 'dev3-ai-mega-menu' ) } value={ custom.url } onChange={ ( v ) => setCustom( { ...custom, url: v } ) } placeholder="https://" help={ __( 'Leave empty (or #) for a heading that only opens its submenu.', 'dev3-ai-mega-menu' ) } />
				</div>
			) : (
				<>
					<TextField label={ __( 'Search', 'dev3-ai-mega-menu' ) } hideLabel value={ search } onChange={ setSearch } placeholder={ __( 'Search…', 'dev3-ai-mega-menu' ) } type="search" />
					<div className="dev3mm-picklist">
						{ rows === null && <Skeleton lines={ 6 } /> }
						{ rows && ! rows.length && <EmptyState compact icon="search" title={ __( 'Nothing found', 'dev3-ai-mega-menu' ) } /> }
						{ rows &&
							rows.map( ( r ) => (
								<Checkbox
									key={ key( r ) }
									label={ r.title || __( '(no title)', 'dev3-ai-mega-menu' ) }
									checked={ !! picked[ key( r ) ] }
									onChange={ ( on ) => {
										const next = { ...picked };
										if ( on ) {
											next[ key( r ) ] = r;
										} else {
											delete next[ key( r ) ];
										}
										setPicked( next );
									} }
								/>
							) ) }
					</div>
				</>
			) }
		</Modal>
	);
}
