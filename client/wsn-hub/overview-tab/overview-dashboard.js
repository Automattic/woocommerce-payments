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

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import StatCard from './stat-card';
import OrdersTable from './orders-table';
import { colors, typography, spacing, radii } from '../tokens';

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
	const [ period, setPeriod ] = useState( '30d' );
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isDisabling, setIsDisabling ] = useState( false );

	useEffect( () => {
		let cancelled = false;
		setIsLoading( true );
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
			.catch( () => {
				if ( ! cancelled ) {
					// Show empty state on fetch error rather than crashing —
					// stats render as `—` and orders table shows empty-row.
					setData( { is_empty: true, stats: {}, orders: [] } );
					setIsLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ period ] );

	const handleDisable = async () => {
		if (
			! window.confirm(
				__(
					'Stop your storefront from appearing in the Woo Shopping Network? Your settings will be preserved.',
					'woocommerce-payments'
				)
			)
		) {
			return;
		}
		setIsDisabling( true );
		try {
			await apiFetch( {
				path: '/wc/v3/payments/wsn/settings',
				method: 'PUT',
				data: { enabled: false },
			} );
			onDisable();
		} catch ( e ) {
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

			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))',
					gap: spacing.s3,
					marginBottom: spacing.s6,
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
					onClick={ handleDisable }
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
		</div>
	);
};

export default OverviewDashboard;
