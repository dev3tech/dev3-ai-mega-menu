import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import { Badge, Button, Card, Columns, ErrorNotice, Icon, Notice, PageHeader, SkeletonCard, Stat, StatGrid, cx } from '../core3/ui';

/**
 * Dashboard: status, quick start and the DEV3 AI tools.
 *
 * @param {Object} props          Props.
 * @param {Object} props.settings Settings.
 * @return {Element} Dashboard.
 */
export default function Dashboard( { settings } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const load = () =>
		request( 'overview' )
			.then( setData )
			.catch( setError );
	useEffect( () => {
		load();
	}, [] );

	if ( error ) {
		return <ErrorNotice error={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<>
				<PageHeader title={ __( 'Dashboard', 'dev3-ai-mega-menu' ) } />
				<SkeletonCard lines={ 4 } />
			</>
		);
	}
	const audit = data.last_audit && ! Array.isArray( data.last_audit ) ? data.last_audit : {};
	const live = data.active > 0 || data.block_replace !== 'off';
	const steps = [
		{ done: data.menus > 0, title: __( 'Create or build a menu', 'dev3-ai-mega-menu' ), text: __( 'Start from scratch or let DEV3 AI build one from your content.', 'dev3-ai-mega-menu' ), href: data.menus ? '#/builder' : '#/ai/build', cta: data.menus ? __( 'Open builder', 'dev3-ai-mega-menu' ) : __( 'Build my menu', 'dev3-ai-mega-menu' ) },
		{ done: data.mega_items > 0, title: __( 'Add a mega panel', 'dev3-ai-mega-menu' ), text: __( 'Turn a top item into columns, tabs, products or a promo.', 'dev3-ai-mega-menu' ), href: '#/builder', cta: __( 'Edit panels', 'dev3-ai-mega-menu' ) },
		{ done: live, title: data.block_theme ? __( 'Show it in your header', 'dev3-ai-mega-menu' ) : __( 'Turn on a location', 'dev3-ai-mega-menu' ), text: data.block_theme ? __( 'Add the block or replace the Navigation block.', 'dev3-ai-mega-menu' ) : __( 'Replace your theme’s menu with the mega menu.', 'dev3-ai-mega-menu' ), href: '#/locations', cta: __( 'Locations', 'dev3-ai-mega-menu' ) },
		{ done: !! audit.at, title: __( 'Audit the menu', 'dev3-ai-mega-menu' ), text: __( 'Check depth, labels, broken links and missing pages.', 'dev3-ai-mega-menu' ), href: '#/ai/audit', cta: __( 'Run audit', 'dev3-ai-mega-menu' ) },
	];
	const done = steps.filter( ( s ) => s.done ).length;
	const tools = [
		{ id: 'build', icon: 'sparkles', title: __( 'Build my menu', 'dev3-ai-mega-menu' ), text: __( 'A full mega menu from your pages, categories and products.', 'dev3-ai-mega-menu' ) },
		{ id: 'labels', icon: 'edit', title: __( 'Labels & descriptions', 'dev3-ai-mega-menu' ), text: __( 'Clear labels and one-line descriptions in your language.', 'dev3-ai-mega-menu' ) },
		{ id: 'audit', icon: 'check', title: __( 'UX audit', 'dev3-ai-mega-menu' ), text: __( 'Too deep, unclear labels, missing links, with one-click fixes.', 'dev3-ai-mega-menu' ) },
		{ id: 'promo', icon: 'bolt', title: __( 'Promo panel', 'dev3-ai-mega-menu' ), text: __( 'Seasonal promo blocks with featured products.', 'dev3-ai-mega-menu' ) },
		{ id: 'icons', icon: 'columns', title: __( 'Icons & images', 'dev3-ai-mega-menu' ), text: __( 'Matching icons and images for every item.', 'dev3-ai-mega-menu' ) },
	];

	return (
		<>
			<PageHeader
				title={ __( 'Dashboard', 'dev3-ai-mega-menu' ) }
				description={ __( 'Accessible mega menus for any theme, designed and checked with DEV3 AI.', 'dev3-ai-mega-menu' ) }
				actions={
					<>
						<Button icon="menu" href="#/builder">
							{ __( 'Menu Builder', 'dev3-ai-mega-menu' ) }
						</Button>
						<Button variant="ai" icon="sparkles" href="#/ai/build">
							{ __( 'Build with DEV3 AI', 'dev3-ai-mega-menu' ) }
						</Button>
					</>
				}
			/>

			{ ! settings.api_key_set && (
				<Notice tone="ai" title={ __( 'Connect DEV3 AI', 'dev3-ai-mega-menu' ) } actions={ <Button size="sm" variant="primary" href="#/api">{ __( 'Add your API key', 'dev3-ai-mega-menu' ) }</Button> }>
					{ __( 'Add a Google Gemini API key to turn on the AI tools. Menus, panels, styles and the quick audit all work without it.', 'dev3-ai-mega-menu' ) }
				</Notice>
			) }

			<StatGrid>
				<Stat label={ __( 'Menus', 'dev3-ai-mega-menu' ) } value={ String( data.menus ) } hint={ sprintf( /* translators: %d: items */ __( '%d items in total', 'dev3-ai-mega-menu' ), data.items ) } icon="menu" tone="brand" />
				<Stat label={ __( 'Mega panels', 'dev3-ai-mega-menu' ) } value={ String( data.mega_items ) } hint={ sprintf( /* translators: %d: promos */ __( '%d with a promo', 'dev3-ai-mega-menu' ), data.promos ) } icon="columns" />
				<Stat
					label={ __( 'Live locations', 'dev3-ai-mega-menu' ) }
					value={ data.block_replace !== 'off' ? __( 'Block nav', 'dev3-ai-mega-menu' ) : `${ data.active } / ${ data.locations }` }
					hint={ <Badge tone={ live ? 'success' : 'neutral' } dot>{ live ? __( 'On your site', 'dev3-ai-mega-menu' ) : __( 'Not live yet', 'dev3-ai-mega-menu' ) }</Badge> }
					icon="bolt"
					tone={ live ? 'success' : 'neutral' }
				/>
				<Stat
					label={ __( 'Last UX score', 'dev3-ai-mega-menu' ) }
					value={ audit.at ? `${ audit.score }/100` : '—' }
					hint={ audit.at ? sprintf( /* translators: %d: issues */ __( '%d issues found', 'dev3-ai-mega-menu' ), audit.issues ) : __( 'Run your first audit', 'dev3-ai-mega-menu' ) }
					icon="check"
					tone={ ! audit.at ? 'neutral' : audit.score >= 80 ? 'success' : audit.score >= 60 ? 'warning' : 'danger' }
				/>
			</StatGrid>

			<Columns min={ 340 }>
				<Card title={ __( 'Quick start', 'dev3-ai-mega-menu' ) } description={ sprintf( /* translators: 1: done 2: total */ __( '%1$d of %2$d done', 'dev3-ai-mega-menu' ), done, steps.length ) }>
					<ol className="dev3mm-steps">
						{ steps.map( ( s, i ) => (
							<li key={ i } className={ cx( 'dev3mm-step', s.done && 'is-done' ) }>
								<span className="dev3mm-step__mark" aria-hidden="true">
									{ s.done ? <Icon name="check" size={ 14 } /> : i + 1 }
								</span>
								<span className="dev3mm-step__body">
									<strong>{ s.title }</strong>
									<span className="dev3mm-muted">{ s.text }</span>
								</span>
								<Button size="sm" variant={ s.done ? 'ghost' : 'secondary' } href={ s.href }>
									{ s.cta }
									{ s.done && <span className="screen-reader-text">{ __( '(done)', 'dev3-ai-mega-menu' ) }</span> }
								</Button>
							</li>
						) ) }
					</ol>
				</Card>
				<Card tone="ai" title={ __( 'DEV3 AI tools', 'dev3-ai-mega-menu' ) } description={ __( 'Every result is a draft: you accept, edit or reject it.', 'dev3-ai-mega-menu' ) }>
					<ul className="dev3mm-tools">
						{ tools.map( ( t ) => (
							<li key={ t.id }>
								<a className="dev3mm-tool" href={ `#/ai/${ t.id }` }>
									<span className="dev3mm-tool__icon" aria-hidden="true">
										<Icon name={ t.icon } size={ 18 } />
									</span>
									<span className="dev3mm-tool__body">
										<strong>{ t.title }</strong>
										<span className="dev3mm-muted">{ t.text }</span>
									</span>
									<Icon name="chevronRight" size={ 16 } />
								</a>
							</li>
						) ) }
					</ul>
				</Card>
			</Columns>

			<Card title={ __( 'Compatibility', 'dev3-ai-mega-menu' ) } description={ sprintf( /* translators: %s: theme */ __( 'Active theme: %s', 'dev3-ai-mega-menu' ), data.theme_name ) }>
				<div className="dev3mm-compat">
					<Badge tone="success" dot>
						{ data.block_theme ? __( 'Block theme: use the block or Navigation replacement', 'dev3-ai-mega-menu' ) : __( 'Classic theme: theme locations', 'dev3-ai-mega-menu' ) }
					</Badge>
					<Badge tone={ data.woocommerce ? 'success' : 'neutral' } dot>
						{ data.woocommerce ? __( 'WooCommerce: product widgets on', 'dev3-ai-mega-menu' ) : __( 'WooCommerce not active', 'dev3-ai-mega-menu' ) }
					</Badge>
					<Badge tone={ data.elementor ? 'success' : 'neutral' } dot>
						{ data.elementor ? __( 'Elementor widget ready', 'dev3-ai-mega-menu' ) : __( 'Elementor not active', 'dev3-ai-mega-menu' ) }
					</Badge>
					<Badge tone={ data.wpbakery ? 'success' : 'neutral' } dot>
						{ data.wpbakery ? __( 'WPBakery element ready', 'dev3-ai-mega-menu' ) : __( 'WPBakery not active', 'dev3-ai-mega-menu' ) }
					</Badge>
					<Badge tone="success" dot>
						{ __( 'Keyboard & screen-reader accessible (WCAG 2.2 AA)', 'dev3-ai-mega-menu' ) }
					</Badge>
				</div>
			</Card>
		</>
	);
}
