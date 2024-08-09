/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { isInDevMode } from 'utils';

export default {
	title: __( 'Account Tools', 'woocommerce-payments' ),
	description: isInDevMode()
		? __(
				'Your account is in sandbox mode. If you are experiencing problems completing account setup, or wish to test with a different email/country associated with your account, you can reset your account and start from the beginning.',
				'woocommerce-payments'
		  )
		: __(
				'Payments and deposits are disabled until account setup is completed. If you are experiencing problems completing account setup, or need to change the email/country associated with your account, you can reset your account and start from the beginning.',
				'woocommerce-payments'
		  ),
	reset: __( 'Reset account', 'woocommerce-payments' ),
};
