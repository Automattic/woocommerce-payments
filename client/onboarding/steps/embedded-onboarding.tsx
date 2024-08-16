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

	const handleOnboardingComplete = async () => {
		try {
			const response = await fetch(
				`${ NAMESPACE }/onboarding/finalise`,
				{
					method: 'POST',
					body: JSON.stringify( {} ),
				}
			);
		} catch ( error ) {
			return undefined;
		}
	};

	return (
		<>
			<ConnectComponentsProvider
				connectInstance={ stripeConnectInstance }
			>
				<ConnectAccountOnboarding
					onExit={ () => handleOnboardingComplete }
				/>
			</ConnectComponentsProvider>
		</>
	);
};

export default EmbeddedOnboarding;
