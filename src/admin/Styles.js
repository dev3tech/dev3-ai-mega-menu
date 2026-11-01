import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, ColorField, ErrorNotice, FormGrid, Modal, Notice, NumberField, PageHeader, Select, SkeletonCard, TextField, Toggle, cx, useToast } from '../core3/ui';
import { DeviceSwitch, LivePreview, MenuSelect, useCurrentMenu, useMenus } from './shared';

const GROUPS = [
	{
		title: __( 'Menu bar', 'dev3-ai-mega-menu' ),
		keys: [
			[ 'bar_bg', __( 'Background', 'dev3-ai-mega-menu' ) ],
			[ 'bar_text', __( 'Text', 'dev3-ai-mega-menu' ) ],
			[ 'bar_hover_bg', __( 'Hover background', 'dev3-ai-mega-menu' ) ],
			[ 'bar_hover_text', __( 'Hover text', 'dev3-ai-mega-menu' ) ],
			[ 'accent', __( 'Accent (current item, buttons)', 'dev3-ai-mega-menu' ) ],
		],
	},
	{
		title: __( 'Panels', 'dev3-ai-mega-menu' ),
		keys: [
			[ 'panel_bg', __( 'Background', 'dev3-ai-mega-menu' ) ],
			[ 'panel_text', __( 'Links', 'dev3-ai-mega-menu' ) ],
			[ 'panel_heading', __( 'Column headings', 'dev3-ai-mega-menu' ) ],
			[ 'panel_muted', __( 'Descriptions', 'dev3-ai-mega-menu' ) ],
			[ 'panel_hover', __( 'Link hover', 'dev3-ai-mega-menu' ) ],
			[ 'border', __( 'Borders', 'dev3-ai-mega-menu' ) ],
		],
	},
	{
		title: __( 'Mobile & badges', 'dev3-ai-mega-menu' ),
		keys: [
			[ 'mobile_bg', __( 'Mobile background', 'dev3-ai-mega-menu' ) ],
			[ 'mobile_text', __( 'Mobile text', 'dev3-ai-mega-menu' ) ],
			[ 'badge_new', __( '“New” badge', 'dev3-ai-mega-menu' ) ],
			[ 'badge_sale', __( '“Sale” badge', 'dev3-ai-mega-menu' ) ],
			[ 'badge_hot', __( '“Hot” badge', 'dev3-ai-mega-menu' ) ],
			[ 'badge_info', __( '“Info” badge', 'dev3-ai-mega-menu' ) ],
		],
	},
];

const isHex = ( v ) => /^#([0-9a-f]{3}){1,2}$/i.test( v || '' );

/**
 * Mini preview of a style (swatches).
 *
 * @param {Object} props        Props.
 * @param {Object} props.tokens Tokens.
 * @return {Element} Swatch.
 */
function Swatch( { tokens } ) {
	const bar = isHex( tokens.bar_bg ) ? tokens.bar_bg : '#ffffff';
	return (
		<span className="dev3mm-swatch" aria-hidden="true">
			<span className="dev3mm-swatch__bar" style={ { background: bar, color: isHex( tokens.bar_text ) ? tokens.bar_text : '#1f2937' } }>
				<i />
				<i />
				<i />
			</span>
			<span className="dev3mm-swatch__panel" style={ { background: tokens.panel_bg, borderColor: tokens.border } }>
				<b style={ { background: tokens.panel_heading } } />
				<b style={ { background: tokens.panel_text, opacity: 0.5 } } />
				<b style={ { background: tokens.accent } } />
			</span>
		</span>
	);
}

/**
 * Styles: presets + custom styles with a visual editor and live preview.
 *
 * @return {Element} Screen.
 */
export default function Styles() {
	const toast = useToast();
	const { menus } = useMenus();
	const [ menuId, setMenuId ] = useCurrentMenu( menus );
	const [ data, setData ] = useState( null );
	const [ custom, setCustom ] = useState( [] );
	const [ active, setActive ] = useState( 'light' );
	const [ error, setError ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ dirty, setDirty ] = useState( false );
	const [ remove, setRemove ] = useState( null );

	const load = () =>
		request( 'themes' )
			.then( ( res ) => {
				setData( res );
				setCustom( res.custom );
				setDirty( false );
			} )
			.catch( setError );
	useEffect( () => {
		load();
	}, [] );

	if ( error ) {
		return <ErrorNotice error={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return <SkeletonCard lines={ 8 } />;
	}

	const all = [ ...data.presets, ...custom ];
	const theme = all.find( ( t ) => t.id === active ) || all[ 0 ];
	const editable = ! theme.preset;
	const setToken = ( key, value ) => {
		setCustom( custom.map( ( t ) => ( t.id === theme.id ? { ...t, tokens: { ...t.tokens, [ key ]: value } } : t ) ) );
		setDirty( true );
	};
	const duplicate = ( from ) => {
		const id = `custom-${ Date.now().toString( 36 ) }`;
		const copy = { id, name: sprintf( /* translators: %s: style name */ __( '%s (copy)', 'dev3-ai-mega-menu' ), from.name ), preset: false, tokens: { ...from.tokens } };
		setCustom( [ ...custom, copy ] );
		setActive( id );
		setDirty( true );
	};
	const persist = ( list = custom ) => {
		setBusy( true );
		return request( 'themes', { method: 'POST', data: { custom: list } } )
			.then( ( res ) => {
				setData( res );
				setCustom( res.custom );
				setDirty( false );
				if ( ! [ ...res.presets, ...res.custom ].some( ( t ) => t.id === active ) ) {
					setActive( 'light' );
				}
				toast.success( __( 'Styles saved.', 'dev3-ai-mega-menu' ) );
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<>
			<PageHeader
				title={ __( 'Styles', 'dev3-ai-mega-menu' ) }
				description={ __( 'Pick a preset or duplicate one and fine-tune colours, sizes and shape. Use a style per location in Locations.', 'dev3-ai-mega-menu' ) }
				actions={
					<Button variant="primary" icon="check" onClick={ () => persist() } loading={ busy } disabled={ ! dirty }>
						{ dirty ? __( 'Save styles', 'dev3-ai-mega-menu' ) : __( 'Saved', 'dev3-ai-mega-menu' ) }
					</Button>
				}
			/>
			<div className="dev3mm-styles">
				<Card title={ __( 'Styles', 'dev3-ai-mega-menu' ) } flush className="dev3mm-styles__list">
					<ul className="dev3mm-stylelist">
						{ all.map( ( t ) => (
							<li key={ t.id }>
								<button type="button" className={ cx( 'dev3mm-stylelist__item', t.id === theme.id && 'is-selected' ) } aria-current={ t.id === theme.id ? 'true' : undefined } onClick={ () => setActive( t.id ) }>
									<Swatch tokens={ t.tokens } />
									<span className="dev3mm-stylelist__name">{ t.name }</span>
									<Badge tone={ t.preset ? 'neutral' : 'brand' }>{ t.preset ? __( 'Preset', 'dev3-ai-mega-menu' ) : __( 'Custom', 'dev3-ai-mega-menu' ) }</Badge>
								</button>
							</li>
						) ) }
					</ul>
				</Card>
				<div className="dev3mm-stack">
					<Card
						title={ theme.name }
						description={ <code>{ theme.id }</code> }
						actions={
							<>
								<Button size="sm" icon="copy" onClick={ () => duplicate( theme ) }>
									{ __( 'Duplicate', 'dev3-ai-mega-menu' ) }
								</Button>
								{ editable && (
									<Button size="sm" variant="ghost" icon="trash" onClick={ () => setRemove( theme ) }>
										{ __( 'Delete', 'dev3-ai-mega-menu' ) }
									</Button>
								) }
							</>
						}
					>
						{ ! editable && (
							<Notice tone="info" actions={ <Button size="sm" variant="primary" icon="copy" onClick={ () => duplicate( theme ) }>{ __( 'Duplicate to customise', 'dev3-ai-mega-menu' ) }</Button> }>
								{ __( 'Presets are read-only so updates never overwrite your work. Duplicate this preset to edit it.', 'dev3-ai-mega-menu' ) }
							</Notice>
						) }
						{ editable && (
							<div className="dev3mm-stack">
								<TextField
									label={ __( 'Style name', 'dev3-ai-mega-menu' ) }
									value={ theme.name }
									maxLength={ 40 }
									onChange={ ( v ) => {
										setCustom( custom.map( ( t ) => ( t.id === theme.id ? { ...t, name: v } : t ) ) );
										setDirty( true );
									} }
								/>
								{ GROUPS.map( ( g ) => (
									<fieldset key={ g.title } className="dev3mm-fieldset">
										<legend>{ g.title }</legend>
										<FormGrid>
											{ g.keys.map( ( [ key, label ] ) =>
												isHex( theme.tokens[ key ] ) || ! theme.tokens[ key ] ? (
													<ColorField key={ key } label={ label } value={ theme.tokens[ key ] } onChange={ ( v ) => setToken( key, v ) } />
												) : (
													<TextField key={ key } label={ label } value={ theme.tokens[ key ] } onChange={ ( v ) => setToken( key, v ) } help={ __( 'A hex colour, “transparent” or “inherit”.', 'dev3-ai-mega-menu' ) } />
												)
											) }
										</FormGrid>
									</fieldset>
								) ) }
								<fieldset className="dev3mm-fieldset">
									<legend>{ __( 'Shape & type', 'dev3-ai-mega-menu' ) }</legend>
									<FormGrid>
										<NumberField label={ __( 'Bar height (px)', 'dev3-ai-mega-menu' ) } value={ theme.tokens.bar_height } min={ 40 } max={ 96 } onChange={ ( v ) => setToken( 'bar_height', v || 40 ) } />
										<NumberField label={ __( 'Corner radius (px)', 'dev3-ai-mega-menu' ) } value={ theme.tokens.radius } min={ 0 } max={ 24 } onChange={ ( v ) => setToken( 'radius', v === '' ? 0 : v ) } />
										<NumberField label={ __( 'Font size (px)', 'dev3-ai-mega-menu' ) } value={ theme.tokens.font_size } min={ 12 } max={ 20 } onChange={ ( v ) => setToken( 'font_size', v || 12 ) } />
										<Select
											label={ __( 'Font weight', 'dev3-ai-mega-menu' ) }
											value={ theme.tokens.font_weight }
											onChange={ ( v ) => setToken( 'font_weight', Number( v ) ) }
											options={ [ 400, 500, 600, 700 ].map( ( w ) => ( { value: w, label: String( w ) } ) ) }
										/>
										<NumberField label={ __( 'Item spacing (px)', 'dev3-ai-mega-menu' ) } value={ theme.tokens.item_padding } min={ 6 } max={ 32 } onChange={ ( v ) => setToken( 'item_padding', v || 6 ) } />
										<NumberField label={ __( 'Panel padding (px)', 'dev3-ai-mega-menu' ) } value={ theme.tokens.panel_padding } min={ 12 } max={ 48 } onChange={ ( v ) => setToken( 'panel_padding', v || 12 ) } />
										<Select
											label={ __( 'Panel shadow', 'dev3-ai-mega-menu' ) }
											value={ theme.tokens.shadow }
											onChange={ ( v ) => setToken( 'shadow', v ) }
											options={ [
												{ value: 'none', label: __( 'None', 'dev3-ai-mega-menu' ) },
												{ value: 'soft', label: __( 'Soft', 'dev3-ai-mega-menu' ) },
												{ value: 'strong', label: __( 'Strong', 'dev3-ai-mega-menu' ) },
											] }
										/>
										<TextField label={ __( 'Font family', 'dev3-ai-mega-menu' ) } value={ theme.tokens.font_family } onChange={ ( v ) => setToken( 'font_family', v ) } help={ __( '“inherit” uses your theme font.', 'dev3-ai-mega-menu' ) } />
									</FormGrid>
									<Toggle label={ __( 'Uppercase top-level labels', 'dev3-ai-mega-menu' ) } checked={ !! theme.tokens.uppercase } onChange={ ( v ) => setToken( 'uppercase', v ) } />
								</fieldset>
							</div>
						) }
					</Card>
					<Card title={ __( 'Live preview', 'dev3-ai-mega-menu' ) } actions={ <DeviceSwitch value={ device } onChange={ setDevice } /> }>
						<MenuSelect menus={ menus } value={ menuId } onChange={ setMenuId } label={ __( 'Preview with menu', 'dev3-ai-mega-menu' ) } />
						{ menuId > 0 && <LivePreview payload={ { menu_id: menuId, tokens: theme.tokens, config: { theme: theme.id } } } device={ device } height={ 500 } openFirst /> }
					</Card>
				</div>
			</div>
			<Modal
				open={ !! remove }
				onClose={ () => setRemove( null ) }
				size="sm"
				title={ __( 'Delete this style?', 'dev3-ai-mega-menu' ) }
				description={ __( 'Locations that use it fall back to the Light preset.', 'dev3-ai-mega-menu' ) }
				footer={
					<>
						<Button variant="ghost" onClick={ () => setRemove( null ) }>
							{ __( 'Cancel', 'dev3-ai-mega-menu' ) }
						</Button>
						<Button
							variant="danger"
							onClick={ () => {
								const list = custom.filter( ( t ) => t.id !== remove.id );
								setRemove( null );
								setActive( 'light' );
								persist( list );
							} }
						>
							{ __( 'Delete style', 'dev3-ai-mega-menu' ) }
						</Button>
					</>
				}
			/>
		</>
	);
}
