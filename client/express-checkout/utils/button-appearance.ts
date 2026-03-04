/**
 * Internal dependencies
 */
import { getDefaultBorderRadius } from 'wcpay/utils/express-checkout';
import { getExpressCheckoutData } from './express-checkout-data';

export type ButtonAttributesType =
	| { height: string; borderRadius: string }
	| undefined;

/**
 * Returns the appearance settings for the Express Checkout buttons.
 * Currently only configures border radius for the buttons.
 */
export const getExpressCheckoutButtonAppearance = (
	buttonAttributes: ButtonAttributesType
) => {
	let borderRadius = getDefaultBorderRadius();
	const buttonSettings = getExpressCheckoutData( 'button' );

	// Border radius from WooPayments settings
	borderRadius = buttonSettings?.radius ?? borderRadius;

	// Border radius from Cart & Checkout blocks attributes
	if ( typeof buttonAttributes !== 'undefined' ) {
		borderRadius = Number( buttonAttributes?.borderRadius ) ?? borderRadius;
	}

	return {
		variables: {
			borderRadius: `${ borderRadius }px`,
			spacingUnit: '6px',
		},
	};
};
