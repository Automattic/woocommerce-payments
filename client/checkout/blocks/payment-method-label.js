/**
 * Internal dependencies
 */
import { PaymentMethodsLogos } from './payment-methods-logos';
import { getCardBrands } from 'wcpay/utils/card-brands';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { __ } from '@wordpress/i18n';
import './style.scss';

const breakpointConfigs = [
	{ breakpoint: 550, maxElements: 2 },
	{ breakpoint: 330, maxElements: 1 },
];

/**
 * Standard payment method label component that uses the WooCommerce Blocks PaymentMethodLabel.
 * This follows the standard pattern used by other payment extensions.
 *
 * @param {Object} props Component props passed by WooCommerce Blocks.
 * @param {Object} props.components Components provided by WooCommerce Blocks, including PaymentMethodLabel.
 * @param {string} props.title The payment method title to display.
 * @return {JSX.Element} The payment method label component.
 */
export const StandardPaymentMethodLabel = ( { components, title } ) => {
	const { PaymentMethodLabel } = components;
	const isTestMode = getUPEConfig( 'testMode' );

	return (
		<span className="wcpay-payment-method-label">
			<PaymentMethodLabel text={ title } />
			{ isTestMode && (
				<span className="test-mode badge">
					{ __( 'Test Mode', 'woocommerce-payments' ) }
				</span>
			) }
		</span>
	);
};

/**
 * Card payment method label component with card brand logos.
 * This is a special case that maintains the current card brands display.
 *
 * @param {Object} props Component props.
 * @param {Object} props.components Components provided by WooCommerce Blocks, including PaymentMethodLabel.
 * @param {string} props.title The payment method title to display.
 * @return {JSX.Element} The card payment method label with logos.
 */
export const CardPaymentMethodLabel = ( { components, title } ) => {
	const { PaymentMethodLabel } = components;
	const isTestMode = getUPEConfig( 'testMode' );

	return (
		<span className="wcpay-card-label">
			<PaymentMethodLabel text={ title } />
			{ isTestMode && (
				<span className="test-mode badge">
					{ __( 'Test Mode', 'woocommerce-payments' ) }
				</span>
			) }
			<PaymentMethodsLogos
				maxElements={ 4 }
				paymentMethods={ getCardBrands() }
				breakpointConfigs={ breakpointConfigs }
			/>
		</span>
	);
};

// Default export for backward compatibility
export default CardPaymentMethodLabel;
