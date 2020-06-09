/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
/**
 * External dependencies
 */
import AccountStatus from 'account-status';

ReactDOM.render(
	<AccountStatus { ...wcpayAdminSettings } />,
	document.getElementById( 'wcpay-account-status-container' )
);
