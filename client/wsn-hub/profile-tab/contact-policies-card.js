/**
 * ContactPoliciesCard — second of two Profile tab cards.
 *
 * Composition: editable Contact email + RefundPagePicker + readonly Shipping
 * regions + readonly Free shipping.
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { __, sprintf } from '@wordpress/i18n';

import ContactEmailField from './contact-email-field';
import ReadonlySyncedField from './readonly-synced-field';
import RefundPagePicker from './refund-page-picker';
import { colors, spacing, radii } from '../tokens';

const WC_SHIPPING_ZONES_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=shipping';

// Middle-dot separator with non-breaking spaces — matches the pattern
// the legacy free-shipping summarizer rendered, keeps short labels from
// orphaning onto the next line.
const ZONE_SEPARATOR = ' · ';

/**
 * Build a single human-readable label for a zone using its
 * `zone_locations` + `is_rest_of_world` flag.
 *
 * Rules:
 *   - `is_rest_of_world: true` → "Rest of World"
 *   - has locations → comma-joined location codes ("US", "US, CA, MX")
 *   - no locations and not rest of world → returns null (caller skips)
 *
 * @param {Object} zone One entry from derivations.shipping_zones.
 * @return {string|null} The label string, or null when the zone has no
 *                       resolvable label (no locations, not rest of world).
 */
const formatZoneLabel = ( zone ) => {
	if ( ! zone ) return null;
	if ( zone.is_rest_of_world ) {
		return __( 'Rest of World', 'woocommerce-payments' );
	}
	const codes = ( zone.zone_locations || [] )
		.map( ( loc ) => ( loc && loc.code ? String( loc.code ) : '' ) )
		.filter( Boolean );
	return codes.length > 0 ? codes.join( ', ' ) : null;
};

/**
 * Build the free-shipping fragment for a single zone, or null if the
 * zone has no qualifying free-shipping terms.
 *
 * "Free shipping (US)" when min_amount is 0; "Orders over X (US)"
 * otherwise. No currency formatting here — the merchant's store
 * currency is implicit in the storefront context.
 *
 * @param {Object} zone One entry from derivations.shipping_zones.
 * @return {string|null} The free-shipping fragment, or null if the zone
 *                       has no qualifying free-shipping terms.
 */
const formatFreeShippingFragment = ( zone ) => {
	const label = formatZoneLabel( zone );
	const fs = zone?.free_shipping;
	if ( ! label || ! fs ) {
		return null;
	}
	if ( ! ( fs.min_amount > 0 ) ) {
		return sprintf(
			/* translators: %s: shipping zone label (e.g. "US, CA" or "Rest of World") */
			__( 'Free shipping (%s)', 'woocommerce-payments' ),
			label
		);
	}
	return sprintf(
		/* translators: 1: minimum order amount, 2: shipping zone label */
		__( 'Orders over %1$s (%2$s)', 'woocommerce-payments' ),
		fs.min_amount,
		label
	);
};

/**
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Object}   props.derivations
 * @param {Function} props.onChange
 */
const ContactPoliciesCard = ( { settings, derivations, onChange } ) => {
	const zones = Array.isArray( derivations.shipping_zones )
		? derivations.shipping_zones
		: [];

	// "Shipping regions" = every zone's location label, comma-joined.
	// Zones with no resolvable label (no locations, not rest of world)
	// are skipped — they'd contribute "?" which reads as bug noise.
	const shippingRegionLabels = zones
		.map( ( z ) => formatZoneLabel( z ) )
		.filter( Boolean );
	const shippingRegionsLabel =
		shippingRegionLabels.length > 0
			? shippingRegionLabels.join( ', ' )
			: null;

	// "Free shipping" = only zones that HAVE free-shipping terms, each
	// rendered as "Orders over X (label)" or "Free shipping (label)".
	const freeShippingFragments = zones
		.map( ( z ) => formatFreeShippingFragment( z ) )
		.filter( Boolean );
	const freeShippingLabel =
		freeShippingFragments.length > 0
			? freeShippingFragments.join( ZONE_SEPARATOR )
			: null;

	return (
		<div
			style={ {
				background: colors.surface,
				border: `1px solid ${ colors.borderSubtle }`,
				borderRadius: radii.md,
				padding: `${ spacing.s5 } ${ spacing.s6 }`,
				marginBottom: spacing.s4,
			} }
		>
			<div
				style={ {
					fontSize: '11px',
					fontWeight: 600,
					color: colors.textMuted,
					textTransform: 'uppercase',
					letterSpacing: '0.04em',
					paddingBottom: spacing.s3,
					marginBottom: spacing.s4,
					borderBottom: `1px solid ${ colors.borderSubtle }`,
				} }
			>
				{ __( 'Contact & Policies', 'woocommerce-payments' ) }
			</div>

			<div
				style={ {
					display: 'grid',
					// auto-fit collapses to single column when the
					// viewport can't fit two ≥260px columns. Same
					// pattern used in BrandingCard.
					gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
					gap: spacing.s4,
				} }
			>
				<ContactEmailField
					contactEmail={ settings.contact_email }
					defaultContactEmail={
						derivations.default_contact_email ?? null
					}
					onChange={ ( value ) =>
						onChange( { key: 'contact_email', value } )
					}
				/>

				<RefundPagePicker
					pageId={ settings.refund_page_id ?? null }
					editUrl={
						settings.refund_page_id
							? `/wp-admin/post.php?post=${ settings.refund_page_id }&action=edit`
							: null
					}
					onChange={ ( value ) =>
						onChange( { key: 'refund_page_id', value } )
					}
				/>

				<ReadonlySyncedField
					label={ __( 'Shipping regions', 'woocommerce-payments' ) }
					value={ shippingRegionsLabel }
					syncedFrom={ __(
						'WooCommerce › Shipping zones',
						'woocommerce-payments'
					) }
					editUrl={ WC_SHIPPING_ZONES_URL }
				/>

				<ReadonlySyncedField
					label={ __( 'Free shipping', 'woocommerce-payments' ) }
					value={ freeShippingLabel }
					syncedFrom={ __(
						'Free Shipping methods in your zones',
						'woocommerce-payments'
					) }
					editUrl={ WC_SHIPPING_ZONES_URL }
				/>
			</div>
		</div>
	);
};

export default ContactPoliciesCard;
