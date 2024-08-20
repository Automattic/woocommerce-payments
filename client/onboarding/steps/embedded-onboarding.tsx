/**
 * External dependencies
 */
import React, { useState } from 'react';
import { loadConnectAndInitialize } from '@stripe/connect-js';
import {
	ConnectAccountOnboarding,
	ConnectComponentsProvider,
} from '@stripe/react-connect-js';

/**
 * Internal dependencies
 */
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { NAMESPACE } from 'data/constants';
import apiFetch from '@wordpress/api-fetch';
import { AccountSession } from 'wcpay/onboarding/types';
import { getAdminUrl } from 'wcpay/utils';

type AccountSessionData = AccountSession;

const EmbeddedOnboarding: React.FC = () => {
	// TODO: Pass the query params.
	const { data, setData } = useOnboardingContext();

	// We use `useState` to ensure the Connect instance is only initialized once
	const [ stripeConnectInstance ] = useState( () => {
		const fetchClientSecret = async () => {
			const accountSession = await apiFetch< AccountSessionData >( {
				path: `${ NAMESPACE }/onboarding/session`,
				method: 'GET',
			} );
			// Fetch the AccountSession client secret
			return accountSession.clientSecret;
		};

		return loadConnectAndInitialize( {
			// This is your test publishable API key. TODO: get the config
			publishableKey: 'blah',
			fetchClientSecret: fetchClientSecret,
		} );
	} );

	return (
		<>
			<ConnectComponentsProvider
				connectInstance={ stripeConnectInstance }
			>
				<ConnectAccountOnboarding
					// onExit={ () => handleOnboardingComplete }
					onExit={ async () => {
						const urlParams = new URLSearchParams(
							window.location.search
						);
						const urlSource =
							urlParams
								.get( 'source' )
								?.replace( /[^\w-]+/g, '' ) || 'unknown';
						try {
							await apiFetch( {
								path: `${ NAMESPACE }/onboarding/finalise`,
								method: 'POST',
								data: {
									source: urlSource,
								},
							} );
						} catch ( error ) {
							// TODO add error to the overview page
						}
						window.location.href = getAdminUrl( {
							page: 'wc-admin',
							path: '/payments/overview',
							source: urlSource,
							from: 'WCPAY_ONBOARDING_WIZARD',
						} );
					} }
				/>
			</ConnectComponentsProvider>
		</>
	);
};

export default EmbeddedOnboarding;
