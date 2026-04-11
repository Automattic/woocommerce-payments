/**
 * External dependencies
 */
import { memo, useMemo, useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PaymentMethodsLogos } from './payment-methods-logos';
import { getCardBrands } from 'wcpay/utils/card-brands';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getCachedTheme } from 'wcpay/utils/appearance-cache';
import { getIconTheme } from 'wcpay/checkout/utils/icon-theme';

const cardBrandsBreakpointConfigs = [
	{ breakpoint: 550, maxElements: 2 },
	{ breakpoint: 330, maxElements: 1 },
];

/**
 * Renders the payment method icon along with the test mode badge.
 * The badge is included here so it can be positioned via CSS within the same flex container.
 * For card payments, shows card brand logos.
 * For other payment methods, shows the payment method icon.
 *
 * @param {Object} props Component props.
 * @param {string} props.paymentMethodId The payment method ID (e.g., 'card', 'giropay').
 * @param {string} props.icon The icon URL.
 * @param {string} props.title The payment method title for alt text.
 * @return {JSX.Element} The payment method icon component.
 */
const PaymentMethodIcon = memo( function PaymentMethodIcon( {
	paymentMethodId,
	icon,
	darkIcon,
	title,
} ) {
	const isTestMode = getUPEConfig( 'testMode' );
	const stylesCacheVersion = getUPEConfig( 'stylesCacheVersion' );

	const [ theme, setTheme ] = useState( () => {
		const cached = getCachedTheme( 'blocks_checkout', stylesCacheVersion );
		if ( cached ) {
			return cached;
		}
		// First load with empty cache: determine theme from page background.
		return getIconTheme( 'blocks' );
	} );

	useEffect( () => {
		const handler = () =>
			setTheme( getCachedTheme( 'blocks_checkout', stylesCacheVersion ) );
		window.addEventListener( 'wcpay-appearance-cached', handler );
		return () =>
			window.removeEventListener( 'wcpay-appearance-cached', handler );
	}, [ stylesCacheVersion ] );

	const testModeBadge = isTestMode && (
		<span className="test-mode badge">
			{ __( 'Test Mode', 'woocommerce-payments' ) }
		</span>
	);

	if ( paymentMethodId === 'card' ) {
		return (
			<>
				{ testModeBadge }
				<PaymentMethodsLogos
					maxElements={ 4 }
					paymentMethods={ getCardBrands() }
					breakpointConfigs={ cardBrandsBreakpointConfigs }
				/>
			</>
		);
	}

	const iconSrc = theme === 'night' && darkIcon ? darkIcon : icon;

	return (
		<>
			{ testModeBadge }
			<img
				className="wcpay-payment-method-icon"
				src={ iconSrc }
				alt={ title }
			/>
		</>
	);
} );

/**
 * Payment method label component that uses the WooCommerce Blocks PaymentMethodLabel
 * with the icon prop for proper icon positioning.
 *
 * @param {Object} props Component props passed by WooCommerce Blocks.
 * @param {Object} props.components Components provided by WooCommerce Blocks, including PaymentMethodLabel.
 * @param {string} props.title The payment method title to display.
 * @param {string} props.paymentMethodId The payment method ID (e.g., 'card', 'giropay').
 * @param {string} props.icon The icon URL.
 * @return {JSX.Element} The payment method label component.
 */
const PaymentMethodLabel = ( {
	components,
	title,
	paymentMethodId,
	icon,
	darkIcon,
} ) => {
	const { PaymentMethodLabel: Label } = components;

	const iconProp = useMemo(
		() => (
			<PaymentMethodIcon
				paymentMethodId={ paymentMethodId }
				icon={ icon }
				darkIcon={ darkIcon }
				title={ title }
			/>
		),
		[ paymentMethodId, icon, darkIcon, title ]
	);

	return useMemo( () => <Label text={ title } icon={ iconProp } />, [
		title,
		iconProp,
	] );
};

export default PaymentMethodLabel;
