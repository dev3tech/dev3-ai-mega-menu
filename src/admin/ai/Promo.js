import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../../core3/api';
import config from '../../core3/config';
import { AiButton, AiDraftReview, Button, Card, ErrorNotice, FormGrid, Inline, Notice, Select, TextField, useToast } from '../../core3/ui';
import { MenuSelect, escapeHtml } from '../shared';

const FIELDS = [
	{ key: 'badge', label: __( 'Badge', 'dev3-ai-mega-menu' ), maxLength: 24 },
	{ key: 'title', label: __( 'Headline', 'dev3-ai-mega-menu' ), maxLength: 60 },
	{ key: 'text', label: __( 'Text', 'dev3-ai-mega-menu' ), multiline: true, maxLength: 300 },
	{ key: 'cta_label', label: __( 'Button label', 'dev3-ai-mega-menu' ), maxLength: 40 },
];

/**
 * Static promo card preview with the real front-end classes.
 *
 * @param {Object} props            Props.
 * @param {Object} props.widget     Widget.
 * @param {Array}  props.candidates Products.
 * @return {Element} Preview.
 */
export function PromoPreview( { widget, candidates = [] } ) {
	const products = ( widget.product_ids || [] ).map( ( id ) => candidates.find( ( c ) => c.id === id ) ).filter( Boolean );
	return (
		<nav className="dev3mm dev3mm--bp-0 dev3mm-promo-preview" aria-label={ __( 'Promo preview', 'dev3-ai-mega-menu' ) }>
			<div className={ `dev3mm__promo dev3mm__promo--${ widget.tone || 'brand' }` }>
				<div className="dev3mm__promo-body">
					{ widget.badge && <span className="dev3mm__promo-badge">{ widget.badge }</span> }
					{ widget.title && <p className="dev3mm__promo-title">{ widget.title }</p> }
					{ widget.text && <p className="dev3mm__promo-text">{ widget.text }</p> }
					{ !! products.length && (
						<ul className="dev3mm__cards">
							{ products.map( ( p ) => (
								<li key={ p.id } className="dev3mm__card">
									<span className="dev3mm__card-link">
										{ p.image ? <img className="dev3mm__card-img" src={ p.image } alt="" /> : <span className="dev3mm__card-img" /> }
										<span className="dev3mm__card-body">
											<span className="dev3mm__card-title">{ p.name }</span>
											<span className="dev3mm__card-meta" dangerouslySetInnerHTML={ { __html: escapeHtml( p.price ) } } />
										</span>
									</span>
								</li>
							) ) }
						</ul>
					) }
					{ widget.cta_label && <span className="dev3mm__promo-cta">{ widget.cta_label }</span> }
				</div>
			</div>
		</nav>
	);
}

/**
 * AI promo panel: seasonal copy + featured products, inserted into a mega panel's side column on accept.
 *
 * @param {Object} props Props.
 * @return {Element} Tool.
 */
export default function Promo( { menus, menuId, setMenuId, onUsage, aiReady } ) {
	const toast = useToast();
	const [ items, setItems ] = useState( [] );
	const [ itemId, setItemId ] = useState( 0 );
	const [ cats, setCats ] = useState( [] );
	const [ opts, setOpts ] = useState( { occasion: '', offer: '', tone: 'friendly', source: config.woo ? 'sale' : 'recent', category: 0 } );
	const [ draft, setDraft ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! menuId ) {
			return;
		}
		request( `menus/${ menuId }` )
			.then( ( res ) => {
				setItems( res.tree );
				const mega = res.tree.find( ( n ) => n.children && n.children.length );
				setItemId( mega ? mega.id : res.tree[ 0 ] ? res.tree[ 0 ].id : 0 );
			} )
			.catch( () => setItems( [] ) );
	}, [ menuId ] );

	useEffect( () => {
		if ( config.woo ) {
			request( 'content?type=product_cat&per_page=50' )
				.then( setCats )
				.catch( () => setCats( [] ) );
		}
	}, [] );

	const run = ( ai ) => {
		setBusy( ai ? 'ai' : 'tpl' );
		setError( null );
		request( 'ai/promo', { method: 'POST', data: { ...opts, ai } } )
			.then( setDraft )
			.catch( setError )
			.finally( () => {
				setBusy( '' );
				if ( ai ) {
					onUsage();
				}
			} );
	};

	const accept = ( values ) => {
		setBusy( 'accept' );
		const widget = { ...draft.widget, ...values };
		request( `menus/${ menuId }/promo`, { method: 'POST', data: { item_id: itemId, widget } } )
			.then( () => {
				const item = items.find( ( n ) => n.id === itemId );
				toast.show( {
					tone: 'success',
					title: __( 'Promo added', 'dev3-ai-mega-menu' ),
					message: sprintf( /* translators: %s: item */ __( 'It now shows in the “%s” panel. Edit it any time in the Menu Builder (Panel tab).', 'dev3-ai-mega-menu' ), item ? item.title : '' ),
					action: { label: __( 'Open builder', 'dev3-ai-mega-menu' ), onClick: () => ( window.location.hash = '#/builder' ) },
				} );
				setDraft( null );
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( '' ) );
	};

	return (
		<Card tone="ai" title={ __( 'AI promo panel', 'dev3-ai-mega-menu' ) } description={ __( 'Generate a seasonal promo block (headline, text, button and featured products) for a mega panel’s side column.', 'dev3-ai-mega-menu' ) }>
			<FormGrid>
				<MenuSelect menus={ menus } value={ menuId } onChange={ setMenuId } />
				<Select label={ __( 'Show it in the panel of', 'dev3-ai-mega-menu' ) } value={ itemId } onChange={ ( v ) => setItemId( Number( v ) ) } options={ items.map( ( n ) => ( { value: n.id, label: n.title } ) ) } placeholder={ items.length ? undefined : __( 'This menu has no items', 'dev3-ai-mega-menu' ) } />
				<TextField label={ __( 'Occasion', 'dev3-ai-mega-menu' ) } value={ opts.occasion } onChange={ ( v ) => setOpts( { ...opts, occasion: v } ) } placeholder={ __( 'e.g. Black Friday, Summer, Back to school', 'dev3-ai-mega-menu' ) } help={ __( 'Leave empty to use the current season.', 'dev3-ai-mega-menu' ) } maxLength={ 120 } />
				<TextField label={ __( 'Real offer (optional)', 'dev3-ai-mega-menu' ) } value={ opts.offer } onChange={ ( v ) => setOpts( { ...opts, offer: v } ) } placeholder={ __( 'e.g. 20% off outerwear until Sunday', 'dev3-ai-mega-menu' ) } help={ __( 'DEV3 AI never invents discounts.', 'dev3-ai-mega-menu' ) } maxLength={ 160 } />
				<Select
					label={ __( 'Tone', 'dev3-ai-mega-menu' ) }
					value={ opts.tone }
					onChange={ ( v ) => setOpts( { ...opts, tone: v } ) }
					options={ [
						{ value: 'friendly', label: __( 'Friendly', 'dev3-ai-mega-menu' ) },
						{ value: 'premium', label: __( 'Premium', 'dev3-ai-mega-menu' ) },
						{ value: 'playful', label: __( 'Playful', 'dev3-ai-mega-menu' ) },
						{ value: 'urgent but honest', label: __( 'Urgent', 'dev3-ai-mega-menu' ) },
					] }
				/>
				{ config.woo && (
					<Select
						label={ __( 'Pick products from', 'dev3-ai-mega-menu' ) }
						value={ opts.source }
						onChange={ ( v ) => setOpts( { ...opts, source: v } ) }
						options={ [
							{ value: 'sale', label: __( 'On sale', 'dev3-ai-mega-menu' ) },
							{ value: 'featured', label: __( 'Featured', 'dev3-ai-mega-menu' ) },
							{ value: 'best', label: __( 'Best sellers', 'dev3-ai-mega-menu' ) },
							{ value: 'recent', label: __( 'Newest', 'dev3-ai-mega-menu' ) },
							{ value: 'category', label: __( 'A category', 'dev3-ai-mega-menu' ) },
						] }
					/>
				) }
				{ config.woo && opts.source === 'category' && <Select label={ __( 'Product category', 'dev3-ai-mega-menu' ) } value={ opts.category } onChange={ ( v ) => setOpts( { ...opts, category: Number( v ) } ) } options={ cats.map( ( c ) => ( { value: c.id, label: c.title } ) ) } placeholder={ __( 'Choose…', 'dev3-ai-mega-menu' ) } /> }
			</FormGrid>
			<Inline gap={ 8 }>
				<AiButton onClick={ () => run( true ) } loading={ busy === 'ai' } disabled={ !! busy || ! aiReady } loadingText={ __( 'Writing promo…', 'dev3-ai-mega-menu' ) }>
					{ __( 'Generate with DEV3 AI', 'dev3-ai-mega-menu' ) }
				</AiButton>
				<Button onClick={ () => run( false ) } loading={ busy === 'tpl' } disabled={ !! busy }>
					{ __( 'Use a template', 'dev3-ai-mega-menu' ) }
				</Button>
			</Inline>
			{ ! config.woo && <Notice tone="info">{ __( 'Install WooCommerce to feature products in promos. The text and button work on any site.', 'dev3-ai-mega-menu' ) }</Notice> }
			<ErrorNotice error={ error } onRetry={ () => run( true ) } onDismiss={ () => setError( null ) } />
			{ draft && (
				<div className="dev3mm-promo-review">
					<AiDraftReview
						fields={ FIELDS }
						draft={ { badge: draft.widget.badge, title: draft.widget.title, text: draft.widget.text, cta_label: draft.widget.cta_label } }
						meta={ draft.meta ? { model: draft.meta.model, tokens: draft.meta.tokens, cached: draft.meta.cached } : undefined }
						onAccept={ accept }
						onReject={ () => setDraft( null ) }
						onRegenerate={ () => run( true ) }
						regenerating={ busy === 'ai' }
						accepting={ busy === 'accept' }
						acceptLabel={ __( 'Accept and add to panel', 'dev3-ai-mega-menu' ) }
					/>
					<div>
						<span className="dev3mm-ui-label">{ __( 'Preview', 'dev3-ai-mega-menu' ) }</span>
						<PromoPreview widget={ draft.widget } candidates={ draft.candidates } />
						<p className="dev3mm-muted">{ sprintf( /* translators: %s: url */ __( 'Button links to %s', 'dev3-ai-mega-menu' ), draft.widget.cta_url || '—' ) }</p>
					</div>
				</div>
			) }
		</Card>
	);
}
