/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const strings: {
	[ key: string ]: string;
} = {
	estimated: __( 'Estimated', 'woocommerce-payments' ),
	processing: __( 'Processing', 'woocommerce-payments' ),
	sent: __( 'Sent', 'woocommerce-payments' ),
	failed: __( 'Failed', 'woocommerce-payments' ),
};

export default strings;
