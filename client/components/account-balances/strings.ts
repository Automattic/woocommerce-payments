/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const greetingStrings = {
	withName: {
		/** translators: %s name of the person being greeted. */
		morning: __( 'Good morning, %s', 'woocommerce-payments' ),
		/** translators: %s name of the person being greeted. */
		afternoon: __( 'Good afternoon, %s', 'woocommerce-payments' ),
		/** translators: %s name of the person being greeted. */
		evening: __( 'Good evening, %s', 'woocommerce-payments' ),
	},
	withoutName: {
		morning: __( 'Good morning', 'woocommerce-payments' ),
		afternoon: __( 'Good afternoon', 'woocommerce-payments' ),
		evening: __( 'Good evening', 'woocommerce-payments' ),
	},
};

export const fundLabelStrings = {
	available: __( 'Available funds', 'woocommerce-payments' ),
	pending: __( 'Pending funds', 'woocommerce-payments' ),
	reserved: __( 'Reserved funds', 'woocommerce-payments' ),
};

export const learnMoreString = __( 'Learn more.', 'woocommerce-payments' );

export const fundTooltipStrings = {
	available: __(
		'The amount of funds available to be deposited.',
		'woocommerce-payments'
	),
	availableNegativeBalance: __(
		'Learn more about why your account balance may be negative.',
		'woocommerce-payments'
	),
	pending: __(
		'The amount of funds still in the %d day pending period.',
		'woocommerce-payments'
	),
	reserved: __(
		'The amount of funds being held in reserve.',
		'woocommerce-payments'
	),
};

export const documentationUrls = {
	depositSchedule:
		'https://woocommerce.com/document/woocommerce-payments/deposits/deposit-schedule',
	reservedFunds:
		'https://woocommerce.com/document/woocommerce-payments/our-policies/reserves',
	negativeBalance:
		'https://woocommerce.com/document/woocommerce-payments/fees-and-debits/account-showing-negative-balance',
};

/** translators: %s is the currency code, e.g. USD. */
export const currencyBalanceString = __( '%s Balance', 'woocommerce-payments' );
