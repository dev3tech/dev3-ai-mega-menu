import { __ } from '@wordpress/i18n';
import { Badge, Card, Checkbox, ColorField, EmptyState, FormGrid, NumberField, Select, Tabs, Textarea, TextField, Toggle } from '../core3/ui';
import { useState } from '@wordpress/element';
import PanelEditor from './PanelEditor';
import { IconPicker, LAYOUT_LABELS, LAYOUT_TONES, MediaPicker } from './shared';
import { layoutOf } from './tree';

/**
 * Settings for the selected item: content, display (icon, image, badge), and the panel for top-level items.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.node     Node.
 * @param {number}   props.depth    Depth.
 * @param {Function} props.onChange ( patch ) => void for node fields; config patches via props.onConfig.
 * @param {Function} props.onConfig ( patch ) => void.
 * @return {Element} Editor.
 */
export default function ItemEditor( { node, depth, onChange, onConfig } ) {
	const [ tab, setTab ] = useState( 'content' );
	if ( ! node ) {
		return (
			<Card title={ __( 'Item settings', 'dev3-ai-mega-menu' ) }>
				<EmptyState compact icon="edit" title={ __( 'Select an item', 'dev3-ai-mega-menu' ) } description={ __( 'Choose an item in the structure to edit its label, icon, badge and mega panel.', 'dev3-ai-mega-menu' ) } />
			</Card>
		);
	}
	const c = node.config;
	const top = depth === 0;
	const layout = layoutOf( node, depth );
	const tabs = [
		{ id: 'content', label: __( 'Content', 'dev3-ai-mega-menu' ) },
		{ id: 'display', label: __( 'Display', 'dev3-ai-mega-menu' ) },
		...( top ? [ { id: 'panel', label: __( 'Panel', 'dev3-ai-mega-menu' ) } ] : [] ),
	];
	const active = tabs.some( ( t ) => t.id === tab ) ? tab : 'content';

	return (
		<Card
			title={ node.title || __( '(no label)', 'dev3-ai-mega-menu' ) }
			description={ node.type === 'custom' ? __( 'Custom link', 'dev3-ai-mega-menu' ) : node.type_label || node.object }
			actions={ <Badge tone={ LAYOUT_TONES[ layout ] }>{ LAYOUT_LABELS[ layout ] }</Badge> }
			className="dev3mm-editor"
		>
			<Tabs tabs={ tabs } value={ active } onChange={ setTab } label={ __( 'Item settings', 'dev3-ai-mega-menu' ) } />
			{ active === 'content' && (
				<div className="dev3mm-stack">
					<TextField label={ __( 'Label', 'dev3-ai-mega-menu' ) } value={ node.title } onChange={ ( v ) => onChange( { title: v } ) } maxLength={ 120 } required />
					{ node.type === 'custom' && <TextField label={ __( 'URL', 'dev3-ai-mega-menu' ) } value={ node.url } onChange={ ( v ) => onChange( { url: v } ) } help={ __( 'Use # for a heading that only opens its submenu.', 'dev3-ai-mega-menu' ) } /> }
					{ node.invalid && <Badge tone="danger">{ __( 'The linked content is missing or unpublished', 'dev3-ai-mega-menu' ) }</Badge> }
					<Textarea label={ __( 'Description', 'dev3-ai-mega-menu' ) } value={ node.description } onChange={ ( v ) => onChange( { description: v } ) } rows={ 2 } maxLength={ 300 } help={ __( 'Shown under the label when “Show description” is on (Display tab).', 'dev3-ai-mega-menu' ) } />
					<FormGrid>
						<TextField label={ __( 'Title attribute', 'dev3-ai-mega-menu' ) } value={ node.attr_title } onChange={ ( v ) => onChange( { attr_title: v } ) } />
						<TextField label={ __( 'CSS classes', 'dev3-ai-mega-menu' ) } value={ node.classes } onChange={ ( v ) => onChange( { classes: v } ) } />
					</FormGrid>
					<Checkbox label={ __( 'Open in a new tab', 'dev3-ai-mega-menu' ) } checked={ node.target === '_blank' } onChange={ ( v ) => onChange( { target: v ? '_blank' : '' } ) } />
				</div>
			) }
			{ active === 'display' && (
				<div className="dev3mm-stack">
					<FormGrid>
						<IconPicker value={ c.icon } onChange={ ( v ) => onConfig( { icon: v } ) } suggest={ node.title } />
						<MediaPicker value={ c.image_id } onChange={ ( id ) => onConfig( { image_id: id } ) } label={ depth === 1 ? __( 'Column image', 'dev3-ai-mega-menu' ) : __( 'Image (thumbnail)', 'dev3-ai-mega-menu' ) } />
					</FormGrid>
					<FormGrid>
						<TextField label={ __( 'Badge text', 'dev3-ai-mega-menu' ) } value={ c.badge.text } onChange={ ( v ) => onConfig( { badge: { ...c.badge, text: v } } ) } maxLength={ 16 } placeholder={ __( 'e.g. New, Sale', 'dev3-ai-mega-menu' ) } />
						<Select
							label={ __( 'Badge style', 'dev3-ai-mega-menu' ) }
							value={ c.badge.tone }
							onChange={ ( v ) => onConfig( { badge: { ...c.badge, tone: v } } ) }
							options={ [
								{ value: 'new', label: __( 'New (green)', 'dev3-ai-mega-menu' ) },
								{ value: 'sale', label: __( 'Sale (red)', 'dev3-ai-mega-menu' ) },
								{ value: 'hot', label: __( 'Hot (orange)', 'dev3-ai-mega-menu' ) },
								{ value: 'info', label: __( 'Info (blue)', 'dev3-ai-mega-menu' ) },
								{ value: 'custom', label: __( 'Custom colour', 'dev3-ai-mega-menu' ) },
							] }
						/>
						{ c.badge.tone === 'custom' && <ColorField label={ __( 'Badge colour', 'dev3-ai-mega-menu' ) } value={ c.badge.color } onChange={ ( v ) => onConfig( { badge: { ...c.badge, color: v } } ) } /> }
					</FormGrid>
					<div className="dev3mm-toggles">
						<Toggle label={ __( 'Show description', 'dev3-ai-mega-menu' ) } checked={ c.show_description } onChange={ ( v ) => onConfig( { show_description: v } ) } />
						<Toggle label={ __( 'Hide label (icon only)', 'dev3-ai-mega-menu' ) } help={ __( 'The label stays available to screen readers.', 'dev3-ai-mega-menu' ) } checked={ c.hide_label } onChange={ ( v ) => onConfig( { hide_label: v } ) } />
						<Toggle label={ __( 'Hide on mobile', 'dev3-ai-mega-menu' ) } checked={ c.hide_on_mobile } onChange={ ( v ) => onConfig( { hide_on_mobile: v } ) } />
						<Toggle label={ __( 'Hide on desktop', 'dev3-ai-mega-menu' ) } checked={ c.hide_on_desktop } onChange={ ( v ) => onConfig( { hide_on_desktop: v } ) } />
					</div>
				</div>
			) }
			{ active === 'panel' && top && (
				<div className="dev3mm-stack">
					<FormGrid>
						<Select
							label={ __( 'Submenu type', 'dev3-ai-mega-menu' ) }
							value={ c.type }
							onChange={ ( v ) => onConfig( { type: v } ) }
							options={ [
								{ value: 'auto', label: __( 'Automatic', 'dev3-ai-mega-menu' ) },
								{ value: 'flyout', label: __( 'Flyout dropdown', 'dev3-ai-mega-menu' ) },
								{ value: 'mega', label: __( 'Mega panel (columns)', 'dev3-ai-mega-menu' ) },
								{ value: 'tabs', label: __( 'Mega panel with tabs', 'dev3-ai-mega-menu' ) },
							] }
							help={ __( 'Automatic: a mega panel when children have their own children, else a flyout.', 'dev3-ai-mega-menu' ) }
						/>
						{ ( layout === 'mega' || layout === 'tabs' ) && <NumberField label={ __( 'Columns', 'dev3-ai-mega-menu' ) } value={ c.columns } min={ 1 } max={ 6 } onChange={ ( v ) => onConfig( { columns: v || 1 } ) } /> }
						{ ( layout === 'mega' || layout === 'tabs' ) && (
							<Select
								label={ __( 'Panel width', 'dev3-ai-mega-menu' ) }
								value={ c.panel_width }
								onChange={ ( v ) => onConfig( { panel_width: v } ) }
								options={ [
									{ value: 'inherit', label: __( 'Location default', 'dev3-ai-mega-menu' ) },
									{ value: 'full', label: __( 'Full window width', 'dev3-ai-mega-menu' ) },
									{ value: 'container', label: __( 'Menu bar width', 'dev3-ai-mega-menu' ) },
									{ value: 'auto', label: __( 'Fit content', 'dev3-ai-mega-menu' ) },
								] }
							/>
						) }
					</FormGrid>
					{ layout === 'tabs' && <p className="dev3mm-muted">{ __( 'Each child item becomes a tab; its own children fill the tab panel.', 'dev3-ai-mega-menu' ) }</p> }
					{ layout === 'flyout' && <p className="dev3mm-muted">{ __( 'Child items open as a dropdown; deeper levels fly out to the side.', 'dev3-ai-mega-menu' ) }</p> }
					{ layout === 'link' && c.type === 'auto' && <p className="dev3mm-muted">{ __( 'Add child items, or set the type to Mega panel and add widgets below.', 'dev3-ai-mega-menu' ) }</p> }
					{ ( layout === 'mega' || c.type === 'mega' ) && <PanelEditor config={ c } onChange={ onConfig } children={ node.children || [] } /> }
				</div>
			) }
		</Card>
	);
}
