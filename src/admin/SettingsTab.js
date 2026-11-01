import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, FormGrid, Modal, NumberField, PageHeader, Select, TextField, Toggle, useToast } from '../core3/ui';

const KEYS = [ 'load_assets', 'ai_language', 'ai_max_pages', 'ai_include_products', 'delete_on_uninstall' ];

/**
 * General plugin settings (keys registered in includes/Settings.php).
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.settings Settings.
 * @param {Function} props.save     Save.
 * @return {Element} Settings.
 */
export default function SettingsTab( { settings, save } ) {
	const toast = useToast();
	const [ form, setForm ] = useState( settings );
	const [ busy, setBusy ] = useState( false );
	const [ confirm, setConfirm ] = useState( false );
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const dirty = KEYS.some( ( k ) => form[ k ] !== settings[ k ] );

	const persist = ( values ) => {
		setBusy( true );
		return save( values )
			.then( ( next ) => {
				setForm( next );
				toast.success( __( 'Settings saved.', 'dev3-ai-mega-menu' ) );
			} )
			.catch( ( err ) => toast.error( err ) )
			.finally( () => setBusy( false ) );
	};

	const submit = ( e ) => {
		e.preventDefault();
		persist( Object.fromEntries( KEYS.map( ( k ) => [ k, k === 'ai_max_pages' && form[ k ] === '' ? 400 : form[ k ] ] ) ) );
	};

	return (
		<form onSubmit={ submit } noValidate>
			<PageHeader
				title={ __( 'Settings', 'dev3-ai-mega-menu' ) }
				description={ __( 'Loading, DEV3 AI behaviour and data handling.', 'dev3-ai-mega-menu' ) }
				actions={
					<Button type="submit" variant="primary" loading={ busy } disabled={ ! dirty }>
						{ __( 'Save settings', 'dev3-ai-mega-menu' ) }
					</Button>
				}
			/>
			<Card title={ __( 'Performance', 'dev3-ai-mega-menu' ) } description={ __( 'The front-end script is about 3 KB and the stylesheet about 8 KB (gzipped). No AI calls run on page load.', 'dev3-ai-mega-menu' ) }>
				<Select
					label={ __( 'Load front-end files', 'dev3-ai-mega-menu' ) }
					value={ form.load_assets }
					onChange={ set( 'load_assets' ) }
					options={ [
						{ value: 'auto', label: __( 'Only when a mega menu is used (recommended)', 'dev3-ai-mega-menu' ) },
						{ value: 'always', label: __( 'On every page', 'dev3-ai-mega-menu' ) },
					] }
					help={ __( 'Choose “every page” if a cache or optimisation plugin strips late-loaded styles.', 'dev3-ai-mega-menu' ) }
				/>
			</Card>
			<Card title={ __( 'DEV3 AI', 'dev3-ai-mega-menu' ) } description={ __( 'How the AI tools read your site and write copy.', 'dev3-ai-mega-menu' ) }>
				<FormGrid>
					<TextField label={ __( 'Language for AI labels', 'dev3-ai-mega-menu' ) } value={ form.ai_language } onChange={ set( 'ai_language' ) } placeholder={ __( 'Site language (automatic)', 'dev3-ai-mega-menu' ) } help={ __( 'e.g. German, Français, Japanese. Empty = the site language.', 'dev3-ai-mega-menu' ) } maxLength={ 40 } />
					<NumberField label={ __( 'Pages to read when building', 'dev3-ai-mega-menu' ) } value={ form.ai_max_pages } min={ 20 } max={ 1000 } step={ 20 } onChange={ set( 'ai_max_pages' ) } help={ __( 'Larger sites take longer; 400 covers most.', 'dev3-ai-mega-menu' ) } />
				</FormGrid>
				<Toggle label={ __( 'Include WooCommerce products and categories', 'dev3-ai-mega-menu' ) } checked={ form.ai_include_products } onChange={ set( 'ai_include_products' ) } />
			</Card>
			<Card title={ __( 'Data', 'dev3-ai-mega-menu' ) }>
				<Toggle
					label={ __( 'Delete all plugin data when uninstalling', 'dev3-ai-mega-menu' ) }
					checked={ form.delete_on_uninstall }
					onChange={ ( v ) => ( v ? setConfirm( true ) : set( 'delete_on_uninstall' )( false ) ) }
					help={ __( 'Removes styles, location settings, item settings and the usage log. Your WordPress menus themselves are kept.', 'dev3-ai-mega-menu' ) }
				/>
			</Card>
			<Modal
				open={ confirm }
				onClose={ () => setConfirm( false ) }
				size="sm"
				title={ __( 'Delete data on uninstall?', 'dev3-ai-mega-menu' ) }
				description={ __( 'Mega-menu settings will be lost if you uninstall the plugin. You can turn this off again any time.', 'dev3-ai-mega-menu' ) }
				footer={
					<>
						<Button variant="ghost" onClick={ () => setConfirm( false ) }>
							{ __( 'Cancel', 'dev3-ai-mega-menu' ) }
						</Button>
						<Button
							variant="danger"
							onClick={ () => {
								set( 'delete_on_uninstall' )( true );
								setConfirm( false );
							} }
						>
							{ __( 'Yes, delete on uninstall', 'dev3-ai-mega-menu' ) }
						</Button>
					</>
				}
			/>
		</form>
	);
}
