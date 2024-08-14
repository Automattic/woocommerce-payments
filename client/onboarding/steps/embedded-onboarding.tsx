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
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { NAMESPACE } from 'data/constants';

const EmbeddedOnboarding: React.FC = () => {
	// TODO: Pass the query params.
	const { data, setData } = useOnboardingContext();
	const { accountSession, isLoading } = useAccountSession( {} );

	// We use `useState` to ensure the Connect instance is only initialized once
	const [ stripeConnectInstance ] = useState( () => {
		const fetchClientSecret = async () => {
			// Fetch the AccountSession client secret
			return accountSession.clientSecret;
		};

		return loadConnectAndInitialize( {
			// This is your test publishable API key. TODO: get the config
			publishableKey: 'pk_test_qblFNYngBkEdjEZ16jxxoWSM',
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

			console.log( response );
			debugger;
		} catch ( error ) {
			return undefined;
		}
	};

	return (
		<>
			! isLoading && (
			<ConnectComponentsProvider
				connectInstance={ stripeConnectInstance }
			>
				<ConnectAccountOnboarding
					onExit={ () => handleOnboardingComplete }
				/>
			</ConnectComponentsProvider>
			)
		</>
	);
};

export default EmbeddedOnboarding;
