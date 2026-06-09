/**
 * OverviewDashboard — the view shown when WSN is enabled.
 *
 * Layout (per v2 mockup):
 *   - Section heading + intro
 *   - Period selector (Today / Last 7d / 30d / 90d / 12m) — flips the data window
 *   - 10-card stat grid (3 rows: discovery, conversion, affinity)
 *   - Recent Network orders table
 *   - Footer disable affordance ("Remove from Woo Shopping Network")
 *
 * Data source: `GET /wc/v3/payments/wsn/orders?period=<period>`. Until WooPay-side
 * order tagging (Cohort A/B; RSM-2484/2485) ships, the endpoint returns
 * `{ is_empty: true, stats: {} }` and every stat renders `—` per StatCard's
 * default behavior. The empty-state design is intentional — visible scaffolding
 * wires up incrementally as upstream lands.
 *
 * Owned by RSM-2493.
 *
 * @format
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
// `__experimentalConfirmDialog` has been the de-facto WP-components confirm
// pattern since WP 6.1 and is widely used across WP core/Gutenberg admin
// surfaces. Stable enough to depend on here; revisit when WP promotes it.
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- intentional, WCPay-wide pattern
import {
	__experimentalConfirmDialog as ConfirmDialog,
	Notice,
} from '@wordpress/components';

import StatCard from './stat-card';
import OrdersTable from './orders-table';
import { colors, typography, spacing, radii } from '../tokens';
import { formatApiError } from '../utils/format-api-error';

// Debounce window for the period selector. The cancelled-flag pattern in the
// fetch effect already prevents stale state updates, but rapid period-chip
// clicks would still fire N server calls before the first completes. 300ms
// gives a user time to scrub through periods without flooding the controller.
const PERIOD_DEBOUNCE_MS = 300;

const PERIODS = [
	{ value: 'today', label: __( 'Today', 'woocommerce-payments' ) },
	{ value: '7d', label: __( 'Last 7 days', 'woocommerce-payments' ) },
	{ value: '30d', label: __( 'Last 30 days', 'woocommerce-payments' ) },
	{ value: '90d', label: __( 'Last 90 days', 'woocommerce-payments' ) },
	{ value: '12m', label: __( 'Last 12 months', 'woocommerce-payments' ) },
];

const PeriodChip = ( { value, label, isActive, onClick } ) => (
	<button
		type="button"
		onClick={ () => onClick( value ) }
		style={ {
			background: isActive ? colors.infoBg : 'transparent',
			border: `1px solid ${
				isActive ? colors.infoBorder : 'transparent'
			}`,
			borderRadius: radii.md,
			padding: '5px 12px',
			fontSize: '13px',
			fontWeight: isActive ? 500 : 400,
			color: isActive ? colors.infoBorder : colors.textSecondary,
			cursor: 'pointer',
			lineHeight: 1.4,
		} }
	>
		{ label }
	</button>
);

/**
 * @param {Object}   props
 * @param {Function} props.onDisable Called after the disable request succeeds.
 */
const OverviewDashboard = ( { onDisable } ) => {
	const [ period, setPeriodImmediate ] = useState( '30d' );
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isDisabling, setIsDisabling ] = useState( false );
	const [ isConfirmingDisable, setIsConfirmingDisable ] = useState( false );
	// Surface errors for the destructive "Remove" action — silent failures
	// here made the button look successful when it wasn't.
	const [ disableError, setDisableError ] = useState( null );
	// Distinguish a fetch failure from the legitimate `is_empty: true` empty
	// state. The empty-state cards still render (we want the layout intact),
	// but we display an inline error chip so merchants know the dashboard is
	// stale rather than genuinely empty.
	const [ fetchError, setFetchError ] = useState( null );

	// Debounced setPeriod. useRef so the timeout id persists across renders;
	// cleared if the user clicks another chip before the window elapses.
	const periodTimerRef = useRef( null );
	const setPeriod = ( nextPeriod ) => {
		if ( periodTimerRef.current ) {
			clearTimeout( periodTimerRef.current );
		}
		periodTimerRef.current = setTimeout( () => {
			setPeriodImmediate( nextPeriod );
		}, PERIOD_DEBOUNCE_MS );
	};
	useEffect( () => {
		return () => {
			if ( periodTimerRef.current ) {
				clearTimeout( periodTimerRef.current );
			}
		};
	}, [] );

	useEffect( () => {
		let cancelled = false;
		setIsLoading( true );
		// Clear any prior fetch error at the start of every period fetch so
		// the chip only reflects the *current* request's outcome. Setter is
		// deliberately *not* added to the dep array — including it would
		// re-run the effect on every clear and create a render loop.
		setFetchError( null );
		apiFetch( {
			path: `/wc/v3/payments/wsn/orders?period=${ encodeURIComponent(
				period
			) }`,
		} )
			.then( ( payload ) => {
				if ( ! cancelled ) {
					setData( payload );
					setIsLoading( false );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					// Keep showing the empty-state cards so layout stays
					// intact; the chip rendered above the grid is how the
					// merchant learns the data is stale rather than genuinely
					// empty.
					setData( { is_empty: true, stats: {}, orders: [] } );
					setFetchError( formatApiError( e ) );
					setIsLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ period ] );

	// Two-step disable: clicking "Remove" opens a styled <ConfirmDialog>, the
	// merchant confirms there, and only then do we PUT. Using the
	// @wordpress/components dialog (rather than window.confirm) is what WCPay
	// uses for other destructive actions — testable, styleable, and not
	// suppressed in cross-origin iframes.
	const handleDisableRequest = () => setIsConfirmingDisable( true );
	const handleDisableCancel = () => setIsConfirmingDisable( false );
	const handleDisableConfirm = async () => {
		setIsConfirmingDisable( false );
		setIsDisabling( true );
		// Clear any prior disable error so a retry doesn't briefly show the
		// old message before the new request resolves.
		setDisableError( null );
		try {
			await apiFetch( {
				path: '/wc/v3/payments/wsn/settings',
				method: 'PUT',
				data: { enabled: false },
			} );
			onDisable();
		} catch ( e ) {
			setDisableError( formatApiError( e ) );
			setIsDisabling( false );
		}
	};

	const stats = data?.stats ?? {};

	return (
		<div>
			<h2
				style={ {
					...typography.sectionHeading,
					margin: `0 0 ${ spacing.s1 }`,
				} }
			>
				{ __(
					'Shopping Network traffic and orders',
					'woocommerce-payments'
				) }
			</h2>
			<p
				style={ {
					fontSize: '13px',
					color: colors.textSecondary,
					fontStyle: 'italic',
					marginBottom: spacing.s6,
					lineHeight: 1.5,
				} }
			>
				{ __(
					'Live dashboard of your Shopping Network-attributed traffic and orders.',
					'woocommerce-payments'
				) }
			</p>

			<div
				style={ {
					display: 'flex',
					justifyContent: 'flex-end',
					gap: '4px',
					marginBottom: spacing.s5,
					flexWrap: 'wrap',
				} }
			>
				{ PERIODS.map( ( { value, label } ) => (
					<PeriodChip
						key={ value }
						value={ value }
						label={ label }
						isActive={ period === value }
						onClick={ setPeriod }
					/>
				) ) }
			</div>

			{ fetchError && (
				<div
					role="status"
					style={ {
						display: 'inline-flex',
						alignItems: 'center',
						gap: '6px',
						background: colors.dangerBg,
						border: `1px solid ${ colors.dangerText }`,
						borderRadius: radii.sm,
						color: colors.dangerText,
						padding: '4px 10px',
						fontSize: '12px',
						lineHeight: 1.4,
						marginBottom: spacing.s3,
					} }
				>
					{ __(
						'Could not load stats — refresh to try again.',
						'woocommerce-payments'
					) }
				</div>
			) }

			{ /*
				Stat-card grid: max 4 cards per row, expanding to fill
				horizontal space until they hit the 4-column cap. Direct
				port of AI Storefront's pattern at
				`woocommerce-ai-storefront/client/settings/ai-storefront/settings-page.js:1234`.

				The `max(180px, calc((100% - 36px) / 4))` formula:
				- `(100% - 36px) / 4` = card width when 4 columns fit
				  (36px = 3 gaps × 12px gap from spacing.s3)
				- `max(180px, ...)` floors each card at 180px
				- On wide containers the calc wins and caps at 4 columns
				- On narrow containers the 180px floor wins and auto-fit
				  packs as many ≥180px columns as fit
				- 10 cards lay out as 4 + 4 + 2 with the last row
				  left-aligned via auto-fit's empty-slot collapse.

				`min-width: 0` lets the grid shrink below its content's
				min-content size inside narrow flex/grid parents; per-card
				overflow defense lives on StatCard's value div.
			*/ }
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns:
						'repeat(auto-fit, minmax(max(180px, calc((100% - 36px) / 4)), 1fr))',
					gap: spacing.s3,
					marginBottom: spacing.s6,
					minWidth: 0,
					opacity: isLoading ? 0.5 : 1,
					transition: 'opacity 0.1s',
				} }
			>
				{ /* Row 1 — discovery / exposure */ }
				<StatCard
					label={ __( 'Products Listed', 'woocommerce-payments' ) }
					value={ stats.products_listed ?? null }
				/>
				<StatCard
					label={ __( 'Products Viewed', 'woocommerce-payments' ) }
					value={ stats.products_viewed ?? null }
					reference={ stats.products_listed ?? null }
				/>
				<StatCard
					label={ __( 'Products Viewed %', 'woocommerce-payments' ) }
					value={ stats.products_viewed_pct ?? null }
				/>
				<StatCard
					label={ __( 'Network Orders', 'woocommerce-payments' ) }
					value={ stats.network_orders ?? null }
					reference={ stats.total_orders ?? null }
				/>

				{ /* Row 2 — conversion / revenue */ }
				<StatCard
					label={ __( 'Network Order Rate', 'woocommerce-payments' ) }
					value={ stats.network_order_rate ?? null }
				/>
				<StatCard
					label={ __( 'Network Revenue', 'woocommerce-payments' ) }
					value={ stats.network_revenue_formatted ?? null }
					reference={ stats.total_revenue_formatted ?? null }
				/>
				<StatCard
					label={ __( 'Network Revenue %', 'woocommerce-payments' ) }
					value={ stats.network_revenue_pct ?? null }
				/>
				<StatCard
					label={ __( 'Network AOV', 'woocommerce-payments' ) }
					value={ stats.network_aov_formatted ?? null }
				/>

				{ /* Row 3 — affinity */ }
				<StatCard
					label={ __(
						'Top Discovery Source',
						'woocommerce-payments'
					) }
					value={ stats.top_source ?? null }
				/>
				<StatCard
					label={ __( 'Top Source Share', 'woocommerce-payments' ) }
					value={ stats.top_source_share ?? null }
				/>
			</div>

			<div style={ { marginBottom: spacing.s6 } }>
				<OrdersTable orders={ data?.orders ?? [] } />
			</div>

			{ disableError && (
				<div style={ { marginTop: spacing.s2 } }>
					<Notice
						status="error"
						isDismissible
						onRemove={ () => setDisableError( null ) }
					>
						{ disableError }
					</Notice>
				</div>
			) }

			{ /* Footer disable affordance — paired with the table per v2 mockup */ }
			<div
				style={ {
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'space-between',
					padding: '16px 0 4px',
					borderTop: `1px solid ${ colors.borderSubtle }`,
					marginTop: spacing.s2,
					gap: spacing.s4,
				} }
			>
				<div style={ { lineHeight: 1.5 } }>
					<strong
						style={ {
							display: 'block',
							fontSize: '13px',
							fontWeight: 600,
							color: colors.textPrimary,
							marginBottom: '2px',
						} }
					>
						{ __(
							'Remove from Woo Shopping Network',
							'woocommerce-payments'
						) }
					</strong>
					<span
						style={ {
							fontSize: '12px',
							color: colors.textMuted,
						} }
					>
						{ __(
							'Stops your storefront from appearing in the Shopping Network. Settings are preserved.',
							'woocommerce-payments'
						) }
					</span>
				</div>
				<button
					type="button"
					onClick={ handleDisableRequest }
					disabled={ isDisabling }
					style={ {
						background: colors.surface,
						border: `1px solid ${ colors.dangerText }`,
						borderRadius: radii.sm,
						color: colors.dangerText,
						padding: '6px 14px',
						fontSize: '13px',
						fontWeight: 500,
						cursor: isDisabling ? 'progress' : 'pointer',
						whiteSpace: 'nowrap',
						flexShrink: 0,
						opacity: isDisabling ? 0.7 : 1,
					} }
				>
					{ isDisabling
						? __( 'Removing…', 'woocommerce-payments' )
						: __( 'Remove', 'woocommerce-payments' ) }
				</button>
			</div>

			<ConfirmDialog
				isOpen={ isConfirmingDisable }
				onConfirm={ handleDisableConfirm }
				onCancel={ handleDisableCancel }
				confirmButtonText={ __( 'Remove', 'woocommerce-payments' ) }
				cancelButtonText={ __( 'Cancel', 'woocommerce-payments' ) }
			>
				{ __(
					'Stop your storefront from appearing in the Woo Shopping Network? Your settings will be preserved.',
					'woocommerce-payments'
				) }
			</ConfirmDialog>
		</div>
	);
};

export default OverviewDashboard;
