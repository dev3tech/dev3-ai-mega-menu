import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Placeholder } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

const ICON = (
	<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true">
		<path d="M3 5h18M3 12h7M3 19h7M14 10h7v9h-7z" />
	</svg>
);

registerBlockType( 'dev3mm/mega-menu', {
	apiVersion: 2,
	title: __( 'DEV3 AI Mega Menu', 'dev3-ai-mega-menu' ),
	description: __( 'An accessible mega menu with panels, tabs, flyouts and a mobile menu.', 'dev3-ai-mega-menu' ),
	category: 'theme',
	icon: ICON,
	keywords: [ 'menu', 'navigation', 'mega' ],
	supports: { align: [ 'wide', 'full' ], html: false },
	attributes: {
		menuId: { type: 'number', default: 0 },
		theme: { type: 'string', default: '' },
		trigger: { type: 'string', default: '' },
		mobileMode: { type: 'string', default: '' },
		align: { type: 'string', default: '' },
	},
	edit: function Edit( { attributes, setAttributes } ) {
		const props = useBlockProps();
		const menus = useSelect( ( select ) => select( 'core' ).getMenus?.( { per_page: -1 } ) || select( 'core' ).getEntityRecords( 'root', 'menu', { per_page: -1 } ) || [], [] );
		const options = [ { value: 0, label: __( '— Choose a menu —', 'dev3-ai-mega-menu' ) }, ...( menus || [] ).map( ( m ) => ( { value: m.id, label: m.name } ) ) ];
		const themes = ( window.dev3mmBlock && window.dev3mmBlock.themes ) || [];
		return (
			<div { ...props }>
				<InspectorControls>
					<PanelBody title={ __( 'Menu', 'dev3-ai-mega-menu' ) }>
						<SelectControl label={ __( 'Menu', 'dev3-ai-mega-menu' ) } value={ attributes.menuId } options={ options } onChange={ ( v ) => setAttributes( { menuId: Number( v ) } ) } />
						<SelectControl
							label={ __( 'Style', 'dev3-ai-mega-menu' ) }
							value={ attributes.theme }
							options={ [ { value: '', label: __( 'Default (from Locations)', 'dev3-ai-mega-menu' ) }, ...themes.map( ( t ) => ( { value: t.id, label: t.name } ) ) ] }
							onChange={ ( v ) => setAttributes( { theme: v } ) }
						/>
						<SelectControl
							label={ __( 'Open submenus on', 'dev3-ai-mega-menu' ) }
							value={ attributes.trigger }
							options={ [
								{ value: '', label: __( 'Default', 'dev3-ai-mega-menu' ) },
								{ value: 'hover', label: __( 'Hover', 'dev3-ai-mega-menu' ) },
								{ value: 'click', label: __( 'Click', 'dev3-ai-mega-menu' ) },
							] }
							onChange={ ( v ) => setAttributes( { trigger: v } ) }
						/>
						<SelectControl
							label={ __( 'Mobile menu', 'dev3-ai-mega-menu' ) }
							value={ attributes.mobileMode }
							options={ [
								{ value: '', label: __( 'Default', 'dev3-ai-mega-menu' ) },
								{ value: 'accordion', label: __( 'Accordion', 'dev3-ai-mega-menu' ) },
								{ value: 'offcanvas', label: __( 'Off-canvas', 'dev3-ai-mega-menu' ) },
							] }
							onChange={ ( v ) => setAttributes( { mobileMode: v } ) }
						/>
					</PanelBody>
				</InspectorControls>
				{ attributes.menuId ? (
					<ServerSideRender block="dev3mm/mega-menu" attributes={ attributes } />
				) : (
					<Placeholder icon={ ICON } label={ __( 'DEV3 AI Mega Menu', 'dev3-ai-mega-menu' ) } instructions={ __( 'Choose the menu to show. Build and style menus under DEV3 AI Mega Menu in the dashboard.', 'dev3-ai-mega-menu' ) }>
						<SelectControl label={ __( 'Menu', 'dev3-ai-mega-menu' ) } hideLabelFromVision value={ attributes.menuId } options={ options } onChange={ ( v ) => setAttributes( { menuId: Number( v ) } ) } />
					</Placeholder>
				) }
			</div>
		);
	},
	save: () => null,
} );
