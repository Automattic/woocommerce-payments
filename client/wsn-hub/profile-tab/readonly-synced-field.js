/**
 * ReadonlySyncedField — display-only field whose value comes from somewhere
 * the merchant edits at the source (WooCommerce → General for shop name /
 * tagline, WooCommerce → Shipping for zones, etc.). The Profile tab surfaces
 * the value so the merchant can see what WSN sees, but doesn't let them
 * change it here — single source of truth lives in the linked admin page.
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { createInterpolateElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { colors, typography, spacing, radii } from '../tokens';

// Stable unique ID generator for label/input pairing. `@wordpress/element`'s
// `useId` isn't available in the React version WCPay's wp-scripts ships, so
// roll our own from useState + Math.random — the initializer runs once on
// mount, giving us a stable per-instance ID across re-renders.
let idCounter = 0;
const nextId = () =>
	`wcpay-wsn-syncfield-${ Date.now().toString( 36 ) }-${ ++idCounter }`;

/**
 * @param {Object}      props
 * @param {string}      props.label      Field label (renders as eyebrow caps).
 * @param {string|null} props.value      Display value. Null/empty renders an em-dash.
 * @param {string}      props.syncedFrom Source attribution text — e.g. "WooCommerce › General › Store name".
 * @param {string}      [props.editUrl]  When provided, the syncedFrom text becomes a link to this URL.
 */
const ReadonlySyncedField = ( { label, value, syncedFrom, editUrl } ) => {
	const display = value && value.length > 0 ? value : '—';
	const [ inputId ] = useState( nextId );

	// When an editUrl is provided, render the source attribution as a link so
	// the merchant can jump to where the value is edited. Falls back to plain
	// text when no URL is given (e.g., theme branding which has no admin page).
	const helperText = editUrl ? (
		createInterpolateElement(
			/* translators: %s preserved as the <a/> tag */
			__( 'Synced from <a>%s</a>', 'woocommerce-payments' ).replace(
				'%s',
				syncedFrom
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href={ editUrl }
						style={ { color: colors.infoBorder } }
					/>
				),
			}
		)
	) : (
		<>
			{ __( 'Synced from', 'woocommerce-payments' ) } { syncedFrom }
		</>
	);

	return (
		<div
			style={ {
				display: 'flex',
				flexDirection: 'column',
				gap: spacing.s1,
			} }
		>
			<label
				htmlFor={ inputId }
				style={ {
					...typography.eyebrowLabel,
					color: colors.textMuted,
				} }
			>
				{ label }
			</label>
			<input
				id={ inputId }
				type="text"
				readOnly
				value={ display }
				style={ {
					border: `1px solid ${ colors.borderStrong }`,
					borderRadius: radii.sm,
					padding: '7px 10px',
					fontSize: '13px',
					color: colors.textMuted,
					background: colors.surfaceAdmin,
					cursor: 'default',
				} }
			/>
			<span
				style={ {
					fontSize: '11px',
					color: colors.textMuted,
					marginTop: '3px',
				} }
			>
				{ helperText }
			</span>
		</div>
	);
};

export default ReadonlySyncedField;
