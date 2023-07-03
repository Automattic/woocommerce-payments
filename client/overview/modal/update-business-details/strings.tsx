/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	button: __( 'Finish setup', 'woocommerce-payments' ),

	heading: __(
		'Update WooPayments business details',
		'woocommerce-payments'
	),

	restrictedDescription: __(
		'Payments and deposits are disabled for this account until missing information is updated. Please update the following information in the Stripe dashboard.',
		'woocommerce-payments'
	),

	restrictedSoonDescription: __(
		'Additional information is required to verify your business. Update by %s to avoid a disruption in deposits.',
		'woocommerce-payments'
	),

	updateBusinessDetails: __(
		'Update business details',
		'woocommerce-payments '
	),

	cancel: __( 'Cancel', 'woocommerce-payments' ),
};
