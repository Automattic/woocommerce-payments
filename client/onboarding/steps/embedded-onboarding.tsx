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
import { useAccountSession } from 'wcpay/onboarding/hooks';

const EmbeddedOnboarding: React.FC = () => {
	// TODO: Pass the query params.
	const { accountSession, isLoading } = useAccountSession( {} );

	// We use `useState` to ensure the Connect instance is only initialized once
	const [ stripeConnectInstance ] = useState( () => {
		const fetchClientSecret = async () => {
			// Fetch the AccountSession client secret
			const clientSecret = accountSession.clientSecret;
			return clientSecret;
		};

		return loadConnectAndInitialize( {
			// This is your test publishable API key. TODO: get the config
			publishableKey: 'pk_test_qblFNYngBkEdjEZ16jxxoWSM',
			fetchClientSecret: fetchClientSecret,
		} );
	} );

	const handleOnboardingComplete = () => {
		console.log( 'onboarding complete!');
	};

	return (
		! isLoading && (
			<>
				<ConnectComponentsProvider
					connectInstance={ stripeConnectInstance }
				>
					<ConnectAccountOnboarding
						onExit={ () => handleOnboardingComplete }
					/>
				</ConnectComponentsProvider>
			</>
		)
	);
};

export default EmbeddedOnboarding;
