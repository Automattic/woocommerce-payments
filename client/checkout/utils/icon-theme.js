/**
 * Internal dependencies
 */
import {
	getBackgroundColor,
	isColorLight,
} from 'wcpay/checkout/upe-styles/utils';

/**
 * Classic checkout: skips .payment_box which may have an explicit light
 * background even on dark themes. Icons sit in the <label> above
 * .payment_box, so we check page-level containers instead.
 */
const classicIconBackgroundSelectors = [
	'#payment',
	'#order_review',
	'form.checkout',
	'body',
];

/**
 * Blocks checkout: icons sit in the same background as the payment elements.
 */
const blocksIconBackgroundSelectors = [
	'#payment-method .wc-block-components-radio-control-accordion-option',
	'#payment-method',
	'form.wc-block-checkout__form',
	'.wc-block-checkout',
	'body',
];

/**
 * Detects whether the icon background is light or dark for a given checkout
 * context and returns the matching Stripe appearance theme name.
 *
 * @param {'classic'|'blocks'} context The checkout context.
 * @return {'stripe'|'night'} The theme to use for payment method icons.
 */
export function getIconTheme( context ) {
	const selectors =
		context === 'classic'
			? classicIconBackgroundSelectors
			: blocksIconBackgroundSelectors;
	const bgColor = getBackgroundColor( selectors );
	return isColorLight( bgColor ) ? 'stripe' : 'night';
}
