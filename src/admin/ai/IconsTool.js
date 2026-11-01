import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../../core3/api';
import { AiButton, Badge, Button, Card, Checkbox, ErrorNotice, Inline, Notice, ResponsiveTable, Select, cx, useToast } from '../../core3/ui';
import { ICONS, ICON_NAMES, MenuIcon, MenuSelect } from '../shared';

/**
 * Icon & image suggestions per item (DEV3 AI, or keyword matching without AI).
 *
 * @param {Object} props Props.
 * @return {Element} Tool.
 */
export default function IconsTool( { menus, menuId, setMenuId, onUsage, aiReady } ) {
	const toast = useToast();
	const [ rows, setRows ] = useState( null );
	const [ source, setSource ] = useState( '' );
	const [ aiError, setAiError ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( null );

	const run = ( ai ) => {
		setBusy( ai ? 'ai' : 'match' );
		setError( null );
		request( 'ai/icons', { method: 'POST', data: { menu_id: menuId, ai } } )
			.then( ( res ) => {
				setSource( res.source );
				setAiError( res.ai_error );
				setRows( res.items.map( ( r ) => ( { ...r, image: 0, on: !! r.icon && r.icon !== r.current_icon } ) ) );
			} )
			.catch( setError )
			.finally( () => {
				setBusy( '' );
				if ( ai ) {
					onUsage();
				}
			} );
	};

	const setRow = ( id, patch ) => setRows( rows.map( ( r ) => ( r.id === id ? { ...r, ...patch } : r ) ) );
	const picked = ( rows || [] ).filter( ( r ) => r.on );

	const apply = () => {
		setBusy( 'apply' );
		const items = picked.map( ( r ) => ( { id: r.id, config: { icon: r.icon, ...( r.image ? { image_id: r.image } : {} ) } } ) );
		request( `menus/${ menuId }/items`, { method: 'POST', data: { items } } )
			.then( ( res ) => {
				toast.success( sprintf( /* translators: %d: count */ __( 'Icons applied to %d items.', 'dev3-ai-mega-menu' ), res.updated ) );
				setRows( null );
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( '' ) );
	};

	const iconOptions = [ { value: '', label: __( 'No icon', 'dev3-ai-mega-menu' ) }, ...ICON_NAMES.map( ( n ) => ( { value: n, label: ICONS[ n ].label } ) ) ];

	return (
		<Card tone="ai" title={ __( 'Icons & images', 'dev3-ai-mega-menu' ) } description={ __( 'Suggests an icon from the built-in set for every item, and images from your media library or the linked page’s featured image.', 'dev3-ai-mega-menu' ) }>
			<Inline gap={ 12 } align="start">
				<div className="dev3mm-grow-field">
					<MenuSelect menus={ menus } value={ menuId } onChange={ ( id ) => {
						setMenuId( id );
						setRows( null );
					} } />
				</div>
				<Button onClick={ () => run( false ) } loading={ busy === 'match' } disabled={ ! menuId || !! busy }>
					{ __( 'Match by keywords', 'dev3-ai-mega-menu' ) }
				</Button>
				<AiButton onClick={ () => run( true ) } loading={ busy === 'ai' } disabled={ ! menuId || !! busy || ! aiReady } loadingText={ __( 'Choosing icons…', 'dev3-ai-mega-menu' ) }>
					{ __( 'Suggest with DEV3 AI', 'dev3-ai-mega-menu' ) }
				</AiButton>
			</Inline>
			<ErrorNotice error={ error } onRetry={ () => run( true ) } onDismiss={ () => setError( null ) } />
			{ rows && (
				<>
					<Inline gap={ 8 }>
						<Badge tone="warning">{ __( 'Not saved yet', 'dev3-ai-mega-menu' ) }</Badge>
						<Badge tone={ source === 'ai' ? 'brand' : 'neutral' }>{ source === 'ai' ? __( 'Suggested by DEV3 AI', 'dev3-ai-mega-menu' ) : __( 'Keyword matches', 'dev3-ai-mega-menu' ) }</Badge>
					</Inline>
					{ aiError && <Notice tone="warning">{ sprintf( /* translators: %s: error */ __( 'DEV3 AI was not available (%s), so keyword matches are shown.', 'dev3-ai-mega-menu' ), aiError.message ) }</Notice> }
					<ResponsiveTable
						caption={ __( 'Icon suggestions', 'dev3-ai-mega-menu' ) }
						rows={ rows }
						pageSize={ 25 }
						columns={ [
							{ key: 'on', label: __( 'Apply', 'dev3-ai-mega-menu' ), width: 70, render: ( r ) => <Checkbox className="dev3mm-hide-label" label={ sprintf( /* translators: %s: label */ __( 'Apply to %s', 'dev3-ai-mega-menu' ), r.title ) } checked={ r.on } onChange={ ( v ) => setRow( r.id, { on: v } ) } /> },
							{ key: 'title', label: __( 'Item', 'dev3-ai-mega-menu' ), primary: true, render: ( r ) => <span style={ { paddingInlineStart: r.depth * 14 } }>{ r.title }</span> },
							{
								key: 'icon',
								label: __( 'Icon', 'dev3-ai-mega-menu' ),
								render: ( r ) => (
									<span className="dev3mm-icon-row">
										<span className="dev3mm-icon-preview" aria-hidden="true">
											<MenuIcon name={ r.icon } size={ 20 } />
										</span>
										<Select label={ __( 'Icon', 'dev3-ai-mega-menu' ) } hideLabel value={ r.icon } options={ iconOptions } onChange={ ( v ) => setRow( r.id, { icon: v, on: true } ) } />
									</span>
								),
							},
							{
								key: 'images',
								label: __( 'Image (optional)', 'dev3-ai-mega-menu' ),
								render: ( r ) =>
									r.images.length ? (
										<span className="dev3mm-thumbs" role="radiogroup" aria-label={ sprintf( /* translators: %s: label */ __( 'Image for %s', 'dev3-ai-mega-menu' ), r.title ) }>
											{ r.images.map( ( img ) => (
												<button key={ img.id } type="button" role="radio" aria-checked={ r.image === img.id } className={ cx( 'dev3mm-thumb', r.image === img.id && 'is-selected' ) } onClick={ () => setRow( r.id, { image: r.image === img.id ? 0 : img.id, on: true } ) }>
													<img src={ img.url } alt={ img.alt || sprintf( /* translators: %d: id */ __( 'Image %d', 'dev3-ai-mega-menu' ), img.id ) } />
												</button>
											) ) }
										</span>
									) : (
										<span className="dev3mm-muted">{ __( 'None found', 'dev3-ai-mega-menu' ) }</span>
									),
							},
						] }
					/>
					<Inline gap={ 8 }>
						<Button variant="primary" icon="check" onClick={ apply } loading={ busy === 'apply' } disabled={ ! picked.length }>
							{ sprintf( /* translators: %d: count */ __( 'Apply %d selected', 'dev3-ai-mega-menu' ), picked.length ) }
						</Button>
						<Button variant="ghost" onClick={ () => setRows( null ) }>
							{ __( 'Reject all', 'dev3-ai-mega-menu' ) }
						</Button>
					</Inline>
				</>
			) }
		</Card>
	);
}
