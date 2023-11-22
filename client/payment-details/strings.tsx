/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

// Content for test mode notice.
export const notice = {
	content: __(
		'Viewing test payments. To view live payments, disable test mode in ',
		'woocommerce-payments'
	),
	action: sprintf(
		/* translators: %s: WooPayments */
		__( '%s settings.', 'woocommerce-payments' ),
		'WooPayments'
	),
	details: sprintf(
		/* translators: %s: WooPayments */
		__(
			'%s was in test mode when these orders were placed.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
};
