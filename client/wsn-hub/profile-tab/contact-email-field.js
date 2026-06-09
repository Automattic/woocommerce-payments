/**
 * ContactEmailField — three-state email control for the WSN storefront
 * contact line.
 *
 * The wire contract is three-state (`null | "" | "email"`) — this component
 * exposes it as two ergonomic affordances over a single text input:
 *
 *   - **Unset / null** — the input shows EMPTY and the placeholder reads
 *     "Using <default> (from WooCommerce email settings)". The merchant
 *     hasn't expressed a preference; the composer falls back to
 *     `default_contact_email` (the WC-derived value).
 *
 *   - **Explicit empty** — the merchant typed in the field then cleared it,
 *     OR clicked "Use no contact email". Saved as `""`. The composer
 *     treats this as a deliberate "no contact" choice and does NOT fall
 *     back to the WC default. A small "Reset to WC default" link appears.
 *
 *   - **Explicit override** — the merchant typed a different email. Saved
 *     as the sanitized string. A "Reset to default" link appears so they
 *     can return to null without re-typing.
 *
 * Why a custom component instead of a plain <input>: the three-state
 * distinction would be lost if we shipped `e.target.value || null` on
 * every change — the "user cleared the field" signal becomes
 * indistinguishable from "user hasn't touched the field". We need to know
 * the difference (the former is an explicit override, the latter is "use
 * default").
 *
 * Owned by RSM-2481.
 *
 * @format
 */

import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { colors, spacing, radii } from '../tokens';

/**
 * @param {Object}      props
 * @param {string|null} props.contactEmail        Saved override: null = unset, '' = explicit empty, string = explicit override.
 * @param {string|null} props.defaultContactEmail WC-derived default (reply-to-address or from-address), or null when WC has none.
 * @param {Function}    props.onChange            Called with the new three-state value.
 */
const ContactEmailField = ( {
	contactEmail,
	defaultContactEmail,
	onChange,
} ) => {
	const isUnset = contactEmail === null || contactEmail === undefined;
	const isExplicitEmpty = contactEmail === '';
	const isOverride =
		typeof contactEmail === 'string' && contactEmail.length > 0;

	// What the merchant SEES in the input. Empty string for both unset and
	// explicit-empty states (the placeholder copy differs between them so
	// the merchant knows which state they're in).
	const inputValue = isOverride ? contactEmail : '';

	// Placeholder shows the default email bare — the "Using ... (synced from
	// WooCommerce)" framing lives in the helperText below, so the input
	// itself reads as a normal email field with a useful suggestion.
	const placeholder = isUnset
		? defaultContactEmail ??
		  __( 'hello@example.com', 'woocommerce-payments' )
		: __( 'No contact email', 'woocommerce-payments' );

	const helperText = ( () => {
		if ( isUnset ) {
			return defaultContactEmail
				? __(
						'Synced from your WooCommerce email settings. Type to override.',
						'woocommerce-payments'
				  )
				: __(
						'Add an email so shoppers can reach you from your Shopping Network storefront.',
						'woocommerce-payments'
				  );
		}
		if ( isExplicitEmpty ) {
			return __(
				'No contact email will be shown on your Shopping Network storefront.',
				'woocommerce-payments'
			);
		}
		return __(
			'Custom contact email for your Shopping Network storefront.',
			'woocommerce-payments'
		);
	} )();

	const handleInputChange = ( e ) => {
		// An empty input from the user is an explicit empty (they typed
		// then cleared, OR they opened a previously-set field and cleared
		// it). That MUST be persisted as `''`, not converted to null —
		// otherwise the next render goes back to "Using <default>".
		// Resetting to default is a separate affordance below.
		const next = e.target.value;
		onChange( next );
	};

	const handleResetToDefault = () => {
		onChange( null );
	};

	const handleClear = () => {
		onChange( '' );
	};

	return (
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
				value={ inputValue }
				onChange={ handleInputChange }
				placeholder={ placeholder }
				style={ {
					border: `1px solid ${ colors.borderStrong }`,
					borderRadius: radii.sm,
					padding: '7px 10px',
					fontSize: '13px',
					color: colors.textPrimary,
					background: colors.surface,
				} }
			/>

			<div
				style={ {
					display: 'flex',
					alignItems: 'baseline',
					justifyContent: 'space-between',
					gap: spacing.s3,
					marginTop: '3px',
				} }
			>
				<span
					style={ {
						fontSize: '11px',
						color: colors.textMuted,
						lineHeight: 1.5,
						flex: 1,
						minWidth: 0,
					} }
				>
					{ helperText }
				</span>

				{ /* Affordances:
				     - When unset AND a default exists, offer "Use no contact email"
				       (set to '') so the merchant can deliberately opt out.
				     - When override OR explicit-empty, offer "Reset to default"
				       (set to null) so they can return to the WC-synced value. */ }
				{ isUnset && defaultContactEmail && (
					<Button
						variant="link"
						onClick={ handleClear }
						style={ {
							fontSize: '11px',
							padding: 0,
							color: colors.textSecondary,
							flexShrink: 0,
						} }
					>
						{ __( 'Use no contact email', 'woocommerce-payments' ) }
					</Button>
				) }
				{ ( isOverride || isExplicitEmpty ) && defaultContactEmail && (
					<Button
						variant="link"
						onClick={ handleResetToDefault }
						style={ {
							fontSize: '11px',
							padding: 0,
							color: colors.textSecondary,
							flexShrink: 0,
						} }
					>
						{ __( 'Reset to default', 'woocommerce-payments' ) }
					</Button>
				) }
			</div>
		</div>
	);
};

export default ContactEmailField;
