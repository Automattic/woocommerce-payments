/** @format **/

/**
 * External dependencies
 */

import { __ } from '@wordpress/i18n';

const PaymentDetailsPaymentMethodCheck = ( props ) => {
	const { checked } = props;

	switch ( checked ) {
		case 'pass':
			return __( 'Passed', 'woocommerce-payments' );
		case 'fail':
			return __( 'Failed', 'woocommerce-payments' );
		case 'unavailable':
			return __( 'Unavailable', 'woocommerce-payments' );
		default:
			return __( 'Not checked', 'woocommerce-payments' );
	}
};

export default PaymentDetailsPaymentMethodCheck;
