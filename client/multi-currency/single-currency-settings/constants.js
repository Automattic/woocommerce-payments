/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const decimalCurrencyRoundingOptions = {
	none: __( 'None', 'woocommerce-payments' ),
	'0.25': '0.25',
	'0.50': '0.50',
	'1.00': '1.00 (recommended)',
	'5.00': '5.00',
	'10.00': '10.00',
};
export const zeroDecimalCurrencyRoundingOptions = {
	'1': '1',
	'10': '10',
	'25': '25',
	'50': '50',
	'100': '100 (recommended)',
	'500': '500',
	'1000': '1000',
};

export const decimalCurrencyCharmOptions = {
	'0.00': __( 'None', 'woocommerce-payments' ),
	'-0.01': '-0.01',
	'-0.05': '-0.05',
};
export const zeroDecimalCurrencyCharmOptions = {
	'0.00': __( 'None', 'woocommerce-payments' ),
	'-1': '-1',
	'-5': '-5',
	'-10': '-10',
	'-20': '-20',
	'-25': '-25',
	'-50': '-50',
	'-100': '-100',
};
