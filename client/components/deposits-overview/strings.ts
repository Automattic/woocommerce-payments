/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	heading: __( 'Deposits', 'woocommerce-payments' ),
	next_deposits: {
		title: __( 'Next deposits', 'woocommerce-payments' ),
		description: __(
			'The amount may change while payments are still accumulating',
			'woocommerce-payments'
		),
	},
};
