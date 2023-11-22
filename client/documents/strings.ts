/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

// Content for test mode notice.
export const notice = {
	content: __(
		'Viewing test documents. To view live documents, disable test mode in ',
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
			'%s was in test mode when these documents were created.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
};

// Mapping of transaction types to display string.
export const displayType = {
	vat_invoice: __( 'VAT Invoice', 'woocommerce-payments' ),
};
