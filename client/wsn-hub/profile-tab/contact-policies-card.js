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

import { __ } from '@wordpress/i18n';

import ReadonlySyncedField from './readonly-synced-field';
import RefundPagePicker from './refund-page-picker';
import { colors, spacing, radii } from '../tokens';

const WC_SHIPPING_ZONES_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=shipping';

/**
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {Object}   props.derivations
 * @param {Function} props.onChange
 */
const ContactPoliciesCard = ( { settings, derivations, onChange } ) => {
	const shippingRegionsLabel =
		derivations.shipping_regions && derivations.shipping_regions.length > 0
			? derivations.shipping_regions.join( ', ' )
			: null;

	const freeShippingLabel = derivations.free_shipping?.has_free_shipping
		? derivations.free_shipping.human_summary
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
					gridTemplateColumns: '1fr 1fr',
					gap: spacing.s4,
				} }
			>
				<div
					style={ {
						display: 'flex',
						flexDirection: 'column',
						gap: spacing.s1,
					} }
				>
					<label
						htmlFor="wcpay-wsn-contact-email"
						style={ {
							fontSize: '11px',
							fontWeight: 600,
							textTransform: 'uppercase',
							letterSpacing: '0.04em',
							color: colors.textMuted,
						} }
					>
						{ __( 'Contact email', 'woocommerce-payments' ) }
					</label>
					<input
						id="wcpay-wsn-contact-email"
						type="email"
						value={ settings.contact_email ?? '' }
						onChange={ ( e ) =>
							onChange( {
								key: 'contact_email',
								value: e.target.value || null,
							} )
						}
						placeholder={ __(
							'hello@example.com',
							'woocommerce-payments'
						) }
						style={ {
							border: `1px solid ${ colors.borderStrong }`,
							borderRadius: radii.sm,
							padding: '7px 10px',
							fontSize: '13px',
							color: colors.textPrimary,
							background: colors.surface,
						} }
					/>
				</div>

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
