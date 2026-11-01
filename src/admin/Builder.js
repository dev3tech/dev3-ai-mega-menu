import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, EmptyState, ErrorNotice, IconButton, Inline, Modal, Notice, PageHeader, SkeletonCard, TextField, cx, useToast } from '../core3/ui';
import AddItems from './AddItems';
import ItemEditor from './ItemEditor';
import { DeviceSwitch, LAYOUT_LABELS, LAYOUT_TONES, LivePreview, MenuIcon, MenuSelect, useCurrentMenu, useMenus } from './shared';
import { addNodes, countNodes, find, flatten, indentNode, layoutOf, moveNode, outdentNode, removeNode, updateNode } from './tree';

/**
 * Menu Builder: structure (add, reorder, nest), item settings and panels, live preview.
 *
 * @return {Element} Screen.
 */
export default function Builder() {
	const toast = useToast();
	const { menus, reload, error: menusError } = useMenus();
	const [ menuId, setMenuId ] = useCurrentMenu( menus );
	const [ tree, setTree ] = useState( null );
	const [ saved, setSaved ] = useState( '[]' );
	const [ selected, setSelected ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ adding, setAdding ] = useState( false );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ creating, setCreating ] = useState( false );
	const [ newName, setNewName ] = useState( '' );
	const [ confirmDelete, setConfirmDelete ] = useState( false );

	const dirty = tree !== null && JSON.stringify( tree ) !== saved;

	useEffect( () => {
		if ( ! menuId ) {
			setTree( null );
			return;
		}
		setTree( null );
		setError( null );
		setSelected( null );
		request( `menus/${ menuId }` )
			.then( ( res ) => {
				setTree( res.tree );
				setSaved( JSON.stringify( res.tree ) );
			} )
			.catch( setError );
	}, [ menuId ] );

	useEffect( () => {
		const warn = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const rows = useMemo( () => ( tree ? flatten( tree ) : [] ), [ tree ] );
	const hit = tree && selected ? find( tree, selected ) : null;
	const selectedDepth = hit ? hit.path.length - 1 : 0;

	const save = () => {
		setSaving( true );
		request( `menus/${ menuId }`, { method: 'POST', data: { tree } } )
			.then( ( res ) => {
				// Map the selection to the saved id (new items got real ids).
				const index = rows.findIndex( ( r ) => r.node.id === selected );
				const nextRows = flatten( res.tree );
				setTree( res.tree );
				setSaved( JSON.stringify( res.tree ) );
				setSelected( index > -1 && nextRows[ index ] ? nextRows[ index ].node.id : null );
				toast.success( __( 'Menu saved.', 'dev3-ai-mega-menu' ) );
				reload();
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setSaving( false ) );
	};

	const createMenu = () => {
		request( 'menus', { method: 'POST', data: { name: newName } } )
			.then( ( menu ) => {
				setCreating( false );
				setNewName( '' );
				reload().then( () => setMenuId( menu.id ) );
				toast.success( __( 'Menu created. Add items to get started.', 'dev3-ai-mega-menu' ) );
			} )
			.catch( ( err ) => toast.error( err ) );
	};

	const deleteMenu = () => {
		request( `menus/${ menuId }`, { method: 'DELETE' } )
			.then( () => {
				setConfirmDelete( false );
				setMenuId( 0 );
				reload();
				toast.success( __( 'Menu deleted.', 'dev3-ai-mega-menu' ) );
			} )
			.catch( ( err ) => toast.error( err ) );
	};

	const menu = menus && menus.find( ( m ) => m.id === menuId );
	const parents = rows.filter( ( r ) => r.depth < 2 ).map( ( r ) => ( { id: r.node.id, label: `${ '— '.repeat( r.depth ) }${ r.node.title || __( '(no label)', 'dev3-ai-mega-menu' ) }` } ) );

	return (
		<>
			<PageHeader
				title={ __( 'Menu Builder', 'dev3-ai-mega-menu' ) }
				description={ __( 'Build the structure, then turn top-level items into mega panels with columns, tabs, products, posts and promos.', 'dev3-ai-mega-menu' ) }
				actions={
					<>
						<Button icon="plus" onClick={ () => setCreating( true ) }>
							{ __( 'New menu', 'dev3-ai-mega-menu' ) }
						</Button>
						<Button variant="primary" icon="check" onClick={ save } loading={ saving } disabled={ ! dirty || saving }>
							{ dirty ? __( 'Save menu', 'dev3-ai-mega-menu' ) : __( 'Saved', 'dev3-ai-mega-menu' ) }
						</Button>
					</>
				}
			/>
			<ErrorNotice error={ menusError || error } onRetry={ reload } />

			<Card className="dev3mm-menubar">
				<div className="dev3mm-menubar__row">
					<div className="dev3mm-menubar__select">
						<MenuSelect
							menus={ menus }
							value={ menuId }
							onChange={ ( id ) => {
								if ( dirty && ! window.confirm( __( 'Discard unsaved changes?', 'dev3-ai-mega-menu' ) ) ) {
									return;
								}
								setMenuId( id );
							} }
						/>
					</div>
					{ menu && (
						<Inline gap={ 8 }>
							{ menu.locations.map( ( l ) => (
								<Badge key={ l.location } tone={ l.enabled ? 'success' : 'neutral' } dot>
									{ l.label }
								</Badge>
							) ) }
							{ menu.is_block && <Badge tone="success" dot>{ __( 'Navigation block', 'dev3-ai-mega-menu' ) }</Badge> }
							{ ! menu.locations.length && ! menu.is_block && <Badge tone="warning">{ __( 'Not assigned to a location', 'dev3-ai-mega-menu' ) }</Badge> }
							{ dirty && <Badge tone="warning">{ __( 'Unsaved changes', 'dev3-ai-mega-menu' ) }</Badge> }
						</Inline>
					) }
					<span className="dev3mm-grow" />
					{ menu && (
						<Button variant="ghost" size="sm" icon="trash" onClick={ () => setConfirmDelete( true ) }>
							{ __( 'Delete menu', 'dev3-ai-mega-menu' ) }
						</Button>
					) }
				</div>
			</Card>

			{ menus && ! menus.length && (
				<Card>
					<EmptyState
						icon="menu"
						title={ __( 'No menus yet', 'dev3-ai-mega-menu' ) }
						description={ __( 'Create an empty menu, or let DEV3 AI build a complete mega menu from your pages, categories and products.', 'dev3-ai-mega-menu' ) }
						action={
							<Button variant="primary" icon="plus" onClick={ () => setCreating( true ) }>
								{ __( 'Create a menu', 'dev3-ai-mega-menu' ) }
							</Button>
						}
						secondary={
							<Button variant="ai" icon="sparkles" href="#/ai">
								{ __( 'Build my menu with DEV3 AI', 'dev3-ai-mega-menu' ) }
							</Button>
						}
					/>
				</Card>
			) }

			{ menuId > 0 && tree === null && ! error && <SkeletonCard lines={ 6 } /> }

			{ tree && menuId > 0 && (
				<div className="dev3mm-builder">
					<Card
						title={ __( 'Structure', 'dev3-ai-mega-menu' ) }
						description={ sprintf( /* translators: %d: item count */ __( '%d items. Use the arrows to reorder and nest (up to 3 levels work best).', 'dev3-ai-mega-menu' ), countNodes( tree ) ) }
						actions={
							<Button size="sm" icon="plus" variant="primary" onClick={ () => setAdding( true ) }>
								{ __( 'Add items', 'dev3-ai-mega-menu' ) }
							</Button>
						}
						flush
						className="dev3mm-structure"
					>
						{ ! tree.length ? (
							<EmptyState compact icon="list" title={ __( 'This menu is empty', 'dev3-ai-mega-menu' ) } description={ __( 'Add pages, categories, products or custom links.', 'dev3-ai-mega-menu' ) } />
						) : (
							<ul className="dev3mm-tree" aria-label={ __( 'Menu items', 'dev3-ai-mega-menu' ) }>
								{ rows.map( ( { node, depth, index, siblings } ) => {
									const layout = layoutOf( node, depth );
									const isSel = node.id === selected;
									return (
										<li key={ node.id } className={ cx( 'dev3mm-tree__row', isSel && 'is-selected', node.invalid && 'is-invalid' ) } style={ { '--depth': depth } }>
											<button type="button" className="dev3mm-tree__main" onClick={ () => setSelected( node.id ) } aria-current={ isSel ? 'true' : undefined }>
												<span className="dev3mm-tree__icon" aria-hidden="true">
													{ node.config.icon ? <MenuIcon name={ node.config.icon } size={ 16 } /> : <span className="dev3mm-tree__dot" /> }
												</span>
												<span className="dev3mm-tree__title">{ node.title || __( '(no label)', 'dev3-ai-mega-menu' ) }</span>
												{ node.config.badge && node.config.badge.text && <Badge tone={ node.config.badge.tone === 'sale' ? 'danger' : node.config.badge.tone === 'hot' ? 'warning' : 'success' }>{ node.config.badge.text }</Badge> }
												{ depth === 0 && layout !== 'link' && <Badge tone={ LAYOUT_TONES[ layout ] }>{ LAYOUT_LABELS[ layout ] }</Badge> }
												{ node.invalid && <Badge tone="danger">{ __( 'Broken', 'dev3-ai-mega-menu' ) }</Badge> }
											</button>
											<span className="dev3mm-tree__actions">
												<IconButton size="sm" icon="chevronDown" className="dev3mm-flip" label={ __( 'Move up', 'dev3-ai-mega-menu' ) } disabled={ index === 0 } onClick={ () => setTree( moveNode( tree, node.id, -1 ) ) } />
												<IconButton size="sm" icon="chevronDown" label={ __( 'Move down', 'dev3-ai-mega-menu' ) } disabled={ index === siblings - 1 } onClick={ () => setTree( moveNode( tree, node.id, 1 ) ) } />
												<IconButton size="sm" icon="chevronLeft" label={ __( 'Move out a level', 'dev3-ai-mega-menu' ) } disabled={ depth === 0 } onClick={ () => setTree( outdentNode( tree, node.id ) ) } />
												<IconButton size="sm" icon="chevronRight" label={ __( 'Make sub-item', 'dev3-ai-mega-menu' ) } disabled={ index === 0 || depth >= 3 } onClick={ () => setTree( indentNode( tree, node.id, 3 ) ) } />
												<IconButton
													size="sm"
													icon="trash"
													label={ __( 'Remove item', 'dev3-ai-mega-menu' ) }
													onClick={ () => {
														setTree( removeNode( tree, node.id ) );
														if ( isSel ) {
															setSelected( null );
														}
													} }
												/>
											</span>
										</li>
									);
								} ) }
							</ul>
						) }
					</Card>

					<div className="dev3mm-builder__side">
						<ItemEditor
							key={ selected || 'none' }
							node={ hit ? hit.node : null }
							depth={ selectedDepth }
							onChange={ ( patch ) => setTree( updateNode( tree, selected, patch ) ) }
							onConfig={ ( patch ) => setTree( updateNode( tree, selected, ( n ) => ( { ...n, config: { ...n.config, ...patch } } ) ) ) }
						/>
					</div>
				</div>
			) }

			{ tree && tree.length > 0 && (
				<Card
					title={ __( 'Live preview', 'dev3-ai-mega-menu' ) }
					description={ __( 'The real front-end menu with your unsaved changes. Hover or click items to open panels.', 'dev3-ai-mega-menu' ) }
					actions={ <DeviceSwitch value={ device } onChange={ setDevice } /> }
				>
					{ ! menu || ( ! menu.is_block && ! menu.locations.some( ( l ) => l.enabled ) ) ? (
						<Notice tone="info" title={ __( 'Not live yet', 'dev3-ai-mega-menu' ) } actions={ <Button size="sm" href="#/locations">{ __( 'Open Locations', 'dev3-ai-mega-menu' ) }</Button> }>
							{ __( 'Turn on the mega menu for a theme location (or add the DEV3 AI Mega Menu block) to show it on your site.', 'dev3-ai-mega-menu' ) }
						</Notice>
					) : null }
					<LivePreview payload={ { menu_id: menuId, tree, location: menu && menu.locations[ 0 ] ? menu.locations[ 0 ].location : '' } } device={ device } height={ 520 } openFirst />
				</Card>
			) }

			<AddItems open={ adding } onClose={ () => setAdding( false ) } parents={ parents } parent={ selected && selectedDepth < 2 ? selected : 0 } onAdd={ ( nodes, parentId ) => setTree( addNodes( tree || [], nodes, parentId ) ) } />

			<Modal
				open={ creating }
				onClose={ () => setCreating( false ) }
				size="sm"
				title={ __( 'New menu', 'dev3-ai-mega-menu' ) }
				footer={
					<>
						<Button variant="ghost" onClick={ () => setCreating( false ) }>
							{ __( 'Cancel', 'dev3-ai-mega-menu' ) }
						</Button>
						<Button variant="primary" onClick={ createMenu } disabled={ ! newName.trim() }>
							{ __( 'Create menu', 'dev3-ai-mega-menu' ) }
						</Button>
					</>
				}
			>
				<TextField label={ __( 'Menu name', 'dev3-ai-mega-menu' ) } value={ newName } onChange={ setNewName } required data-autofocus maxLength={ 80 } placeholder={ __( 'e.g. Main menu', 'dev3-ai-mega-menu' ) } />
			</Modal>

			<Modal
				open={ confirmDelete }
				onClose={ () => setConfirmDelete( false ) }
				size="sm"
				title={ __( 'Delete this menu?', 'dev3-ai-mega-menu' ) }
				description={ menu ? sprintf( /* translators: %s: menu name */ __( '“%s” and all its items will be removed. This cannot be undone.', 'dev3-ai-mega-menu' ), menu.name ) : '' }
				footer={
					<>
						<Button variant="ghost" onClick={ () => setConfirmDelete( false ) }>
							{ __( 'Cancel', 'dev3-ai-mega-menu' ) }
						</Button>
						<Button variant="danger" onClick={ deleteMenu }>
							{ __( 'Delete menu', 'dev3-ai-mega-menu' ) }
						</Button>
					</>
				}
			/>
		</>
	);
}
