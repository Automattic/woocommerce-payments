/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import AccountStatus from 'account-status';

addFilter( 'woocommerce_admin_notices_to_show', 'plugin-domain', notices => {
	return [
		...notices,
		[ 'wcpay-test-mode-notice', null, null ],
		[ null, [ 'wcpay-settings-notice' ], null ],
	];
} );

ReactDOM.render(
	<AccountStatus { ...wcpayAdminSettings } />,
	document.getElementById( 'wcpay-account-status-container' )
);
