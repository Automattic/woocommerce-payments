/**
 * Internal dependencies
 */
import { PaymentMethodsLogos } from './payment-methods-logos';
import { getCardBrands } from 'wcpay/utils/card-brands';
import { getUPEConfig } from 'wcpay/utils/checkout';

const cardBrandsBreakpointConfigs = [
	{ breakpoint: 550, maxElements: 2 },
	{ breakpoint: 330, maxElements: 1 },
];

/**
 * Payment method label component that uses the WooCommerce Blocks PaymentMethodLabel
 * for the text, with icons rendered as a sibling element for proper positioning.
 *
 * This approach allows the CSS grid to position the label text and icons separately,
 * with the label staying next to the radio button and icons aligned to the right.
 *
 * @param {Object} props Component props passed by WooCommerce Blocks.
 * @param {Object} props.components Components provided by WooCommerce Blocks, including PaymentMethodLabel.
 * @param {string} props.title The payment method title to display.
 * @param {string} props.paymentMethodId The payment method ID (e.g., 'card', 'giropay').
 * @param {string} props.icon The light theme icon URL.
 * @param {string} props.darkIcon The dark theme icon URL.
 * @return {JSX.Element} The payment method label component with icons.
 */
const PaymentMethodLabel = ( {
	components,
	title,
	paymentMethodId,
	icon,
	darkIcon,
} ) => {
	const { PaymentMethodLabel: Label } = components;
	const upeAppearanceTheme = getUPEConfig( 'wcBlocksUPEAppearanceTheme' );

	const renderIcon = () => {
		if ( paymentMethodId === 'card' ) {
			return (
				<PaymentMethodsLogos
					maxElements={ 4 }
					paymentMethods={ getCardBrands() }
					breakpointConfigs={ cardBrandsBreakpointConfigs }
				/>
			);
		}

		const iconSrc =
			upeAppearanceTheme === 'night' && darkIcon ? darkIcon : icon;

		return (
			<img
				className="wcpay-payment-method-icon"
				src={ iconSrc }
				alt={ title }
			/>
		);
	};

	return (
		<span className="wcpay-payment-method-label">
			<Label text={ title } />
			{ renderIcon() }
		</span>
	);
};

export default PaymentMethodLabel;
