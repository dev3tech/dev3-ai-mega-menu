import { __ } from '@wordpress/i18n';
import { Button, Notice, PageHeader, Tabs, useAiStatus, useHashRoute } from '../core3/ui';
import Audit from './ai/Audit';
import BuildMenu from './ai/BuildMenu';
import IconsTool from './ai/IconsTool';
import Labels from './ai/Labels';
import Promo from './ai/Promo';
import { useCurrentMenu, useMenus } from './shared';

export const TOOLS = [
	{ id: 'build', label: __( 'Build my menu', 'dev3-ai-mega-menu' ) },
	{ id: 'labels', label: __( 'Labels & descriptions', 'dev3-ai-mega-menu' ) },
	{ id: 'audit', label: __( 'UX audit', 'dev3-ai-mega-menu' ) },
	{ id: 'promo', label: __( 'Promo panel', 'dev3-ai-mega-menu' ) },
	{ id: 'icons', label: __( 'Icons & images', 'dev3-ai-mega-menu' ) },
];

/**
 * AI Studio: the five DEV3 AI tools. Every result is a draft you accept, edit or reject.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.settings Settings.
 * @param {Function} props.onUsage  Usage changed.
 * @return {Element} Screen.
 */
export default function AiStudio( { settings, onUsage, refreshKey } ) {
	const [ route, navigate ] = useHashRoute( [ 'ai' ], 'ai' );
	const tool = TOOLS.some( ( t ) => t.id === route.params[ 0 ] ) ? route.params[ 0 ] : 'build';
	const { menus, reload } = useMenus();
	const [ menuId, setMenuId ] = useCurrentMenu( menus );
	const status = useAiStatus( refreshKey );
	const aiReady = !! settings.api_key_set && ( ! status || ( status.enabled !== false && ! status.cap_reached ) );
	const shared = { menus, menuId, setMenuId, onUsage, aiReady, reload };

	return (
		<>
			<PageHeader eyebrow={ __( 'DEV3 AI', 'dev3-ai-mega-menu' ) } title={ __( 'AI Studio', 'dev3-ai-mega-menu' ) } description={ __( 'Build, label, audit and decorate menus with DEV3 AI. Nothing changes on your site until you accept a draft.', 'dev3-ai-mega-menu' ) } />
			{ ! settings.api_key_set && (
				<Notice tone="ai" title={ __( 'Connect DEV3 AI', 'dev3-ai-mega-menu' ) } actions={ <Button size="sm" variant="primary" href="#/api">{ __( 'Add your API key', 'dev3-ai-mega-menu' ) }</Button> }>
					{ __( 'Add a Google Gemini API key to use the AI tools. The automatic builder, quick audit, keyword icons and promo templates work without it.', 'dev3-ai-mega-menu' ) }
				</Notice>
			) }
			{ status && status.cap_reached && <Notice tone="warning" title={ __( 'AI paused: monthly budget reached', 'dev3-ai-mega-menu' ) }>{ __( 'Raise the monthly cap in API Config or wait for next month.', 'dev3-ai-mega-menu' ) }</Notice> }
			<Tabs tabs={ TOOLS } value={ tool } onChange={ ( id ) => navigate( `ai/${ id }` ) } label={ __( 'AI tools', 'dev3-ai-mega-menu' ) } variant="pills" />
			{ tool === 'build' && <BuildMenu { ...shared } /> }
			{ tool === 'labels' && <Labels { ...shared } /> }
			{ tool === 'audit' && <Audit { ...shared } /> }
			{ tool === 'promo' && <Promo { ...shared } /> }
			{ tool === 'icons' && <IconsTool { ...shared } /> }
		</>
	);
}
