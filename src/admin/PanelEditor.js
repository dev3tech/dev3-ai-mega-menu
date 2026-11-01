import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import config from '../core3/config';
import { Badge, Button, Checkbox, EmptyState, FormGrid, IconButton, NumberField, Select, Textarea, TextField } from '../core3/ui';
import { MediaPicker } from './shared';

export const WIDGET_TYPES = [
	{ value: 'item', label: __( 'Menu column (a child item + its links)', 'dev3-ai-mega-menu' ) },
	{ value: 'posts', label: __( 'Recent posts', 'dev3-ai-mega-menu' ) },
	{ value: 'products', label: __( 'Products', 'dev3-ai-mega-menu' ), woo: true },
	{ value: 'search', label: __( 'Search box', 'dev3-ai-mega-menu' ) },
	{ value: 'image', label: __( 'Image', 'dev3-ai-mega-menu' ) },
	{ value: 'promo', label: __( 'Promo block', 'dev3-ai-mega-menu' ) },
	{ value: 'html', label: __( 'HTML', 'dev3-ai-mega-menu' ) },
	{ value: 'shortcode', label: __( 'Shortcode', 'dev3-ai-mega-menu' ) },
	{ value: 'block', label: __( 'Synced pattern (reusable block)', 'dev3-ai-mega-menu' ) },
];

const TYPE_LABEL = Object.fromEntries( WIDGET_TYPES.map( ( t ) => [ t.value, t.label.split( ' (' )[ 0 ] ] ) );

export function newWidget( type, children = [] ) {
	const base = { type, title: '' };
	switch ( type ) {
		case 'item':
			return { type, item_id: children[ 0 ] ? children[ 0 ].id : 0 };
		case 'posts':
			return { ...base, title: __( 'From the blog', 'dev3-ai-mega-menu' ), category: 0, count: 3, show_image: true };
		case 'products':
			return { ...base, title: __( 'Popular now', 'dev3-ai-mega-menu' ), source: 'best', category: 0, ids: [], count: 3 };
		case 'search':
			return { ...base, placeholder: '', post_type: 'any' };
		case 'image':
			return { ...base, image_id: 0, link: '', caption: '' };
		case 'promo':
			return { ...base, title: __( 'Seasonal picks', 'dev3-ai-mega-menu' ), text: '', badge: __( 'New', 'dev3-ai-mega-menu' ), cta_label: __( 'Shop now', 'dev3-ai-mega-menu' ), cta_url: '', image_id: 0, product_ids: [], tone: 'brand' };
		case 'block':
			return { ...base, block_id: 0 };
		default:
			return { ...base, content: '' };
	}
}

/** Loads options for a content type once. */
function useOptions( type ) {
	const [ rows, setRows ] = useState( [] );
	useEffect( () => {
		if ( ! type ) {
			return;
		}
		request( `content?type=${ type }&per_page=50` )
			.then( setRows )
			.catch( () => setRows( [] ) );
	}, [ type ] );
	return rows;
}

function WidgetFields( { widget, onChange, children } ) {
	const set = ( patch ) => onChange( { ...widget, ...patch } );
	const cats = useOptions( widget.type === 'posts' ? 'category' : widget.type === 'products' && config.woo ? 'product_cat' : null );
	const blocks = useOptions( widget.type === 'block' ? 'wp_block' : null );
	const products = useOptions( ( widget.type === 'products' && widget.source === 'ids' ) || widget.type === 'promo' ? ( config.woo ? 'product' : null ) : null );
	const title = widget.type !== 'item' && (
		<TextField label={ widget.type === 'promo' ? __( 'Headline', 'dev3-ai-mega-menu' ) : __( 'Title (optional)', 'dev3-ai-mega-menu' ) } value={ widget.title || '' } onChange={ ( v ) => set( { title: v } ) } maxLength={ 80 } />
	);
	const productPicker = ( key, max ) => (
		<div className="dev3mm-field-block">
			<span className="dev3mm-ui-label">{ __( 'Products', 'dev3-ai-mega-menu' ) }</span>
			<div className="dev3mm-chips">
				{ products.slice( 0, 30 ).map( ( p ) => {
					const on = ( widget[ key ] || [] ).includes( p.id );
					return (
						<button
							key={ p.id }
							type="button"
							className={ `dev3mm-chip${ on ? ' is-on' : '' }` }
							aria-pressed={ on }
							onClick={ () => {
								const list = widget[ key ] || [];
								set( { [ key ]: on ? list.filter( ( id ) => id !== p.id ) : [ ...list, p.id ].slice( 0, max ) } );
							} }
						>
							{ p.title }
						</button>
					);
				} ) }
				{ ! products.length && <span className="dev3mm-muted">{ __( 'No products found.', 'dev3-ai-mega-menu' ) }</span> }
			</div>
		</div>
	);
	switch ( widget.type ) {
		case 'item':
			return (
				<Select
					label={ __( 'Child item', 'dev3-ai-mega-menu' ) }
					value={ widget.item_id }
					onChange={ ( v ) => set( { item_id: Number( v ) } ) }
					options={ children.map( ( c ) => ( { value: c.id, label: c.title || __( '(no label)', 'dev3-ai-mega-menu' ) } ) ) }
					placeholder={ children.length ? undefined : __( 'Add child items first', 'dev3-ai-mega-menu' ) }
					help={ __( 'Shows the item as a column heading with its own sub-items below.', 'dev3-ai-mega-menu' ) }
				/>
			);
		case 'posts':
			return (
				<FormGrid>
					{ title }
					<Select label={ __( 'Category', 'dev3-ai-mega-menu' ) } value={ widget.category } onChange={ ( v ) => set( { category: Number( v ) } ) } options={ [ { value: 0, label: __( 'All categories', 'dev3-ai-mega-menu' ) }, ...cats.map( ( c ) => ( { value: c.id, label: c.title } ) ) ] } />
					<NumberField label={ __( 'Number of posts', 'dev3-ai-mega-menu' ) } value={ widget.count } min={ 1 } max={ 8 } onChange={ ( v ) => set( { count: v || 1 } ) } />
					<Checkbox label={ __( 'Show featured images', 'dev3-ai-mega-menu' ) } checked={ !! widget.show_image } onChange={ ( v ) => set( { show_image: v } ) } />
				</FormGrid>
			);
		case 'products':
			return (
				<FormGrid>
					{ title }
					<Select
						label={ __( 'Show', 'dev3-ai-mega-menu' ) }
						value={ widget.source }
						onChange={ ( v ) => set( { source: v } ) }
						options={ [
							{ value: 'best', label: __( 'Best sellers', 'dev3-ai-mega-menu' ) },
							{ value: 'recent', label: __( 'Newest', 'dev3-ai-mega-menu' ) },
							{ value: 'featured', label: __( 'Featured', 'dev3-ai-mega-menu' ) },
							{ value: 'sale', label: __( 'On sale', 'dev3-ai-mega-menu' ) },
							{ value: 'category', label: __( 'From a category', 'dev3-ai-mega-menu' ) },
							{ value: 'ids', label: __( 'Hand-picked', 'dev3-ai-mega-menu' ) },
						] }
					/>
					{ widget.source === 'category' && <Select label={ __( 'Product category', 'dev3-ai-mega-menu' ) } value={ widget.category } onChange={ ( v ) => set( { category: Number( v ) } ) } options={ cats.map( ( c ) => ( { value: c.id, label: c.title } ) ) } placeholder={ __( 'Choose…', 'dev3-ai-mega-menu' ) } /> }
					<NumberField label={ __( 'Number of products', 'dev3-ai-mega-menu' ) } value={ widget.count } min={ 1 } max={ 8 } onChange={ ( v ) => set( { count: v || 1 } ) } />
					{ widget.source === 'ids' && productPicker( 'ids', 8 ) }
				</FormGrid>
			);
		case 'search':
			return (
				<FormGrid>
					{ title }
					<TextField label={ __( 'Placeholder', 'dev3-ai-mega-menu' ) } value={ widget.placeholder } onChange={ ( v ) => set( { placeholder: v } ) } maxLength={ 60 } />
					<Select
						label={ __( 'Search in', 'dev3-ai-mega-menu' ) }
						value={ widget.post_type }
						onChange={ ( v ) => set( { post_type: v } ) }
						options={ [
							{ value: 'any', label: __( 'Everything', 'dev3-ai-mega-menu' ) },
							{ value: 'post', label: __( 'Posts', 'dev3-ai-mega-menu' ) },
							{ value: 'page', label: __( 'Pages', 'dev3-ai-mega-menu' ) },
							...( config.woo ? [ { value: 'product', label: __( 'Products', 'dev3-ai-mega-menu' ) } ] : [] ),
						] }
					/>
				</FormGrid>
			);
		case 'image':
			return (
				<FormGrid>
					{ title }
					<MediaPicker value={ widget.image_id } onChange={ ( id ) => set( { image_id: id } ) } />
					<TextField label={ __( 'Link', 'dev3-ai-mega-menu' ) } value={ widget.link } onChange={ ( v ) => set( { link: v } ) } placeholder="https://" />
					<TextField label={ __( 'Caption', 'dev3-ai-mega-menu' ) } value={ widget.caption } onChange={ ( v ) => set( { caption: v } ) } maxLength={ 120 } />
				</FormGrid>
			);
		case 'promo':
			return (
				<FormGrid>
					{ title }
					<TextField label={ __( 'Badge', 'dev3-ai-mega-menu' ) } value={ widget.badge } onChange={ ( v ) => set( { badge: v } ) } maxLength={ 24 } />
					<Textarea label={ __( 'Text', 'dev3-ai-mega-menu' ) } value={ widget.text } onChange={ ( v ) => set( { text: v } ) } maxLength={ 300 } rows={ 2 } />
					<TextField label={ __( 'Button label', 'dev3-ai-mega-menu' ) } value={ widget.cta_label } onChange={ ( v ) => set( { cta_label: v } ) } maxLength={ 40 } />
					<TextField label={ __( 'Button link', 'dev3-ai-mega-menu' ) } value={ widget.cta_url } onChange={ ( v ) => set( { cta_url: v } ) } placeholder="https://" />
					<Select
						label={ __( 'Colour', 'dev3-ai-mega-menu' ) }
						value={ widget.tone }
						onChange={ ( v ) => set( { tone: v } ) }
						options={ [
							{ value: 'brand', label: __( 'Accent gradient', 'dev3-ai-mega-menu' ) },
							{ value: 'light', label: __( 'Light tint', 'dev3-ai-mega-menu' ) },
							{ value: 'dark', label: __( 'Dark', 'dev3-ai-mega-menu' ) },
							{ value: 'accent', label: __( 'Vivid', 'dev3-ai-mega-menu' ) },
						] }
					/>
					<MediaPicker value={ widget.image_id } onChange={ ( id ) => set( { image_id: id } ) } />
					{ config.woo && productPicker( 'product_ids', 3 ) }
				</FormGrid>
			);
		case 'block':
			return (
				<FormGrid>
					{ title }
					<Select label={ __( 'Synced pattern', 'dev3-ai-mega-menu' ) } value={ widget.block_id } onChange={ ( v ) => set( { block_id: Number( v ) } ) } options={ blocks.map( ( b ) => ( { value: b.id, label: b.title } ) ) } placeholder={ blocks.length ? __( 'Choose…', 'dev3-ai-mega-menu' ) : __( 'No synced patterns yet', 'dev3-ai-mega-menu' ) } />
				</FormGrid>
			);
		default:
			return (
				<FormGrid>
					{ title }
					<Textarea
						label={ widget.type === 'shortcode' ? __( 'Shortcode', 'dev3-ai-mega-menu' ) : __( 'HTML', 'dev3-ai-mega-menu' ) }
						value={ widget.content }
						onChange={ ( v ) => set( { content: v } ) }
						rows={ 4 }
						maxLength={ widget.type === 'shortcode' ? 1000 : 5000 }
						placeholder={ widget.type === 'shortcode' ? '[contact-form-7 id="12"]' : '<p>…</p>' }
					/>
				</FormGrid>
			);
	}
}

/**
 * One widget card (collapsible).
 *
 * @param {Object} props Props.
 * @return {Element} Card.
 */
export function WidgetCard( { widget, onChange, onRemove, onMove, children, first, last } ) {
	const [ open, setOpen ] = useState( false );
	const child = widget.type === 'item' ? children.find( ( c ) => c.id === widget.item_id ) : null;
	const summary = child ? child.title : widget.title || widget.content || '';
	return (
		<div className="dev3mm-widget">
			<div className="dev3mm-widget__head">
				<button type="button" className="dev3mm-widget__toggle" aria-expanded={ open } onClick={ () => setOpen( ! open ) }>
					<Badge tone={ widget.type === 'item' ? 'brand' : 'neutral' }>{ TYPE_LABEL[ widget.type ] }</Badge>
					<span className="dev3mm-widget__summary">{ summary }</span>
				</button>
				<div className="dev3mm-widget__actions">
					<IconButton icon="chevronDown" label={ __( 'Move up', 'dev3-ai-mega-menu' ) } size="sm" onClick={ () => onMove( -1 ) } disabled={ first } className="dev3mm-flip" />
					<IconButton icon="chevronDown" label={ __( 'Move down', 'dev3-ai-mega-menu' ) } size="sm" onClick={ () => onMove( 1 ) } disabled={ last } />
					<IconButton icon="trash" label={ __( 'Remove widget', 'dev3-ai-mega-menu' ) } size="sm" onClick={ onRemove } />
				</div>
			</div>
			{ open && (
				<div className="dev3mm-widget__body">
					<WidgetFields widget={ widget } onChange={ onChange } children={ children } />
				</div>
			) }
		</div>
	);
}

function AddWidget( { onAdd, children, allowItem = true } ) {
	const [ type, setType ] = useState( '' );
	const types = WIDGET_TYPES.filter( ( t ) => ( ! t.woo || config.woo ) && ( allowItem || t.value !== 'item' ) );
	return (
		<div className="dev3mm-addwidget">
			<Select label={ __( 'Add widget', 'dev3-ai-mega-menu' ) } hideLabel value={ type } onChange={ setType } options={ types } placeholder={ __( 'Add a widget…', 'dev3-ai-mega-menu' ) } />
			<Button
				size="sm"
				icon="plus"
				disabled={ ! type }
				onClick={ () => {
					onAdd( newWidget( type, children ) );
					setType( '' );
				} }
			>
				{ __( 'Add', 'dev3-ai-mega-menu' ) }
			</Button>
		</div>
	);
}

function moveIn( list, index, dir ) {
	const next = [ ...list ];
	const target = index + dir;
	if ( target < 0 || target >= next.length ) {
		return list;
	}
	[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
	return next;
}

/**
 * Panel builder: rows → columns (12-grid span) → widgets, plus the side column.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.config   Item config.
 * @param {Function} props.onChange ( patch ) => void.
 * @param {Array}    props.children Child nodes.
 * @return {Element} Editor.
 */
export default function PanelEditor( { config: item, onChange, children } ) {
	const rows = item.rows || [];
	const aside = item.aside || [];
	const setRows = ( next ) => onChange( { rows: next } );
	const setCol = ( r, c, patch ) => setRows( rows.map( ( row, ri ) => ( ri !== r ? row : { cols: row.cols.map( ( col, ci ) => ( ci !== c ? col : { ...col, ...patch } ) ) } ) ) );
	const placed = new Set();
	rows.forEach( ( row ) => row.cols.forEach( ( col ) => col.widgets.forEach( ( w ) => w.type === 'item' && placed.add( w.item_id ) ) ) );
	const unplaced = children.filter( ( c ) => ! placed.has( c.id ) );

	const addRow = () => {
		const perCol = Math.max( 1, Math.ceil( unplaced.length / 3 ) );
		const cols = [ 0, 1, 2 ].map( ( i ) => ( { span: 4, widgets: unplaced.slice( i * perCol, ( i + 1 ) * perCol ).map( ( c ) => ( { type: 'item', item_id: c.id } ) ) } ) );
		setRows( [ ...rows, { cols } ] );
	};

	return (
		<div className="dev3mm-panel-editor">
			{ ! rows.length && (
				<EmptyState
					compact
					icon="columns"
					title={ __( 'Automatic columns', 'dev3-ai-mega-menu' ) }
					description={ __( 'Child items fill the panel as columns. Add a row to place columns, products, posts, images or promos yourself.', 'dev3-ai-mega-menu' ) }
					action={
						<Button size="sm" icon="plus" onClick={ addRow }>
							{ __( 'Add a custom row', 'dev3-ai-mega-menu' ) }
						</Button>
					}
				/>
			) }
			{ rows.map( ( row, r ) => {
				const used = row.cols.reduce( ( n, c ) => n + c.span, 0 );
				return (
					<div className="dev3mm-prow" key={ r }>
						<div className="dev3mm-prow__head">
							<strong>{ sprintf( /* translators: %d: row number */ __( 'Row %d', 'dev3-ai-mega-menu' ), r + 1 ) }</strong>
							<Badge tone={ used > 12 ? 'warning' : 'neutral' }>{ sprintf( /* translators: %d: used grid columns */ __( '%d / 12 wide', 'dev3-ai-mega-menu' ), used ) }</Badge>
							<span className="dev3mm-grow" />
							<Button size="sm" variant="ghost" icon="plus" disabled={ row.cols.length >= 6 } onClick={ () => setRows( rows.map( ( x, ri ) => ( ri !== r ? x : { cols: [ ...x.cols, { span: Math.max( 2, 12 - used ) || 3, widgets: [] } ] } ) ) ) }>
								{ __( 'Column', 'dev3-ai-mega-menu' ) }
							</Button>
							<IconButton icon="trash" size="sm" label={ __( 'Remove row', 'dev3-ai-mega-menu' ) } onClick={ () => setRows( rows.filter( ( x, ri ) => ri !== r ) ) } />
						</div>
						<div className="dev3mm-prow__cols">
							{ row.cols.map( ( col, c ) => (
								<div className="dev3mm-pcol" key={ c } style={ { '--span': col.span } }>
									<div className="dev3mm-pcol__head">
										<Select
											label={ sprintf( /* translators: %d: column number */ __( 'Column %d width', 'dev3-ai-mega-menu' ), c + 1 ) }
											hideLabel
											value={ col.span }
											onChange={ ( v ) => setCol( r, c, { span: Number( v ) } ) }
											options={ [ 2, 3, 4, 5, 6, 8, 9, 12 ].map( ( n ) => ( { value: n, label: sprintf( /* translators: %d: span */ __( '%d/12', 'dev3-ai-mega-menu' ), n ) } ) ) }
										/>
										<IconButton icon="x" size="sm" label={ __( 'Remove column', 'dev3-ai-mega-menu' ) } onClick={ () => setRows( rows.map( ( x, ri ) => ( ri !== r ? x : { cols: x.cols.filter( ( y, ci ) => ci !== c ) } ) ).filter( ( x ) => x.cols.length ) ) } />
									</div>
									{ col.widgets.map( ( w, i ) => (
										<WidgetCard
											key={ i }
											widget={ w }
											children={ children }
											first={ i === 0 }
											last={ i === col.widgets.length - 1 }
											onMove={ ( dir ) => setCol( r, c, { widgets: moveIn( col.widgets, i, dir ) } ) }
											onChange={ ( next ) => setCol( r, c, { widgets: col.widgets.map( ( x, wi ) => ( wi === i ? next : x ) ) } ) }
											onRemove={ () => setCol( r, c, { widgets: col.widgets.filter( ( x, wi ) => wi !== i ) } ) }
										/>
									) ) }
									<AddWidget children={ children } onAdd={ ( w ) => setCol( r, c, { widgets: [ ...col.widgets, w ] } ) } />
								</div>
							) ) }
						</div>
					</div>
				);
			} ) }
			{ !! rows.length && (
				<div className="dev3mm-inline">
					<Button size="sm" icon="plus" onClick={ addRow }>
						{ __( 'Add row', 'dev3-ai-mega-menu' ) }
					</Button>
					{ !! unplaced.length && <span className="dev3mm-muted">{ sprintf( /* translators: %d: count */ __( '%d child items not placed: they appear in automatic columns below your rows.', 'dev3-ai-mega-menu' ), unplaced.length ) }</span> }
				</div>
			) }
			<div className="dev3mm-prow dev3mm-prow--aside">
				<div className="dev3mm-prow__head">
					<strong>{ __( 'Side column', 'dev3-ai-mega-menu' ) }</strong>
					<span className="dev3mm-muted">{ __( 'Shown on the right of the panel: promos, products, images.', 'dev3-ai-mega-menu' ) }</span>
				</div>
				{ aside.map( ( w, i ) => (
					<WidgetCard
						key={ i }
						widget={ w }
						children={ children }
						first={ i === 0 }
						last={ i === aside.length - 1 }
						onMove={ ( dir ) => onChange( { aside: moveIn( aside, i, dir ) } ) }
						onChange={ ( next ) => onChange( { aside: aside.map( ( x, wi ) => ( wi === i ? next : x ) ) } ) }
						onRemove={ () => onChange( { aside: aside.filter( ( x, wi ) => wi !== i ) } ) }
					/>
				) ) }
				{ aside.length < 4 && <AddWidget allowItem={ false } children={ children } onAdd={ ( w ) => onChange( { aside: [ ...aside, w ] } ) } /> }
			</div>
		</div>
	);
}
