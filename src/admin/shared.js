import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../core3/api';
import config from '../core3/config';
import { Button, cx, Icon, Modal, Select, Skeleton, TextField } from '../core3/ui';
import ICONS from '../../assets/icons.json';

export { ICONS };
export const ICON_NAMES = Object.keys( ICONS );

/**
 * Menu icon from the plugin icon set (assets/icons.json).
 *
 * @param {Object} props      Props.
 * @param {string} props.name Icon name.
 * @param {number} props.size Size.
 * @return {Element|null} Svg.
 */
export function MenuIcon( { name, size = 18, className } ) {
	const icon = ICONS[ name ];
	if ( ! icon ) {
		return null;
	}
	return (
		<svg className={ cx( 'dev3mm-mi', className ) } width={ size } height={ size } viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
			<path d={ icon.d } />
		</svg>
	);
}

/**
 * Keyword matcher (mirrors Icons::match() in PHP, used for instant suggestions).
 *
 * @param {string} label Label.
 * @return {string} Icon name or ''.
 */
export function matchIcon( label ) {
	const words = String( label || '' ).toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).split( /[^a-z0-9]+/ ).filter( Boolean );
	let best = '';
	let score = 0;
	ICON_NAMES.forEach( ( name ) => {
		const keys = ( ICONS[ name ].keywords || '' ).split( /\s+/ );
		let hit = 0;
		words.forEach( ( word, i ) => {
			const singular = word.replace( /s+$/, '' );
			if ( keys.some( ( k ) => k === word || ( singular.length > 2 && k.replace( /s+$/, '' ) === singular ) ) ) {
				hit += i === 0 ? 3 : 2;
			}
		} );
		if ( hit > score ) {
			score = hit;
			best = name;
		}
	} );
	return best;
}

/**
 * Icon picker: a searchable grid in a modal.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.value    Icon.
 * @param {Function} props.onChange Change.
 * @param {string}   props.label    Label.
 * @param {string}   props.suggest  Label used for the "Suggest" button.
 * @return {Element} Picker.
 */
export function IconPicker( { value, onChange, label = __( 'Icon', 'dev3-ai-mega-menu' ), suggest } ) {
	const [ open, setOpen ] = useState( false );
	const [ q, setQ ] = useState( '' );
	const list = ICON_NAMES.filter( ( n ) => ! q || n.includes( q.toLowerCase() ) || ( ICONS[ n ].keywords || '' ).includes( q.toLowerCase() ) || ICONS[ n ].label.toLowerCase().includes( q.toLowerCase() ) );
	const suggested = suggest ? matchIcon( suggest ) : '';
	return (
		<div className="dev3mm-field-block">
			<span className="dev3mm-ui-label" id="dev3mm-icon-label">
				{ label }
			</span>
			<div className="dev3mm-icon-row">
				<span className={ cx( 'dev3mm-icon-preview', ! value && 'is-empty' ) } aria-hidden="true">
					{ value ? <MenuIcon name={ value } size={ 22 } /> : <Icon name="plus" size={ 16 } /> }
				</span>
				<Button size="sm" onClick={ () => setOpen( true ) } aria-describedby="dev3mm-icon-label">
					{ value ? sprintf( /* translators: %s: icon name */ __( 'Change (%s)', 'dev3-ai-mega-menu' ), ICONS[ value ] ? ICONS[ value ].label : value ) : __( 'Choose icon', 'dev3-ai-mega-menu' ) }
				</Button>
				{ suggested && suggested !== value && (
					<Button size="sm" variant="ghost" onClick={ () => onChange( suggested ) }>
						{ sprintf( /* translators: %s: icon label */ __( 'Use “%s”', 'dev3-ai-mega-menu' ), ICONS[ suggested ].label ) }
					</Button>
				) }
				{ value && (
					<Button size="sm" variant="ghost" onClick={ () => onChange( '' ) }>
						{ __( 'Remove', 'dev3-ai-mega-menu' ) }
					</Button>
				) }
			</div>
			<Modal open={ open } onClose={ () => setOpen( false ) } title={ __( 'Choose an icon', 'dev3-ai-mega-menu' ) } size="lg">
				<TextField label={ __( 'Search icons', 'dev3-ai-mega-menu' ) } value={ q } onChange={ setQ } placeholder={ __( 'e.g. shop, phone, gift', 'dev3-ai-mega-menu' ) } data-autofocus />
				<div className="dev3mm-icon-grid" role="listbox" aria-label={ __( 'Icons', 'dev3-ai-mega-menu' ) }>
					{ list.map( ( name ) => (
						<button
							key={ name }
							type="button"
							role="option"
							aria-selected={ value === name }
							className={ cx( 'dev3mm-icon-cell', value === name && 'is-selected' ) }
							onClick={ () => {
								onChange( name );
								setOpen( false );
							} }
						>
							<MenuIcon name={ name } size={ 22 } />
							<span>{ ICONS[ name ].label }</span>
						</button>
					) ) }
				</div>
				{ ! list.length && <p className="dev3mm-muted">{ __( 'No icons match.', 'dev3-ai-mega-menu' ) }</p> }
			</Modal>
		</div>
	);
}

/**
 * WordPress media library picker (wp.media).
 *
 * @param {Object}   props          Props.
 * @param {number}   props.value    Attachment id.
 * @param {Function} props.onChange ( id, url ) => void.
 * @param {string}   props.label    Label.
 * @param {string}   props.url      Known preview url.
 * @return {Element} Picker.
 */
export function MediaPicker( { value, onChange, label = __( 'Image', 'dev3-ai-mega-menu' ), url: knownUrl } ) {
	const [ url, setUrl ] = useState( knownUrl || '' );
	useEffect( () => {
		if ( ! value ) {
			setUrl( '' );
			return;
		}
		if ( knownUrl ) {
			setUrl( knownUrl );
			return;
		}
		const wp = window.wp;
		if ( wp && wp.media && wp.media.attachment ) {
			const att = wp.media.attachment( value );
			att.fetch().then( () => {
				const sizes = att.get( 'sizes' ) || {};
				setUrl( ( sizes.thumbnail || sizes.medium || {} ).url || att.get( 'url' ) || '' );
			} );
		}
	}, [ value, knownUrl ] );
	const pick = () => {
		const wp = window.wp;
		if ( ! wp || ! wp.media ) {
			return;
		}
		const frame = wp.media( { title: label, library: { type: 'image' }, multiple: false, button: { text: __( 'Use this image', 'dev3-ai-mega-menu' ) } } );
		frame.on( 'select', () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			const thumb = ( att.sizes && ( att.sizes.thumbnail || att.sizes.medium ) ) || att;
			setUrl( thumb.url );
			onChange( att.id, thumb.url );
		} );
		frame.open();
	};
	return (
		<div className="dev3mm-field-block">
			<span className="dev3mm-ui-label">{ label }</span>
			<div className="dev3mm-icon-row">
				<span className={ cx( 'dev3mm-media-preview', ! value && 'is-empty' ) }>{ value && url ? <img src={ url } alt="" /> : <Icon name="file" size={ 16 } /> }</span>
				<Button size="sm" onClick={ pick }>
					{ value ? __( 'Replace image', 'dev3-ai-mega-menu' ) : __( 'Choose image', 'dev3-ai-mega-menu' ) }
				</Button>
				{ !! value && (
					<Button size="sm" variant="ghost" onClick={ () => onChange( 0, '' ) }>
						{ __( 'Remove', 'dev3-ai-mega-menu' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

/**
 * Loads nav menus.
 *
 * @return {Object} { menus, loading, error, reload }.
 */
export function useMenus() {
	const [ menus, setMenus ] = useState( null );
	const [ error, setError ] = useState( null );
	const reload = useCallback( () => {
		setError( null );
		return request( 'menus' )
			.then( ( rows ) => {
				setMenus( rows );
				return rows;
			} )
			.catch( setError );
	}, [] );
	useEffect( () => {
		reload();
	}, [ reload ] );
	return { menus, loading: menus === null && ! error, error, reload };
}

/**
 * Menu selector bound to useMenus().
 *
 * @param {Object}   props          Props.
 * @param {Array}    props.menus    Menus.
 * @param {number}   props.value    Menu id.
 * @param {Function} props.onChange Change.
 * @return {Element} Select.
 */
export function MenuSelect( { menus, value, onChange, label = __( 'Menu', 'dev3-ai-mega-menu' ), ...rest } ) {
	if ( ! menus ) {
		return <Skeleton height={ 40 } />;
	}
	return (
		<Select
			label={ label }
			value={ value || '' }
			onChange={ ( v ) => onChange( Number( v ) ) }
			placeholder={ menus.length ? __( 'Choose a menu…', 'dev3-ai-mega-menu' ) : __( 'No menus yet', 'dev3-ai-mega-menu' ) }
			options={ menus.map( ( m ) => ( { value: m.id, label: `${ m.name } (${ m.count })` } ) ) }
			{ ...rest }
		/>
	);
}

/**
 * Remembers the menu last worked on (per browser).
 *
 * @param {Array} menus Menus.
 * @return {Array} [ id, setId ].
 */
export function useCurrentMenu( menus ) {
	const [ id, setIdState ] = useState( () => {
		try {
			return Number( window.localStorage.getItem( 'dev3mm-menu' ) ) || 0;
		} catch ( e ) {
			return 0;
		}
	} );
	const setId = ( next ) => {
		setIdState( next );
		try {
			window.localStorage.setItem( 'dev3mm-menu', String( next ) );
		} catch ( e ) {}
	};
	useEffect( () => {
		if ( menus && menus.length && ! menus.some( ( m ) => m.id === id ) ) {
			const located = menus.find( ( m ) => m.locations && m.locations.length );
			setIdState( ( located || menus[ 0 ] ).id );
		}
	}, [ menus, id ] );
	return [ id, setId ];
}

/**
 * Live preview: renders the real front-end markup in an iframe, so media queries (mobile layout)
 * apply to the preview width.
 *
 * @param {Object} props          Props.
 * @param {Object} props.payload  POST /preview body ({ menu_id, tree, draft, location, config, tokens }).
 * @param {string} props.device   desktop|mobile.
 * @param {number} props.height   Desktop height.
 * @param {string} props.title    Accessible title.
 * @return {Element} Preview.
 */
const DESKTOP_WIDTH = 1280;

export function LivePreview( { payload, device = 'desktop', height = 460, title = __( 'Menu preview', 'dev3-ai-mega-menu' ), openFirst = false } ) {
	const [ html, setHtml ] = useState( null );
	const [ error, setError ] = useState( null );
	const key = JSON.stringify( payload );
	const timer = useRef( 0 );
	useEffect( () => {
		if ( ! payload || ( ! payload.menu_id && ! payload.tree && ! payload.draft ) ) {
			setHtml( '' );
			return;
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			request( 'preview', { method: 'POST', data: payload } )
				.then( ( res ) => {
					setError( null );
					setHtml( res.html || '' );
				} )
				.catch( setError );
		}, 350 );
		return () => clearTimeout( timer.current );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );

	const doc = useMemo( () => previewDocument( html || '', openFirst ), [ html, openFirst ] );
	const mobile = device === 'mobile';
	// Desktop: render a real 1280px-wide page and scale it to fit, so desktop breakpoints apply.
	const box = useRef( null );
	const [ scale, setScale ] = useState( 1 );
	useEffect( () => {
		const measure = () => box.current && setScale( Math.min( 1, ( box.current.clientWidth || 1280 ) / DESKTOP_WIDTH ) );
		measure();
		window.addEventListener( 'resize', measure );
		return () => window.removeEventListener( 'resize', measure );
	}, [ html, device ] );
	const frameHeight = Math.round( height / Math.max( scale, 0.4 ) );
	return (
		<div className={ cx( 'dev3mm-preview', mobile && 'is-mobile' ) }>
			{ error && <p className="dev3mm-muted">{ error.message }</p> }
			{ html === null && ! error && <Skeleton height={ mobile ? 560 : height } /> }
			{ html === '' && <p className="dev3mm-muted dev3mm-preview__empty">{ __( 'Add items to see a preview.', 'dev3-ai-mega-menu' ) }</p> }
			{ !! html && mobile && <iframe title={ title } className="dev3mm-preview__frame" srcDoc={ doc } style={ { height: 600 } } sandbox="allow-scripts allow-same-origin" /> }
			{ !! html && ! mobile && (
				<div ref={ box } className="dev3mm-preview__scaler" style={ { height: Math.round( frameHeight * scale ) } }>
					<iframe title={ title } className="dev3mm-preview__frame" srcDoc={ doc } style={ { width: DESKTOP_WIDTH, height: frameHeight, transform: `scale(${ scale })` } } sandbox="allow-scripts allow-same-origin" />
				</div>
			) }
		</div>
	);
}

/**
 * Preview page: the menu over a mock page, with front-end CSS/JS.
 *
 * @param {string}  html      Menu html.
 * @param {boolean} openFirst Open the first panel (for screenshots / style editing).
 * @return {string} Document.
 */
export function previewDocument( html, openFirst = false ) {
	const css = config.frontendCss || '';
	const js = config.frontendJs || '';
	const open = openFirst
		? `<script>window.addEventListener('load',function(){var nav=document.querySelector('nav.dev3mm');if(!nav||!nav.dev3mm||nav.dev3mm.isMobile())return;var li=nav.querySelector('.dev3mm__item--mega,.dev3mm__item--tabs,.dev3mm__item--flyout');if(li)nav.dev3mm.open(li);});</script>`
		: '';
	return `<!doctype html><html${ config.isRtl ? ' dir="rtl"' : '' }><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="${ css }"><style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f6f7fb;color:#1f2937}
.site{max-width:1200px;margin:0 auto;padding:18px 20px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px;margin-bottom:14px;color:#111827}
.brand i{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#a855f7);display:inline-block}
.hero{margin-top:28px;height:220px;border-radius:16px;background:linear-gradient(120deg,#e0e7ff,#fce7f3);}
.row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px}.row div{height:90px;border-radius:12px;background:#fff;border:1px solid #e5e7eb}
@media(max-width:700px){.row{grid-template-columns:1fr}.hero{height:140px}}
</style></head><body><div class="site"><div class="brand"><i></i>${ escapeHtml( config.siteName || 'Your site' ) }</div>${ html }<div class="hero"></div><div class="row"><div></div><div></div><div></div></div></div><script src="${ js }"></script><script>document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href]');if(a){e.preventDefault();}});document.addEventListener('submit',function(e){e.preventDefault();});</script>${ open }</body></html>`;
}

export function escapeHtml( s ) {
	return String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] );
}

/**
 * Desktop / mobile switch.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.value    Device.
 * @param {Function} props.onChange Change.
 * @return {Element} Switch.
 */
export function DeviceSwitch( { value, onChange } ) {
	return (
		<div className="dev3mm-seg" role="group" aria-label={ __( 'Preview size', 'dev3-ai-mega-menu' ) }>
			{ [
				[ 'desktop', __( 'Desktop', 'dev3-ai-mega-menu' ) ],
				[ 'mobile', __( 'Mobile', 'dev3-ai-mega-menu' ) ],
			].map( ( [ id, text ] ) => (
				<button key={ id } type="button" className={ cx( 'dev3mm-seg__btn', value === id && 'is-active' ) } aria-pressed={ value === id } onClick={ () => onChange( id ) }>
					{ text }
				</button>
			) ) }
		</div>
	);
}

export const LAYOUT_LABELS = {
	link: __( 'Link', 'dev3-ai-mega-menu' ),
	flyout: __( 'Flyout', 'dev3-ai-mega-menu' ),
	mega: __( 'Mega panel', 'dev3-ai-mega-menu' ),
	tabs: __( 'Tabs', 'dev3-ai-mega-menu' ),
};

export const LAYOUT_TONES = { link: 'neutral', flyout: 'info', mega: 'brand', tabs: 'success' };
