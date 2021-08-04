/**
 * External dependencies
 */
import React, { useContext, useEffect } from 'react';
import { getHistory, getNewPath } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

const RedirectToConnectPage = () => {
	useEffect( () => {
		// temporarily overwriting this so that it appears on the connect page
		// it will be hidden on page refresh
		wcpaySettings.errorMessage = __(
			'Please connect your account before proceeding with onboarding.',
			'woocommerce-payments'
		);
		getHistory().push( getNewPath( {}, '/payments/connect', {} ) );
	}, [] );

	return null;
};

const EnsureConnectedAccount = ( { children } ) => {
	const { accountStatus } = useContext( WCPaySettingsContext );

	if ( Boolean( accountStatus.error ) ) {
		return <RedirectToConnectPage />;
	}

	return children;
};

export default EnsureConnectedAccount;
