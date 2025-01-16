/**
 * External dependencies
 */
import { useMemo } from 'react';

/**
 * Internal dependencies
 */
import GooglePayAssetDark from 'assets/images/cards/google-pay-preview-dark.svg?asset';
import GooglePayAssetLight from 'assets/images/cards/google-pay-preview-light.svg?asset';
import { getExpressCheckoutButtonAppearance } from 'wcpay/express-checkout/utils';

const ExpressCheckoutButtonPreview = ( {
	expressPaymentMethod,
	options,
	buttonAttributes,
} ) => {
	const appearance = useMemo(
		() => getExpressCheckoutButtonAppearance( buttonAttributes ),
		[ buttonAttributes ]
	);

	const buttonStyle = {
		height: `${ options.buttonHeight }px`,
		borderRadius: appearance.variables.borderRadius,
	};

	const theme = options.buttonTheme[ expressPaymentMethod ];

	if ( expressPaymentMethod === 'applePay' ) {
		buttonStyle.WebkitAppearance = '-apple-pay-button';
		if ( theme === 'black' ) {
			buttonStyle.ApplePayButtonStyle = 'black';
		} else if ( theme === 'outline' ) {
			buttonStyle.ApplePayButtonStyle = 'white-outline';
		} else {
			buttonStyle.ApplePayButtonStyle = 'white';
		}
	}

	if ( expressPaymentMethod === 'googlePay' ) {
		if ( theme === 'black' ) {
			buttonStyle.backgroundColor = 'black';
			buttonStyle.backgroundImage = `url(${ GooglePayAssetDark })`;
		} else {
			buttonStyle.backgroundColor = 'white';
			buttonStyle.backgroundImage = `url(${ GooglePayAssetLight })`;
		}
	}

	return (
		<button
			type="button"
			id={ `express-checkout-button-preview-${ expressPaymentMethod }` }
			className="express-checkout-button-preview"
			style={ buttonStyle }
		/>
	);
};

export default ExpressCheckoutButtonPreview;
