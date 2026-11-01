import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../../core3/api';
import { AiButton, Badge, Button, Card, Checkbox, ErrorNotice, FormGrid, IconButton, Inline, Notice, NumberField, Select, Stat, StatGrid, Textarea, TextField, useToast } from '../../core3/ui';
import { DeviceSwitch, LAYOUT_LABELS, LAYOUT_TONES, LivePreview, MenuIcon, MenuSelect } from '../shared';
import { draftCount, updateDraft } from '../tree';

/**
 * Editable draft tree (labels can be edited, nodes removed) before accepting.
 *
 * @param {Object} props Props.
 * @return {Element} Tree.
 */
export function DraftTree( { draft, onChange } ) {
	const node = ( d, path ) => (
		<li key={ path.join( '-' ) } className="dev3mm-dtree__node" style={ { '--depth': path.length - 1 } }>
			<div className="dev3mm-dtree__row">
				<span className="dev3mm-dtree__icon" aria-hidden="true">
					{ d.icon ? <MenuIcon name={ d.icon } size={ 16 } /> : <span className="dev3mm-tree__dot" /> }
				</span>
				<TextField
					label={ sprintf( /* translators: %s: path */ __( 'Label for %s', 'dev3-ai-mega-menu' ), d.ref ) }
					hideLabel
					value={ d.title }
					maxLength={ 40 }
					onChange={ ( v ) => onChange( updateDraft( draft, path, { title: v } ) ) }
				/>
				<code className="dev3mm-dtree__ref">{ d.ref }</code>
				{ path.length === 1 && d.layout !== 'link' && <Badge tone={ LAYOUT_TONES[ d.layout ] }>{ LAYOUT_LABELS[ d.layout ] }</Badge> }
				<IconButton size="sm" icon="trash" label={ __( 'Remove from draft', 'dev3-ai-mega-menu' ) } onClick={ () => onChange( updateDraft( draft, path, null ) ) } />
			</div>
			{ d.children && d.children.length > 0 && <ul className="dev3mm-dtree">{ d.children.map( ( c, i ) => node( c, [ ...path, i ] ) ) }</ul> }
		</li>
	);
	return <ul className="dev3mm-dtree dev3mm-dtree--root">{ draft.map( ( d, i ) => node( d, [ i ] ) ) }</ul>;
}

/**
 * "Build my menu": inventory → AI (or rules) draft → review/edit → create or replace a menu.
 *
 * @param {Object}   props         Props.
 * @param {Array}    props.menus   Menus.
 * @param {Function} props.reload  Reload menus.
 * @param {Function} props.onUsage Usage changed.
 * @param {Object}   props.status  AI status.
 * @return {Element} Tool.
 */
export default function BuildMenu( { menus, reload, onUsage, aiReady } ) {
	const toast = useToast();
	const [ opts, setOpts ] = useState( { max_top: 7, notes: '', pages: true, categories: true, products: true } );
	const [ result, setResult ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( null );
	const [ target, setTarget ] = useState( { mode: 'new', name: __( 'Main menu', 'dev3-ai-mega-menu' ), menu_id: 0, location: '' } );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ elapsed, setElapsed ] = useState( 0 );

	const run = ( mode ) => {
		setBusy( mode );
		setError( null );
		const started = Date.now();
		request( 'build', { method: 'POST', data: { ...opts, mode } } )
			.then( ( res ) => {
				setResult( res );
				setDraft( res.draft );
				setElapsed( Date.now() - started );
			} )
			.catch( setError )
			.finally( () => {
				setBusy( '' );
				onUsage();
			} );
	};

	const apply = () => {
		setBusy( 'apply' );
		request( 'build/apply', { method: 'POST', data: { draft, target } } )
			.then( ( res ) => {
				toast.show( {
					tone: 'success',
					title: __( 'Menu saved', 'dev3-ai-mega-menu' ),
					message: sprintf( /* translators: %s: menu name */ __( '“%s” is ready. Fine-tune it in the Menu Builder.', 'dev3-ai-mega-menu' ), res.menu.name ),
					action: { label: __( 'Open builder', 'dev3-ai-mega-menu' ), onClick: () => ( window.location.hash = '#/builder' ) },
				} );
				try {
					window.localStorage.setItem( 'dev3mm-menu', String( res.menu_id ) );
				} catch ( e ) {}
				setDraft( null );
				setResult( null );
				reload();
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( '' ) );
	};

	return (
		<>
			<Card tone="ai" title={ __( 'Build my menu', 'dev3-ai-mega-menu' ) } description={ __( 'DEV3 AI reads your pages, categories and products and proposes a complete mega menu: top items, columns, groups and labels. You review and edit it before anything is saved.', 'dev3-ai-mega-menu' ) }>
				<FormGrid>
					<NumberField label={ __( 'Top-level items (max)', 'dev3-ai-mega-menu' ) } value={ opts.max_top } min={ 3 } max={ 10 } onChange={ ( v ) => setOpts( { ...opts, max_top: v || 7 } ) } />
					<div className="dev3mm-field-block">
						<span className="dev3mm-ui-label">{ __( 'Read from', 'dev3-ai-mega-menu' ) }</span>
						<Inline gap={ 16 }>
							<Checkbox label={ __( 'Pages', 'dev3-ai-mega-menu' ) } checked={ opts.pages } onChange={ ( v ) => setOpts( { ...opts, pages: v } ) } />
							<Checkbox label={ __( 'Post categories', 'dev3-ai-mega-menu' ) } checked={ opts.categories } onChange={ ( v ) => setOpts( { ...opts, categories: v } ) } />
							<Checkbox label={ __( 'Products & product categories', 'dev3-ai-mega-menu' ) } checked={ opts.products } onChange={ ( v ) => setOpts( { ...opts, products: v } ) } />
						</Inline>
					</div>
				</FormGrid>
				<Textarea label={ __( 'Notes for DEV3 AI (optional)', 'dev3-ai-mega-menu' ) } value={ opts.notes } onChange={ ( v ) => setOpts( { ...opts, notes: v } ) } rows={ 2 } maxLength={ 400 } placeholder={ __( 'e.g. Put Sale first, keep the About pages under one item, we are a B2B store', 'dev3-ai-mega-menu' ) } />
				<Inline gap={ 8 }>
					<AiButton onClick={ () => run( 'ai' ) } loading={ busy === 'ai' } loadingText={ __( 'Designing your menu…', 'dev3-ai-mega-menu' ) } disabled={ !! busy || ! aiReady }>
						{ __( 'Build with DEV3 AI', 'dev3-ai-mega-menu' ) }
					</AiButton>
					<Button onClick={ () => run( 'rules' ) } loading={ busy === 'rules' } disabled={ !! busy }>
						{ __( 'Auto-build without AI', 'dev3-ai-mega-menu' ) }
					</Button>
				</Inline>
				{ ! aiReady && <p className="dev3mm-muted">{ __( 'Connect DEV3 AI in API Config to use the AI builder. The automatic builder works without it.', 'dev3-ai-mega-menu' ) }</p> }
				<ErrorNotice error={ error } onRetry={ () => run( 'ai' ) } onDismiss={ () => setError( null ) } />
			</Card>

			{ draft && result && (
				<Card
					tone="ai"
					title={ result.source === 'ai' ? __( 'DEV3 AI draft', 'dev3-ai-mega-menu' ) : __( 'Automatic draft', 'dev3-ai-mega-menu' ) }
					description={ __( 'Not saved yet. Edit labels or remove items, then choose where to save it.', 'dev3-ai-mega-menu' ) }
					actions={
						<Inline gap={ 8 }>
							<Badge tone="warning">{ __( 'Not saved yet', 'dev3-ai-mega-menu' ) }</Badge>
							{ result.model && <Badge tone="info">{ result.model }</Badge> }
							{ result.cached && <Badge>{ __( 'Cached', 'dev3-ai-mega-menu' ) }</Badge> }
						</Inline>
					}
				>
					<StatGrid>
						<Stat label={ __( 'Top-level items', 'dev3-ai-mega-menu' ) } value={ String( draft.length ) } icon="menu" tone="brand" />
						<Stat label={ __( 'Items in total', 'dev3-ai-mega-menu' ) } value={ String( draftCount( draft ) ) } icon="list" />
						<Stat label={ __( 'Mega panels', 'dev3-ai-mega-menu' ) } value={ String( draft.filter( ( d ) => d.layout === 'mega' ).length ) } icon="columns" />
						<Stat
							label={ __( 'Read', 'dev3-ai-mega-menu' ) }
							value={ sprintf( /* translators: 1: pages 2: categories */ __( '%1$d pages · %2$d categories', 'dev3-ai-mega-menu' ), result.inventory.pages, result.inventory.categories + result.inventory.product_cats ) }
							hint={ sprintf( /* translators: %s: seconds */ __( 'in %s s', 'dev3-ai-mega-menu' ), ( elapsed / 1000 ).toFixed( 1 ) ) }
							icon="clock"
						/>
					</StatGrid>
					<DraftTree draft={ draft } onChange={ setDraft } />
					<div className="dev3mm-preview-head">
						<strong>{ __( 'Preview', 'dev3-ai-mega-menu' ) }</strong>
						<DeviceSwitch value={ device } onChange={ setDevice } />
					</div>
					<LivePreview payload={ { draft } } device={ device } height={ 480 } openFirst />
					<fieldset className="dev3mm-fieldset">
						<legend>{ __( 'Save to', 'dev3-ai-mega-menu' ) }</legend>
						<FormGrid>
							<Select
								label={ __( 'Action', 'dev3-ai-mega-menu' ) }
								value={ target.mode }
								onChange={ ( v ) => setTarget( { ...target, mode: v } ) }
								options={ [
									{ value: 'new', label: __( 'Create a new menu', 'dev3-ai-mega-menu' ) },
									{ value: 'replace', label: __( 'Replace the items of a menu', 'dev3-ai-mega-menu' ) },
									{ value: 'append', label: __( 'Add to the end of a menu', 'dev3-ai-mega-menu' ) },
								] }
							/>
							{ target.mode === 'new' ? (
								<TextField label={ __( 'Menu name', 'dev3-ai-mega-menu' ) } value={ target.name } onChange={ ( v ) => setTarget( { ...target, name: v } ) } maxLength={ 80 } />
							) : (
								<MenuSelect menus={ menus } value={ target.menu_id } onChange={ ( id ) => setTarget( { ...target, menu_id: id } ) } />
							) }
						</FormGrid>
						{ target.mode === 'replace' && <Notice tone="warning">{ __( 'Replacing removes the current items of that menu.', 'dev3-ai-mega-menu' ) }</Notice> }
					</fieldset>
					<Inline gap={ 8 }>
						<Button variant="primary" icon="check" onClick={ apply } loading={ busy === 'apply' } disabled={ ! draft.length || ( target.mode !== 'new' && ! target.menu_id ) }>
							{ __( 'Accept and save menu', 'dev3-ai-mega-menu' ) }
						</Button>
						{ result.source === 'ai' && (
							<Button icon="refresh" onClick={ () => run( 'ai' ) } loading={ busy === 'ai' } disabled={ !! busy }>
								{ __( 'Regenerate', 'dev3-ai-mega-menu' ) }
							</Button>
						) }
						<Button
							variant="ghost"
							onClick={ () => {
								setDraft( null );
								setResult( null );
							} }
						>
							{ __( 'Reject', 'dev3-ai-mega-menu' ) }
						</Button>
					</Inline>
				</Card>
			) }
		</>
	);
}
