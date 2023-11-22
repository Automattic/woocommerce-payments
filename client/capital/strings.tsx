/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

export default {
	notice: {
		content: __(
			'Viewing test loans. To view live loans, disable test mode in ',
			'woocommerce-payments'
		),
		action: sprintf(
			/* translators: %s: WooPayments */
			__( '%s settings.', 'woocommerce-payments' ),
			'WooPayments'
		),
	},
};
