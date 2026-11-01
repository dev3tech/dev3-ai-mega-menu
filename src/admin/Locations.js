import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, EmptyState, ErrorNotice, FormGrid, Notice, NumberField, PageHeader, Select, SkeletonCard, TextField, Toggle, useToast } from '../core3/ui';
import { DeviceSwitch, LivePreview } from './shared';

const OPTS = {
	trigger: [
		{ value: 'hover', label: __( 'Hover (and click / keyboard)', 'dev3-ai-mega-menu' ) },
		{ value: 'click', label: __( 'Click only', 'dev3-ai-mega-menu' ) },
	],
	animation: [
		{ value: 'fade', label: __( 'Fade', 'dev3-ai-mega-menu' ) },
		{ value: 'slide', label: __( 'Fade + slide', 'dev3-ai-mega-menu' ) },
		{ value: 'none', label: __( 'None', 'dev3-ai-mega-menu' ) },
	],
	orientation: [
		{ value: 'horizontal', label: __( 'Horizontal bar', 'dev3-ai-mega-menu' ) },
		{ value: 'vertical', label: __( 'Vertical (sidebar)', 'dev3-ai-mega-menu' ) },
	],
	align: [
		{ value: 'start', label: __( 'Start', 'dev3-ai-mega-menu' ) },
		{ value: 'center', label: __( 'Center', 'dev3-ai-mega-menu' ) },
		{ value: 'end', label: __( 'End', 'dev3-ai-mega-menu' ) },
		{ value: 'between', label: __( 'Spread out', 'dev3-ai-mega-menu' ) },
	],
	panel_width: [
		{ value: 'full', label: __( 'Full window width', 'dev3-ai-mega-menu' ) },
		{ value: 'container', label: __( 'Menu bar width', 'dev3-ai-mega-menu' ) },
		{ value: 'auto', label: __( 'Fit content', 'dev3-ai-mega-menu' ) },
	],
	mobile_mode: [
		{ value: 'accordion', label: __( 'Accordion (drops down)', 'dev3-ai-mega-menu' ) },
		{ value: 'offcanvas', label: __( 'Off-canvas drawer', 'dev3-ai-mega-menu' ) },
	],
	offcanvas_side: [
		{ value: 'left', label: __( 'Left', 'dev3-ai-mega-menu' ) },
		{ value: 'right', label: __( 'Right', 'dev3-ai-mega-menu' ) },
	],
};

function LocationForm( { config, set, themes, breakpoints } ) {
	return (
		<>
			<FormGrid>
				<Select label={ __( 'Style', 'dev3-ai-mega-menu' ) } value={ config.theme } onChange={ ( v ) => set( { theme: v } ) } options={ themes.map( ( t ) => ( { value: t.id, label: t.name } ) ) } />
				<Select label={ __( 'Open submenus on', 'dev3-ai-mega-menu' ) } value={ config.trigger } onChange={ ( v ) => set( { trigger: v } ) } options={ OPTS.trigger } />
				{ config.trigger === 'hover' && <NumberField label={ __( 'Hover delay (ms)', 'dev3-ai-mega-menu' ) } value={ config.hover_delay } min={ 0 } max={ 1000 } step={ 50 } onChange={ ( v ) => set( { hover_delay: v === '' ? 0 : v } ) } /> }
				<Select label={ __( 'Animation', 'dev3-ai-mega-menu' ) } value={ config.animation } onChange={ ( v ) => set( { animation: v } ) } options={ OPTS.animation } />
				<Select label={ __( 'Orientation', 'dev3-ai-mega-menu' ) } value={ config.orientation } onChange={ ( v ) => set( { orientation: v } ) } options={ OPTS.orientation } />
				<Select label={ __( 'Item alignment', 'dev3-ai-mega-menu' ) } value={ config.align } onChange={ ( v ) => set( { align: v } ) } options={ OPTS.align } />
				<Select label={ __( 'Mega panel width', 'dev3-ai-mega-menu' ) } value={ config.panel_width } onChange={ ( v ) => set( { panel_width: v } ) } options={ OPTS.panel_width } />
			</FormGrid>
			<h4 className="dev3mm-subhead">{ __( 'Mobile', 'dev3-ai-mega-menu' ) }</h4>
			<FormGrid>
				<Select
					label={ __( 'Switch to the mobile menu below', 'dev3-ai-mega-menu' ) }
					value={ config.breakpoint }
					onChange={ ( v ) => set( { breakpoint: Number( v ) } ) }
					options={ breakpoints.map( ( b ) => ( { value: b, label: b ? sprintf( /* translators: %d: pixels */ __( '%d px', 'dev3-ai-mega-menu' ), b ) : __( 'Never (always desktop)', 'dev3-ai-mega-menu' ) } ) ) }
				/>
				<Select label={ __( 'Mobile menu', 'dev3-ai-mega-menu' ) } value={ config.mobile_mode } onChange={ ( v ) => set( { mobile_mode: v } ) } options={ OPTS.mobile_mode } />
				{ config.mobile_mode === 'offcanvas' && <Select label={ __( 'Drawer side', 'dev3-ai-mega-menu' ) } value={ config.offcanvas_side } onChange={ ( v ) => set( { offcanvas_side: v } ) } options={ OPTS.offcanvas_side } /> }
				<TextField label={ __( 'Toggle button label', 'dev3-ai-mega-menu' ) } value={ config.toggle_label } onChange={ ( v ) => set( { toggle_label: v } ) } placeholder={ __( 'Menu', 'dev3-ai-mega-menu' ) } maxLength={ 40 } />
			</FormGrid>
			<div className="dev3mm-toggles">
				<Toggle label={ __( 'Sticky on scroll', 'dev3-ai-mega-menu' ) } checked={ config.sticky } onChange={ ( v ) => set( { sticky: v } ) } help={ __( 'The bar stays at the top while visitors scroll.', 'dev3-ai-mega-menu' ) } />
				{ config.sticky && <Toggle label={ __( 'Sticky on mobile too', 'dev3-ai-mega-menu' ) } checked={ config.sticky_mobile } onChange={ ( v ) => set( { sticky_mobile: v } ) } /> }
				<Toggle label={ __( 'Search box at the end of the bar', 'dev3-ai-mega-menu' ) } checked={ config.show_search } onChange={ ( v ) => set( { show_search: v } ) } />
			</div>
			<TextField label={ __( 'Accessible name (aria-label)', 'dev3-ai-mega-menu' ) } value={ config.aria_label } onChange={ ( v ) => set( { aria_label: v } ) } help={ __( 'Defaults to the menu name. Screen readers announce it, e.g. “Main”.', 'dev3-ai-mega-menu' ) } maxLength={ 40 } />
		</>
	);
}

/**
 * Locations: which theme locations use the mega menu, with what settings, plus embeds.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.settings Settings.
 * @param {Function} props.save     Save settings.
 * @return {Element} Screen.
 */
export default function Locations( { settings, save } ) {
	const toast = useToast();
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ configs, setConfigs ] = useState( {} );
	const [ assign, setAssign ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ preview, setPreview ] = useState( '' );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ block, setBlock ] = useState( { block_nav_replace: settings.block_nav_replace, block_nav_menu: settings.block_nav_menu } );

	const load = () =>
		request( 'locations' )
			.then( ( res ) => {
				setData( res );
				setConfigs( Object.fromEntries( res.locations.map( ( l ) => [ l.location, l.config ] ) ) );
				setAssign( Object.fromEntries( res.locations.filter( ( l ) => ! l.virtual ).map( ( l ) => [ l.location, l.menu_id ] ) ) );
				setPreview( ( p ) => p || ( res.locations.find( ( l ) => ! l.virtual && l.menu_id ) || res.locations.find( ( l ) => l.virtual ) || {} ).location || '' );
			} )
			.catch( setError );
	useEffect( () => {
		load();
	}, [] );

	const set = ( loc ) => ( patch ) => setConfigs( { ...configs, [ loc ]: { ...configs[ loc ], ...patch } } );

	const submit = () => {
		setBusy( true );
		Promise.all( [ request( 'locations', { method: 'POST', data: { configs, assignments: assign } } ), save( block ) ] )
			.then( ( [ res ] ) => {
				setData( res );
				toast.success( __( 'Locations saved.', 'dev3-ai-mega-menu' ) );
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( false ) );
	};

	if ( error ) {
		return <ErrorNotice error={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return <SkeletonCard lines={ 8 } />;
	}
	const theme = data.locations.filter( ( l ) => ! l.virtual );
	const embed = data.locations.find( ( l ) => l.virtual );
	const menuOptions = [ { value: 0, label: __( '— None —', 'dev3-ai-mega-menu' ) }, ...data.menus.map( ( m ) => ( { value: m.id, label: m.name } ) ) ];
	const previewMenu = preview === embed.location ? Number( block.block_nav_menu ) || ( data.menus[ 0 ] || {} ).id : assign[ preview ];

	return (
		<>
			<PageHeader
				title={ __( 'Locations', 'dev3-ai-mega-menu' ) }
				description={ __( 'Choose where the mega menu replaces your theme’s menu, and how it behaves on desktop and mobile.', 'dev3-ai-mega-menu' ) }
				actions={
					<Button variant="primary" icon="check" onClick={ submit } loading={ busy }>
						{ __( 'Save locations', 'dev3-ai-mega-menu' ) }
					</Button>
				}
			/>

			{ data.block_theme && (
				<Notice tone="info" title={ __( 'You are using a block theme', 'dev3-ai-mega-menu' ) }>
					{ __( 'Block themes place menus with the Navigation block. Either add the “DEV3 AI Mega Menu” block to your header template, or let the plugin replace the Navigation block (see “Blocks, shortcodes & builders” below).', 'dev3-ai-mega-menu' ) }
				</Notice>
			) }

			{ ! theme.length && ! data.block_theme && (
				<Card>
					<EmptyState compact icon="menu" title={ __( 'Your theme registers no menu locations', 'dev3-ai-mega-menu' ) } description={ __( 'Use the block, the shortcode or a page-builder widget instead (below).', 'dev3-ai-mega-menu' ) } />
				</Card>
			) }

			{ theme.map( ( l ) => {
				const c = configs[ l.location ];
				return (
					<Card
						key={ l.location }
						title={ l.label }
						description={ <code>{ l.location }</code> }
						actions={
							<>
								<Badge tone={ c.enabled && assign[ l.location ] ? 'success' : 'neutral' } dot>
									{ c.enabled && assign[ l.location ] ? __( 'Mega menu on', 'dev3-ai-mega-menu' ) : __( 'Theme menu', 'dev3-ai-mega-menu' ) }
								</Badge>
							</>
						}
					>
						<FormGrid>
							<Select label={ __( 'Menu shown here', 'dev3-ai-mega-menu' ) } value={ assign[ l.location ] || 0 } onChange={ ( v ) => setAssign( { ...assign, [ l.location ]: Number( v ) } ) } options={ menuOptions } />
							<Toggle label={ __( 'Use the DEV3 AI Mega Menu here', 'dev3-ai-mega-menu' ) } checked={ c.enabled } onChange={ ( v ) => set( l.location )( { enabled: v } ) } help={ __( 'Off: your theme renders this location as before.', 'dev3-ai-mega-menu' ) } />
						</FormGrid>
						{ c.enabled && <LocationForm config={ c } set={ set( l.location ) } themes={ data.themes } breakpoints={ data.breakpoints } /> }
					</Card>
				);
			} ) }

			<Card title={ __( 'Blocks, shortcodes & builders', 'dev3-ai-mega-menu' ) } description={ __( 'Settings used by the DEV3 AI Mega Menu block, the [dev3mm_menu] shortcode, the Elementor and WPBakery widgets, and the Navigation-block replacement.', 'dev3-ai-mega-menu' ) }>
				<FormGrid>
					<Select
						label={ __( 'Replace the Navigation block (block themes)', 'dev3-ai-mega-menu' ) }
						value={ block.block_nav_replace }
						onChange={ ( v ) => setBlock( { ...block, block_nav_replace: v } ) }
						options={ [
							{ value: 'off', label: __( 'Off', 'dev3-ai-mega-menu' ) },
							{ value: 'first', label: __( 'First Navigation block on each page (usually the header)', 'dev3-ai-mega-menu' ) },
							{ value: 'all', label: __( 'Every Navigation block', 'dev3-ai-mega-menu' ) },
						] }
					/>
					{ block.block_nav_replace !== 'off' && <Select label={ __( 'Menu to show instead', 'dev3-ai-mega-menu' ) } value={ block.block_nav_menu || 0 } onChange={ ( v ) => setBlock( { ...block, block_nav_menu: Number( v ) } ) } options={ menuOptions } /> }
				</FormGrid>
				<div className="dev3mm-snippet">
					<span className="dev3mm-ui-label">{ __( 'Shortcode', 'dev3-ai-mega-menu' ) }</span>
					<code>[dev3mm_menu menu=&quot;{ ( data.menus[ 0 ] || {} ).slug || 'main-menu' }&quot; theme=&quot;dark&quot; mobile_mode=&quot;offcanvas&quot;]</code>
				</div>
				<LocationForm config={ configs[ embed.location ] } set={ set( embed.location ) } themes={ data.themes } breakpoints={ data.breakpoints } />
			</Card>

			<Card
				title={ __( 'Preview', 'dev3-ai-mega-menu' ) }
				description={ __( 'Unsaved location settings are applied to the preview.', 'dev3-ai-mega-menu' ) }
				actions={ <DeviceSwitch value={ device } onChange={ setDevice } /> }
			>
				<Select
					label={ __( 'Preview location', 'dev3-ai-mega-menu' ) }
					value={ preview }
					onChange={ setPreview }
					options={ data.locations.map( ( l ) => ( { value: l.location, label: l.virtual ? __( 'Blocks, shortcodes & builders', 'dev3-ai-mega-menu' ) : l.label } ) ) }
				/>
				{ previewMenu ? (
					<LivePreview payload={ { menu_id: previewMenu, location: preview, config: { ...configs[ preview ], enabled: true } } } device={ device } height={ 500 } openFirst />
				) : (
					<EmptyState compact icon="menu" title={ __( 'No menu assigned to this location', 'dev3-ai-mega-menu' ) } />
				) }
			</Card>
		</>
	);
}
