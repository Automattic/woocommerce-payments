/** @format **/

/**
 * External dependencies
 */
// import '@wordpress/notices';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

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

	createInfoNotice( __( 'Disabled WooPayments', 'woocommerce-payments' ), {
		actions: [
			{
				label: __( 'Undo', 'woocommerce-payments' ),
				onClick: enableGateway,
			},
		],
	} );
};

export default showTosNotice;
