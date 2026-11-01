import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../../core3/api';
import { AiButton, Badge, Button, Card, EmptyState, ErrorNotice, Inline, Notice, Stat, StatGrid, useToast } from '../../core3/ui';
import { MenuSelect } from '../shared';

const SEVERITY = {
	high: { tone: 'danger', label: __( 'High', 'dev3-ai-mega-menu' ) },
	medium: { tone: 'warning', label: __( 'Medium', 'dev3-ai-mega-menu' ) },
	low: { tone: 'info', label: __( 'Low', 'dev3-ai-mega-menu' ) },
};

const GRADES = {
	great: __( 'Great', 'dev3-ai-mega-menu' ),
	good: __( 'Good', 'dev3-ai-mega-menu' ),
	fair: __( 'Needs work', 'dev3-ai-mega-menu' ),
	poor: __( 'Poor', 'dev3-ai-mega-menu' ),
};

/**
 * Score ring.
 *
 * @param {Object} props       Props.
 * @param {number} props.score 0-100.
 * @return {Element} Ring.
 */
export function ScoreRing( { score } ) {
	const r = 34;
	const c = 2 * Math.PI * r;
	const tone = score >= 90 ? '#047857' : score >= 70 ? '#4f46e5' : score >= 50 ? '#b45309' : '#b91c1c';
	return (
		<svg className="dev3mm-ring" width="88" height="88" viewBox="0 0 88 88" role="img" aria-label={ sprintf( /* translators: %d: score */ __( 'UX score %d out of 100', 'dev3-ai-mega-menu' ), score ) }>
			<circle cx="44" cy="44" r={ r } fill="none" stroke="#e5e7eb" strokeWidth="8" />
			<circle cx="44" cy="44" r={ r } fill="none" stroke={ tone } strokeWidth="8" strokeLinecap="round" strokeDasharray={ c } strokeDashoffset={ c * ( 1 - score / 100 ) } transform="rotate(-90 44 44)" />
			<text x="44" y="50" textAnchor="middle" fontSize="20" fontWeight="700" fill="#111827">
				{ score }
			</text>
		</svg>
	);
}

/**
 * Menu UX audit: rules always, plus DEV3 AI label/grouping review. Fixes apply on click.
 *
 * @param {Object} props Props.
 * @return {Element} Tool.
 */
export default function Audit( { menus, menuId, setMenuId, onUsage, aiReady } ) {
	const toast = useToast();
	const [ result, setResult ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( null );
	const [ fixed, setFixed ] = useState( {} );

	const run = ( ai ) => {
		setBusy( ai ? 'ai' : 'rules' );
		setError( null );
		setFixed( {} );
		request( 'audit', { method: 'POST', data: { menu_id: menuId, ai } } )
			.then( setResult )
			.catch( setError )
			.finally( () => {
				setBusy( '' );
				if ( ai ) {
					onUsage();
				}
			} );
	};

	const fix = ( issue ) => {
		const done = () => {
			setFixed( { ...fixed, [ issue.id ]: true } );
			toast.success( __( 'Fix applied.', 'dev3-ai-mega-menu' ) );
		};
		if ( issue.fix.type === 'rename' ) {
			request( `menus/${ menuId }/items`, { method: 'POST', data: { items: [ { id: issue.item_id, title: issue.fix.label } ] } } )
				.then( done )
				.catch( ( err ) => toast.error( err ) );
		} else if ( issue.fix.type === 'add_link' ) {
			request( 'build/apply', { method: 'POST', data: { draft: [ { title: issue.fix.label, ref: issue.fix.ref, layout: 'link', children: [] } ], target: { mode: 'append', menu_id: menuId } } } )
				.then( done )
				.catch( ( err ) => toast.error( err ) );
		}
	};

	return (
		<Card tone="ai" title={ __( 'Menu UX audit', 'dev3-ai-mega-menu' ) } description={ __( 'Finds menus that are too deep or crowded, vague or long labels, broken and dead-end links, and important pages that are missing. DEV3 AI adds a review of label clarity and grouping.', 'dev3-ai-mega-menu' ) }>
			<Inline gap={ 12 } align="start">
				<div className="dev3mm-grow-field">
					<MenuSelect menus={ menus } value={ menuId } onChange={ ( id ) => {
						setMenuId( id );
						setResult( null );
					} } />
				</div>
				<Button onClick={ () => run( false ) } loading={ busy === 'rules' } disabled={ ! menuId || !! busy }>
					{ __( 'Quick audit', 'dev3-ai-mega-menu' ) }
				</Button>
				<AiButton onClick={ () => run( true ) } loading={ busy === 'ai' } disabled={ ! menuId || !! busy || ! aiReady } loadingText={ __( 'Reviewing…', 'dev3-ai-mega-menu' ) }>
					{ __( 'Audit with DEV3 AI', 'dev3-ai-mega-menu' ) }
				</AiButton>
			</Inline>
			<ErrorNotice error={ error } onRetry={ () => run( true ) } onDismiss={ () => setError( null ) } />
			{ result && (
				<>
					<div className="dev3mm-audit-head">
						<ScoreRing score={ result.score } />
						<div>
							<h3 className="dev3mm-audit-head__grade">{ GRADES[ result.grade ] }</h3>
							<p className="dev3mm-muted">
								{ sprintf( /* translators: 1: issues 2: items 3: depth */ __( '%1$d issues · %2$d items · %3$d levels deep', 'dev3-ai-mega-menu' ), result.issues.length, result.counts.items, result.counts.depth ) }
							</p>
						</div>
					</div>
					{ result.ai && result.ai.error && <Notice tone="warning">{ sprintf( /* translators: %s: error */ __( 'DEV3 AI review skipped: %s The rule checks below still ran.', 'dev3-ai-mega-menu' ), result.ai.error.message ) }</Notice> }
					<StatGrid>
						{ [ 'high', 'medium', 'low' ].map( ( s ) => (
							<Stat key={ s } label={ SEVERITY[ s ].label } value={ String( result.issues.filter( ( i ) => i.severity === s ).length ) } tone={ s === 'high' ? 'danger' : s === 'medium' ? 'warning' : 'neutral' } />
						) ) }
					</StatGrid>
					{ ! result.issues.length ? (
						<EmptyState compact icon="success" title={ __( 'No issues found', 'dev3-ai-mega-menu' ) } description={ __( 'This menu is clear, shallow and complete.', 'dev3-ai-mega-menu' ) } />
					) : (
						<ul className="dev3mm-issues">
							{ result.issues.map( ( issue ) => (
								<li key={ issue.id } className={ `dev3mm-issue is-${ issue.severity }` }>
									<div className="dev3mm-issue__main">
										<Inline gap={ 8 }>
											<Badge tone={ SEVERITY[ issue.severity ].tone }>{ SEVERITY[ issue.severity ].label }</Badge>
											{ issue.source === 'ai' && <Badge tone="brand">{ __( 'DEV3 AI', 'dev3-ai-mega-menu' ) }</Badge> }
										</Inline>
										<p className="dev3mm-issue__title">{ issue.title }</p>
										<p className="dev3mm-muted">{ issue.detail }</p>
										{ issue.fix.type === 'rename' && (
											<p className="dev3mm-issue__fix">
												{ __( 'Suggested label:', 'dev3-ai-mega-menu' ) } <strong>{ issue.fix.label }</strong>
											</p>
										) }
									</div>
									<div className="dev3mm-issue__actions">
										{ fixed[ issue.id ] ? (
											<Badge tone="success">{ __( 'Fixed', 'dev3-ai-mega-menu' ) }</Badge>
										) : issue.fix.type === 'rename' ? (
											<Button size="sm" variant="primary" onClick={ () => fix( issue ) }>
												{ __( 'Apply label', 'dev3-ai-mega-menu' ) }
											</Button>
										) : issue.fix.type === 'add_link' ? (
											<Button size="sm" variant="primary" icon="plus" onClick={ () => fix( issue ) }>
												{ sprintf( /* translators: %s: label */ __( 'Add “%s”', 'dev3-ai-mega-menu' ), issue.fix.label ) }
											</Button>
										) : (
											<Button size="sm" href="#/builder">
												{ __( 'Open builder', 'dev3-ai-mega-menu' ) }
											</Button>
										) }
									</div>
								</li>
							) ) }
						</ul>
					) }
				</>
			) }
		</Card>
	);
}
