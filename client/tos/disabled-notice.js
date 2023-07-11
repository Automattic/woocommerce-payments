/** @format **/

/**
 * External dependencies
 */
// import '@wordpress/notices';
import { dispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { enableGatewayAfterTosDecline } from './request.js';

const showTosNotice = ( settingsUrl ) => {
	const { createInfoNotice } = dispatch( 'core/notices' );

	const enableGateway = async () => {
		try {
			await enableGatewayAfterTosDecline();
		} finally {
			// If the gateway was not enabled through AJAX, the
			// settings page is still the best way to do it.
			window.location = settingsUrl;
		}
	};

	createInfoNotice(
		sprintf(
			/* translators: %s: WooPayments */
			__( 'Disabled %s', 'woocommerce-payments' ),
			'WooPayments'
		),
		{
			actions: [
				{
					label: __( 'Undo', 'woocommerce-payments' ),
					onClick: enableGateway,
				},
			],
		}
	);
};

export default showTosNotice;
