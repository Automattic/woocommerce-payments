/**
 * BrandingCard — first of two Profile tab cards.
 *
 * Composition: LogoWithOverride + HeroBannerPicker + readonly Shop name +
 * readonly Tagline + theme branding SyncBadge.
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';

import LogoWithOverride from './logo-with-override';
import HeroBannerPicker from './hero-banner-picker';
import ReadonlySyncedField from './readonly-synced-field';
import SyncBadge from './sync-badge';
import { colors, typography, spacing, radii } from '../tokens';

/**
 * @param {Object}   props
 * @param {Object}   props.settings    Current settings blob (mirrors WSN_Settings::get_all()).
 * @param {Object}   props.derivations Resolved derivations from the GET /wsn/settings response.
 * @param {Function} props.onChange    Called with `{ key, value }` to update a single setting locally.
 */
// Compose the human-readable store location string from derivations.location.
// Returns null when nothing useful resolved so ReadonlySyncedField shows the
// em-dash placeholder. Prefer labels (e.g. "California") over codes ("CA")
// when available — codes are still what we ship to WooPay, but the merchant
// UI shows the friendlier form. Order: City, Region, Country.
const formatStoreLocation = ( location ) => {
	if ( ! location ) {
		return null;
	}
	const parts = [
		location.city || null,
		location.region_label || location.region || null,
		location.country_label || location.country || null,
	].filter( Boolean );
	return parts.length > 0 ? parts.join( ', ' ) : null;
};

const BrandingCard = ( { settings, derivations, onChange } ) => {
	// Shop name + tagline come from WP's blogname/blogdescription
	// (get_bloginfo('name')/get_bloginfo('description') on the PHP side),
	// which are edited in WP > Settings > General — NOT WC > General.
	const wpGeneralEditUrl = '/wp-admin/options-general.php';
	// Store location (city / state / country) lives on the WC General
	// settings page — separate URL from WP General above.
	const wcGeneralEditUrl = '/wp-admin/admin.php?page=wc-settings&tab=general';
	const storeLocation = formatStoreLocation( derivations.location );

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
				{ __( 'Branding', 'woocommerce-payments' ) }
			</div>

			<LogoWithOverride
				overrideId={ settings.logo_override_id ?? null }
				resolvedUrl={ derivations.logo_url ?? null }
				logoSource={ derivations.logo_source ?? 'site_logo' }
				onChange={ ( value, previewUrl ) =>
					onChange( {
						key: 'logo_override_id',
						value,
						previewUrl,
					} )
				}
			/>

			<HeroBannerPicker
				attachmentId={ settings.hero_image_id ?? null }
				resolvedUrl={ derivations.hero_image_url ?? null }
				onChange={ ( value, previewUrl ) =>
					onChange( {
						key: 'hero_image_id',
						value,
						previewUrl,
					} )
				}
			/>

			<div
				style={ {
					display: 'grid',
					// auto-fit collapses cells to a single column when the
					// viewport can't fit two ≥260px columns. Wraps around
					// ~580px-580px viewport (depending on card padding) so
					// content stays readable on phones without a
					// matchMedia branch.
					gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
					gap: spacing.s4,
				} }
			>
				<ReadonlySyncedField
					label={ __( 'Shop name', 'woocommerce-payments' ) }
					value={ derivations.shop_name ?? null }
					syncedFrom={ __(
						'Settings › General › Site Title',
						'woocommerce-payments'
					) }
					editUrl={ wpGeneralEditUrl }
				/>
				<ReadonlySyncedField
					label={ __( 'Tagline', 'woocommerce-payments' ) }
					value={ derivations.tagline ?? null }
					syncedFrom={ __(
						'Settings › General › Tagline',
						'woocommerce-payments'
					) }
					editUrl={ wpGeneralEditUrl }
				/>
				{ /*
					Store location spans the grid's full width — one
					combined "City, State, Country" string reads better
					than three skinny cells, and it tracks how merchants
					actually think of their store address.
				*/ }
				<div style={ { gridColumn: '1 / -1' } }>
					<ReadonlySyncedField
						label={ __( 'Store location', 'woocommerce-payments' ) }
						value={ storeLocation }
						syncedFrom={ __(
							'WooCommerce › Settings › General › Store Address',
							'woocommerce-payments'
						) }
						editUrl={ wcGeneralEditUrl }
					/>
				</div>
			</div>

			<div
				style={ { marginTop: spacing.s4 } }
				role="group"
				aria-labelledby="wcpay-wsn-theme-branding-label"
			>
				<div
					id="wcpay-wsn-theme-branding-label"
					style={ {
						...typography.eyebrowLabel,
						color: colors.textMuted,
						display: 'block',
						marginBottom: spacing.s1,
					} }
				>
					{ __( 'Theme branding', 'woocommerce-payments' ) }
				</div>
				<SyncBadge themeType={ derivations.theme_type ?? 'classic' } />
			</div>
		</div>
	);
};

export default BrandingCard;
