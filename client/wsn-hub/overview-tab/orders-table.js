/**
 * OrdersTable — Recent Network orders inside the Overview dashboard.
 *
 * Renders the top-20 WSN-attributed orders returned by
 * GET /wc/v3/payments/wsn/orders as a DataViews table. The visual
 * treatment matches WC core's native Orders list and AI Storefront's
 * Recent AI Orders surface.
 *
 * Why dataviews and not Woo's TableCard: direct port of the rationale
 * from AI Storefront's ai-orders-table.js. Woo components ship as a
 * runtime external on `window.wc.components` whose CSS is only
 * auto-enqueued on native wc-admin screens. Our custom plugin submenu
 * page would render the DOM but get no styles. DataViews lives in the
 * WP dependency-extraction plugin's BUNDLED_PACKAGES list — webpack
 * force-bundles the JS (see webpack/shared.js:178-202) and we import
 * its prebuilt CSS via SCSS (see ../style.scss). Styles travel with
 * our plugin and ride our existing wsn-hub.css enqueue; zero new
 * server-side plumbing.
 *
 * Data contract: `orders` is the array returned verbatim from the
 * REST controller's `format_order()` (see
 * includes/admin/class-wc-rest-payments-wsn-orders-controller.php).
 * Each item carries: id, number, customer_name, date_relative,
 * status, status_label, items[], source (e.g. "wsn-pdp"), total_formatted,
 * edit_url. The endpoint already sorts DESC by created date and caps
 * at 20 rows — DataViews' sort, paginate, and search UI are disabled
 * because they'd misrepresent a capped, fixed-order result. Status
 * filter chips ARE enabled — cheap client-side filter on 20 rows,
 * genuinely useful for triaging by state.
 *
 * Owned by RSM-2493.
 *
 * @format
 */

import { useMemo, useState } from '@wordpress/element';
// Use the `/wp` entrypoint — this is the one client/reports/fees uses and
// the one webpack/shared.js's requestToExternal handler force-bundles
// against. Importing from the package root works at build time but the
// generated module graph drifts in subtle ways from what the host WP
// component contexts expect (Stack experimental APIs in particular —
// the bare path triggers "Cannot read properties of undefined (reading
// 'Stack')" inside the bundled DataViews when nested in host Cards).
import { DataViews } from '@wordpress/dataviews/wp';
import { __, sprintf } from '@wordpress/i18n';
import { colors, typography, radii } from '../tokens';

// WC's native status pill palette — mirrors the WC admin order list and
// matches what the hand-rolled previous implementation showed. Keeping
// the same palette means the DataViews swap is visually invisible to
// merchants who'd already grown used to the pill colors.
const STATUS_PILL_STYLES = {
	processing: { background: '#c6e1c6', color: '#5b841b' },
	completed: { background: '#c8d7e1', color: '#2e4453' },
	'on-hold': { background: '#f8dda7', color: '#94660c' },
	pending: { background: '#e5e5e5', color: '#777' },
	cancelled: { background: '#e5e5e5', color: '#777' },
	refunded: { background: '#e5e5e5', color: '#777' },
	failed: { background: '#eba3a3', color: '#761919' },
};

/**
 * Guard against non-HTTP(S) URL schemes in an href value.
 *
 * JSX escapes attribute values but does NOT filter URL schemes, so an
 * adversarial REST response could land `javascript:` in `<a href>`. The
 * current source for edit_url is admin_url() (safe by construction) but
 * this guard removes the regression class ahead of time. Port of the
 * same helper from AI Storefront's ai-orders-table.js:97.
 *
 * @param {unknown} url Raw URL from the REST response.
 * @return {string|null} URL safe to bind to href, or null.
 */
const safeHref = ( url ) => {
	if ( typeof url !== 'string' || url === '' ) {
		return null;
	}
	try {
		const parsed = new URL( url, window.location.origin );
		if ( parsed.protocol !== 'https:' && parsed.protocol !== 'http:' ) {
			return null;
		}
		return parsed.href;
	} catch ( _error ) {
		return null;
	}
};

/**
 * Colored status pill — matches the previous hand-rolled implementation's
 * shape so the visual rhythm of the table doesn't shift on this refactor.
 *
 * `title` attribute gives sighted users the full label on hover when a
 * narrow viewport truncates the pill — same accessibility affordance
 * WC's own order list uses.
 *
 * @param {Object} root0        Props.
 * @param {string} root0.status WC status key (e.g. "processing").
 * @param {string} root0.label  Localized display label.
 * @return {JSX.Element} The rendered colored status pill.
 */
const StatusPill = ( { status, label } ) => {
	const palette = STATUS_PILL_STYLES[ status ] ?? STATUS_PILL_STYLES.pending;
	return (
		<span
			className={ `order-status status-${ status }` }
			title={ label }
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
 * Format an items array — show the first two names, collapse the rest
 * into "+N more" so the row stays single-line on narrow viewports.
 *
 * @param {Array<string>} items Order line-item names, display order.
 * @return {JSX.Element|string} React node for the cell.
 */
const formatItems = ( items ) => {
	if ( ! Array.isArray( items ) || items.length === 0 ) {
		return '—';
	}
	if ( items.length <= 2 ) {
		return items.join( ', ' );
	}
	const visible = items.slice( 0, 2 ).join( ', ' );
	const overflow = items.length - 2;
	// Single contiguous string (rather than split nodes) so screen readers
	// announce it as one phrase and Testing Library can match it with a
	// plain text query.
	return (
		<span title={ items.join( ', ' ) }>
			{ sprintf(
				/* translators: 1: first two item names joined by comma, 2: count of additional items not shown */
				__( '%1$s, +%2$d more', 'woocommerce-payments' ),
				visible,
				overflow
			) }
		</span>
	);
};

// Initial DataViews view state. Pagination is a noop here because our
// endpoint caps at 20 rows AND we don't expose `paginationInfo` — but
// the type-and-perPage shape is required by DataViews' state model.
const DEFAULT_VIEW = {
	type: 'table',
	perPage: 20,
	fields: [
		'order',
		'customer',
		'date',
		'status',
		'items',
		'source',
		'total',
	],
};

// Plain <div> shell instead of @wordpress/components Card/CardBody.
// Background: DataViews force-bundles its own copy of the WP-components
// Stack family; nesting <DataViews> inside the host <CardBody> puts
// DataViews' bundled internals inside the host CardBody's context
// providers, which try to reach into a Stack export the bundled code
// doesn't see. Plain divs sidestep the context-provider clash entirely.
// See client/reports/fees/index.tsx:256-278 for the same shape.
const RecentNetworkOrdersCard = ( { children } ) => (
	<div
		style={ {
			background: colors.surface,
			border: `1px solid ${ colors.borderSubtle }`,
			borderRadius: radii.md,
			overflow: 'hidden',
		} }
	>
		<div style={ { padding: '16px 20px 12px' } }>
			<h3 style={ { margin: 0, fontSize: '14px' } }>
				{ __( 'Recent Network orders', 'woocommerce-payments' ) }
			</h3>
		</div>
		{ children }
	</div>
);

const EmptyState = () => (
	<RecentNetworkOrdersCard>
		<div
			style={ {
				textAlign: 'center',
				padding: '20px 16px 32px',
				color: colors.textMuted,
				fontStyle: 'italic',
				fontSize: '13px',
			} }
		>
			{ __(
				'No Network orders yet — your first Shopping Network purchase will appear here.',
				'woocommerce-payments'
			) }
		</div>
	</RecentNetworkOrdersCard>
);

const OrdersTable = ( { orders } ) => {
	const [ view, setView ] = useState( DEFAULT_VIEW );

	// Derive the status filter chip's enum from what's actually in the
	// payload. Static enums would dangle empty options for statuses no
	// recent order has — UX noise. Recomputed on data identity so a new
	// fetch refreshes available filter values.
	const statusElements = useMemo( () => {
		const seen = new Map();
		( orders || [] ).forEach( ( o ) => {
			if ( o?.status && ! seen.has( o.status ) ) {
				seen.set( o.status, o.status_label || o.status );
			}
		} );
		return [ ...seen.entries() ]
			.sort( ( a, b ) => a[ 1 ].localeCompare( b[ 1 ] ) )
			.map( ( [ value, label ] ) => ( { value, label } ) );
	}, [ orders ] );

	const fields = useMemo(
		() => [
			{
				id: 'order',
				label: __( 'Order', 'woocommerce-payments' ),
				enableSorting: false,
				render: ( { item } ) => {
					const href = safeHref( item.edit_url );
					const number = '#' + ( item.number ?? '—' );
					if ( ! href ) {
						return (
							<span style={ { fontWeight: 500 } }>
								{ number }
							</span>
						);
					}
					return (
						<a
							href={ href }
							style={ {
								color: colors.infoBorder,
								textDecoration: 'none',
								fontWeight: 500,
							} }
						>
							{ number }
						</a>
					);
				},
				getValue: ( { item } ) => String( item.number ?? '' ),
			},
			{
				id: 'customer',
				label: __( 'Customer', 'woocommerce-payments' ),
				enableSorting: false,
				render: ( { item } ) => item.customer_name || '—',
				getValue: ( { item } ) => item.customer_name || '',
			},
			{
				id: 'date',
				label: __( 'Date', 'woocommerce-payments' ),
				enableSorting: false,
				render: ( { item } ) => (
					<span title={ item.date }>{ item.date_relative }</span>
				),
				getValue: ( { item } ) => item.date || item.date_relative || '',
			},
			{
				id: 'status',
				label: __( 'Status', 'woocommerce-payments' ),
				enableSorting: false,
				elements: statusElements,
				render: ( { item } ) => (
					<StatusPill
						status={ item.status }
						label={ item.status_label }
					/>
				),
				getValue: ( { item } ) => item.status,
			},
			{
				id: 'items',
				label: __( 'Items', 'woocommerce-payments' ),
				enableSorting: false,
				render: ( { item } ) => formatItems( item.items ),
				getValue: ( { item } ) => ( item.items || [] ).join( ', ' ),
			},
			{
				id: 'source',
				label: __( 'Source', 'woocommerce-payments' ),
				enableSorting: false,
				render: ( { item } ) => <strong>{ item.source || '—' }</strong>,
				getValue: ( { item } ) => item.source || '',
			},
			{
				id: 'total',
				label: __( 'Total', 'woocommerce-payments' ),
				enableSorting: false,
				render: ( { item } ) => item.total_formatted || '—',
				getValue: ( { item } ) => item.total_formatted || '',
			},
		],
		[ statusElements ]
	);

	if ( ! orders || orders.length === 0 ) {
		return <EmptyState />;
	}

	return (
		<RecentNetworkOrdersCard>
			<div>
				{ /*
					Header chrome tuned to match our eyebrow tokens
					(12px / 600 / uppercase / 0.04em). Direct port of
					the override block AI Storefront ships at
					ai-orders-table.js:782-831 — DataViews' default
					header is heavier and would clash with our stat-
					card eyebrows above the table. !important is
					required because DataViews' own stylesheet
					(imported at higher specificity through wsn-hub.css)
					targets the same selectors.
				*/ }
				<style>{ `
					.dataviews-view-table thead th {
						background-color: ${ colors.surfaceAdmin } !important;
						font-size: ${ typography.eyebrowLabel.fontSize } !important;
						font-weight: ${ typography.eyebrowLabel.fontWeight } !important;
						text-transform: ${ typography.eyebrowLabel.textTransform } !important;
						letter-spacing: ${ typography.eyebrowLabel.letterSpacing } !important;
						color: ${ colors.textMuted } !important;
						padding-top: 6px !important;
						padding-bottom: 6px !important;
					}
					.dataviews-view-table:not(.has-compact-density):not(.has-comfortable-density) td {
						padding-top: 8px !important;
						padding-bottom: 8px !important;
					}
					.dataviews-view-table-wrapper {
						overflow: hidden !important;
					}
				` }</style>
				<DataViews
					data={ orders }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					defaultLayouts={ { table: {} } }
					getItemId={ ( item ) => String( item.id ) }
					actions={ [] }
					search={ false }
					/*
					 * paginationInfo is required by DataViews even when
					 * we don't paginate — passing undefined throws
					 * inside its pagination footer's render. Surface
					 * the full row count as a single page so "X of X"
					 * reads truthfully and totalPages = 1 disables the
					 * nav arrows.
					 */
					paginationInfo={ {
						totalItems: orders.length,
						totalPages: 1,
					} }
				/>
			</div>
		</RecentNetworkOrdersCard>
	);
};

export default OrdersTable;
