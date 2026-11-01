import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../core3/api';
import config from '../core3/config';
import ApiTab from '../core3/ApiTab';
import UsageTab from '../core3/UsageTab';
import { AppShell, ErrorNotice, PageHeader, SkeletonCard, UsageMeter, useHashRoute } from '../core3/ui';
import AiStudio from './AiStudio';
import Builder from './Builder';
import Dashboard from './Dashboard';
import Locations from './Locations';
import SettingsTab from './SettingsTab';
import Styles from './Styles';

const NAV = [
	{ id: 'dashboard', label: __( 'Dashboard', 'dev3-ai-mega-menu' ), icon: 'dashboard' },
	{ id: 'builder', label: __( 'Menu Builder', 'dev3-ai-mega-menu' ), icon: 'menu' },
	{ id: 'locations', label: __( 'Locations', 'dev3-ai-mega-menu' ), icon: 'list' },
	{ id: 'styles', label: __( 'Styles', 'dev3-ai-mega-menu' ), icon: 'columns' },
	{ id: 'ai', label: __( 'AI Studio', 'dev3-ai-mega-menu' ), icon: 'sparkles' },
	{ id: 'settings', label: __( 'Settings', 'dev3-ai-mega-menu' ), icon: 'settings' },
	{ id: 'api', label: __( 'API Config', 'dev3-ai-mega-menu' ), icon: 'key' },
	{ id: 'usage', label: __( 'Usage Logs', 'dev3-ai-mega-menu' ), icon: 'chart' },
];

export default function App() {
	const [ route ] = useHashRoute( NAV.map( ( n ) => n.id ), 'dashboard' );
	const [ settings, setSettings ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ meter, setMeter ] = useState( 0 );

	const load = () => {
		setError( null );
		request( 'settings' ).then( setSettings ).catch( setError );
	};
	useEffect( load, [] );

	const save = ( patch ) =>
		request( 'settings', { method: 'POST', data: patch } ).then( ( next ) => {
			setSettings( next );
			setMeter( ( n ) => n + 1 );
			return next;
		} );
	const onUsage = () => setMeter( ( n ) => n + 1 );

	const screens = {
		dashboard: () => <Dashboard settings={ settings } />,
		builder: () => <Builder />,
		locations: () => <Locations settings={ settings } save={ save } />,
		styles: () => <Styles />,
		ai: () => <AiStudio settings={ settings } onUsage={ onUsage } refreshKey={ meter } />,
		settings: () => <SettingsTab settings={ settings } save={ save } />,
		api: () => (
			<>
				<PageHeader title={ __( 'API Config', 'dev3-ai-mega-menu' ) } description={ __( 'Connect Google Gemini, pick models and set a monthly AI budget.', 'dev3-ai-mega-menu' ) } />
				<ApiTab settings={ settings } save={ save } />
			</>
		),
		usage: () => (
			<>
				<PageHeader title={ __( 'Usage Logs', 'dev3-ai-mega-menu' ) } description={ __( 'Requests, tokens and estimated cost of every DEV3 AI feature.', 'dev3-ai-mega-menu' ) } />
				<UsageTab />
			</>
		),
	};

	return (
		<AppShell
			name={ config.name || 'DEV3 AI Mega Menu for WordPress' }
			version={ config.version }
			icon={ config.assetsUrl ? `${ config.assetsUrl }img/icon.svg` : undefined }
			nav={ NAV }
			active={ route.id }
			actions={ <UsageMeter compact refreshKey={ meter } /> }
			footer={ __( 'Made by DEV3 Technologies · AI by Google Gemini, using your own API key.', 'dev3-ai-mega-menu' ) }
		>
			{ error && <ErrorNotice error={ error } onRetry={ load } /> }
			{ ! settings && ! error && <SkeletonCard lines={ 5 } /> }
			{ settings && screens[ route.id ]() }
		</AppShell>
	);
}
