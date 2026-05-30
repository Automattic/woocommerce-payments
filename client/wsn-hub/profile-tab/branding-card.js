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
const BrandingCard = ( { settings, derivations, onChange } ) => {
	const wcGeneralEditUrl = '/wp-admin/admin.php?page=wc-settings&tab=general';

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
				onChange={ ( value ) =>
					onChange( { key: 'logo_override_id', value } )
				}
			/>

			<HeroBannerPicker
				attachmentId={ settings.hero_image_id ?? null }
				resolvedUrl={ derivations.hero_image_url ?? null }
				onChange={ ( value ) =>
					onChange( { key: 'hero_image_id', value } )
				}
			/>

			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: '1fr 1fr',
					gap: spacing.s4,
				} }
			>
				<ReadonlySyncedField
					label={ __( 'Shop name', 'woocommerce-payments' ) }
					value={ derivations.shop_name ?? null }
					syncedFrom={ __(
						'WooCommerce › General › Store name',
						'woocommerce-payments'
					) }
					editUrl={ wcGeneralEditUrl }
				/>
				<ReadonlySyncedField
					label={ __( 'Tagline', 'woocommerce-payments' ) }
					value={ derivations.tagline ?? null }
					syncedFrom={ __(
						'WooCommerce › General › Store tagline',
						'woocommerce-payments'
					) }
					editUrl={ wcGeneralEditUrl }
				/>
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
