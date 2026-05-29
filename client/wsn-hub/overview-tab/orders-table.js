/**
 * OrdersTable — Recent Network orders table inside the Overview dashboard.
 *
 * 7-column table matching the v2 mockup: Order / Customer / Date / Status /
 * Items / Source / Total. Each order links to the WC admin edit page.
 *
 * Empty state: when `orders` is empty, render a single-row message
 * ("No WSN orders yet — first WSN purchase will appear here") with `colspan`
 * across the columns. This is the expected default state until WooPay-side
 * Cohort A/B (RSM-2484/2485) ships order tagging.
 *
 * Owned by RSM-2493.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';
import { colors, typography, radii } from '../tokens';

const HEADER_CELL_STYLE = {
	background: colors.surfaceAdmin,
	textAlign: 'left',
	padding: '10px 16px',
	fontSize: '11px',
	fontWeight: 600,
	textTransform: 'uppercase',
	letterSpacing: '0.04em',
	color: colors.textMuted,
	borderBottom: `1px solid ${ colors.borderSubtle }`,
	whiteSpace: 'nowrap',
};

const BODY_CELL_STYLE = {
	padding: '14px 16px',
	borderBottom: `1px solid ${ colors.surfaceMuted }`,
	verticalAlign: 'middle',
	color: colors.textPrimary,
	fontSize: '13px',
};

const LINK_STYLE = {
	color: colors.infoBorder,
	textDecoration: 'none',
};

// WC's native status pill palette — mirrors WC admin order list.
const STATUS_PILL_STYLES = {
	processing: { background: '#c6e1c6', color: '#5b841b' },
	completed: { background: '#c8d7e1', color: '#2e4453' },
	'on-hold': { background: '#f8dda7', color: '#94660c' },
	pending: { background: '#e5e5e5', color: '#777' },
	cancelled: { background: '#e5e5e5', color: '#777' },
	refunded: { background: '#e5e5e5', color: '#777' },
	failed: { background: '#eba3a3', color: '#761919' },
};

const StatusPill = ( { status, label } ) => {
	const palette = STATUS_PILL_STYLES[ status ] ?? STATUS_PILL_STYLES.pending;
	return (
		<span
			style={ {
				...palette,
				display: 'inline-block',
				padding: '3px 10px',
				borderRadius: radii.sm,
				fontSize: '12px',
				fontWeight: 500,
				lineHeight: 1.4,
				whiteSpace: 'nowrap',
			} }
		>
			{ label }
		</span>
	);
};

/**
 * Format an items array for the Items column. Shows the first item name and
 * collapses extras into a "+N more" suffix so the row stays single-line on
 * narrow viewports.
 *
 * @param {Array} items Order line item names, in display order.
 * @return {string} Human-readable items summary suitable for the table cell.
 */
const formatItems = ( items ) => {
	if ( ! Array.isArray( items ) || items.length === 0 ) {
		return '—';
	}
	if ( items.length === 1 ) {
		return items[ 0 ];
	}
	if ( items.length === 2 ) {
		return items.join( ', ' );
	}
	return `${ items[ 0 ] }, ${ items[ 1 ] }, +${ items.length - 2 } more`;
};

const OrdersTable = ( { orders } ) => {
	const isEmpty = ! orders || orders.length === 0;

	return (
		<div
			style={ {
				background: colors.surface,
				border: `1px solid ${ colors.borderSubtle }`,
				borderRadius: radii.md,
				overflow: 'hidden',
			} }
		>
			<div
				style={ {
					padding: '16px 20px',
					borderBottom: `1px solid ${ colors.borderSubtle }`,
				} }
			>
				<div
					style={ {
						fontSize: '14px',
						fontWeight: 600,
						color: colors.textPrimary,
					} }
				>
					{ __( 'Recent Network orders', 'woocommerce-payments' ) }
				</div>
			</div>
			<div
				style={ {
					overflowX: 'auto',
					WebkitOverflowScrolling: 'touch',
				} }
			>
				<table
					style={ {
						width: '100%',
						borderCollapse: 'collapse',
						...typography.body,
					} }
				>
					<thead>
						<tr>
							<th style={ HEADER_CELL_STYLE }>
								{ __( 'Order', 'woocommerce-payments' ) }
							</th>
							<th style={ HEADER_CELL_STYLE }>
								{ __( 'Customer', 'woocommerce-payments' ) }
							</th>
							<th style={ HEADER_CELL_STYLE }>
								{ __( 'Date', 'woocommerce-payments' ) }
							</th>
							<th style={ HEADER_CELL_STYLE }>
								{ __( 'Status', 'woocommerce-payments' ) }
							</th>
							<th style={ HEADER_CELL_STYLE }>
								{ __( 'Items', 'woocommerce-payments' ) }
							</th>
							<th style={ HEADER_CELL_STYLE }>
								{ __( 'Source', 'woocommerce-payments' ) }
							</th>
							<th
								style={ {
									...HEADER_CELL_STYLE,
									textAlign: 'right',
								} }
							>
								{ __( 'Total', 'woocommerce-payments' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ isEmpty ? (
							<tr>
								<td
									colSpan={ 7 }
									style={ {
										...BODY_CELL_STYLE,
										textAlign: 'center',
										color: colors.textMuted,
										fontStyle: 'italic',
										padding: '32px 16px',
									} }
								>
									{ __(
										'No WSN orders yet — first WSN purchase will appear here.',
										'woocommerce-payments'
									) }
								</td>
							</tr>
						) : (
							orders.map( ( order ) => (
								<tr key={ order.id }>
									<td style={ BODY_CELL_STYLE }>
										<a
											href={ order.edit_url }
											style={ LINK_STYLE }
										>
											{ `#${ order.number }` }
										</a>
									</td>
									<td style={ BODY_CELL_STYLE }>
										{ order.customer_name || '—' }
									</td>
									<td style={ BODY_CELL_STYLE }>
										{ order.date_relative }
									</td>
									<td style={ BODY_CELL_STYLE }>
										<StatusPill
											status={ order.status }
											label={ order.status_label }
										/>
									</td>
									<td style={ BODY_CELL_STYLE }>
										{ formatItems( order.items ) }
									</td>
									<td
										style={ {
											...BODY_CELL_STYLE,
											fontWeight: 600,
										} }
									>
										{ order.source || '—' }
									</td>
									<td
										style={ {
											...BODY_CELL_STYLE,
											textAlign: 'right',
											whiteSpace: 'nowrap',
										} }
									>
										{ order.total_formatted }
									</td>
								</tr>
							) )
						) }
					</tbody>
				</table>
			</div>
		</div>
	);
};

export default OrdersTable;
