import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../../core3/api';
import { AiButton, Badge, Button, Card, Checkbox, ErrorNotice, Inline, ResponsiveTable, TextField, Toggle, useToast } from '../../core3/ui';
import { MenuSelect } from '../shared';

/**
 * AI labels and short descriptions, reviewed per row before applying.
 *
 * @param {Object} props Props.
 * @return {Element} Tool.
 */
export default function Labels( { menus, menuId, setMenuId, onUsage, aiReady } ) {
	const toast = useToast();
	const [ rows, setRows ] = useState( null );
	const [ meta, setMeta ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ applying, setApplying ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ showDesc, setShowDesc ] = useState( true );

	const run = () => {
		setBusy( true );
		setError( null );
		request( 'ai/labels', { method: 'POST', data: { menu_id: menuId } } )
			.then( ( res ) => {
				setRows( res.items.map( ( r ) => ( { ...r, on: r.label !== r.title || ( r.new_description && r.new_description !== r.description ) } ) ) );
				setMeta( { ...res.meta, language: res.language } );
			} )
			.catch( setError )
			.finally( () => {
				setBusy( false );
				onUsage();
			} );
	};

	const setRow = ( id, patch ) => setRows( rows.map( ( r ) => ( r.id === id ? { ...r, ...patch } : r ) ) );
	const picked = ( rows || [] ).filter( ( r ) => r.on );

	const apply = () => {
		setApplying( true );
		const items = picked.map( ( r ) => ( { id: r.id, title: r.label, description: r.new_description, ...( showDesc && r.new_description ? { config: { show_description: r.depth > 0 } } : {} ) } ) );
		request( `menus/${ menuId }/items`, { method: 'POST', data: { items } } )
			.then( ( res ) => {
				toast.success( sprintf( /* translators: %d: count */ __( '%d items updated.', 'dev3-ai-mega-menu' ), res.updated ) );
				setRows( null );
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setApplying( false ) );
	};

	return (
		<Card tone="ai" title={ __( 'Labels & descriptions', 'dev3-ai-mega-menu' ) } description={ __( 'Clear, short labels and one-line descriptions for every item, written in your site’s language. Review each suggestion, edit it, and apply only what you like.', 'dev3-ai-mega-menu' ) }>
			<Inline gap={ 12 } align="start">
				<div className="dev3mm-grow-field">
					<MenuSelect menus={ menus } value={ menuId } onChange={ ( id ) => {
						setMenuId( id );
						setRows( null );
					} } />
				</div>
				<AiButton onClick={ run } loading={ busy } disabled={ ! menuId || busy || ! aiReady } loadingText={ __( 'Writing labels…', 'dev3-ai-mega-menu' ) }>
					{ __( 'Write labels with DEV3 AI', 'dev3-ai-mega-menu' ) }
				</AiButton>
			</Inline>
			<ErrorNotice error={ error } onRetry={ run } onDismiss={ () => setError( null ) } />
			{ rows && (
				<>
					<Inline gap={ 8 }>
						<Badge tone="warning">{ __( 'Not saved yet', 'dev3-ai-mega-menu' ) }</Badge>
						{ meta && meta.language && <Badge tone="info">{ meta.language }</Badge> }
						{ meta && meta.model && <Badge>{ meta.model }</Badge> }
						<span className="dev3mm-grow" />
						<Button size="sm" variant="ghost" onClick={ () => setRows( rows.map( ( r ) => ( { ...r, on: true } ) ) ) }>
							{ __( 'Select all', 'dev3-ai-mega-menu' ) }
						</Button>
						<Button size="sm" variant="ghost" onClick={ () => setRows( rows.map( ( r ) => ( { ...r, on: false } ) ) ) }>
							{ __( 'Select none', 'dev3-ai-mega-menu' ) }
						</Button>
					</Inline>
					<ResponsiveTable
						caption={ __( 'Suggested labels', 'dev3-ai-mega-menu' ) }
						rows={ rows }
						pageSize={ 25 }
						columns={ [
							{ key: 'on', label: __( 'Apply', 'dev3-ai-mega-menu' ), width: 70, render: ( r ) => <Checkbox label={ sprintf( /* translators: %s: label */ __( 'Apply to %s', 'dev3-ai-mega-menu' ), r.title ) } checked={ r.on } onChange={ ( v ) => setRow( r.id, { on: v } ) } className="dev3mm-hide-label" /> },
							{ key: 'title', label: __( 'Current', 'dev3-ai-mega-menu' ), primary: true, render: ( r ) => <span style={ { paddingInlineStart: r.depth * 14 } }>{ r.title }</span> },
							{ key: 'label', label: __( 'Suggested label', 'dev3-ai-mega-menu' ), render: ( r ) => <TextField label={ __( 'Suggested label', 'dev3-ai-mega-menu' ) } hideLabel value={ r.label } maxLength={ 40 } onChange={ ( v ) => setRow( r.id, { label: v, on: true } ) } /> },
							{ key: 'new_description', label: __( 'Suggested description', 'dev3-ai-mega-menu' ), render: ( r ) => <TextField label={ __( 'Suggested description', 'dev3-ai-mega-menu' ) } hideLabel value={ r.new_description } maxLength={ 100 } onChange={ ( v ) => setRow( r.id, { new_description: v, on: true } ) } /> },
						] }
					/>
					<Toggle label={ __( 'Show descriptions under sub-items in the menu', 'dev3-ai-mega-menu' ) } checked={ showDesc } onChange={ setShowDesc } />
					<Inline gap={ 8 }>
						<Button variant="primary" icon="check" onClick={ apply } loading={ applying } disabled={ ! picked.length }>
							{ sprintf( /* translators: %d: count */ __( 'Apply %d selected', 'dev3-ai-mega-menu' ), picked.length ) }
						</Button>
						<Button icon="refresh" onClick={ run } disabled={ busy }>
							{ __( 'Regenerate', 'dev3-ai-mega-menu' ) }
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
