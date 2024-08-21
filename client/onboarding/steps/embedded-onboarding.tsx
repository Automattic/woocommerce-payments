/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import {
	loadConnectAndInitialize,
	StripeConnectInstance,
} from '@stripe/connect-js';
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
	// TODO GH-9251: Pass the query params.
	const { data, setData } = useOnboardingContext();
	const [ publishableKey, setPublishableKey ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState<
		( () => Promise< string > ) | null
	>( null );
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );

	useEffect( () => {
		const fetchKeys = async () => {
			const accountSession = await apiFetch< AccountSessionData >( {
				path: `${ NAMESPACE }/onboarding/session`,
				method: 'GET',
			} );
			setPublishableKey( accountSession.publishableKey );
			setClientSecret( () => () =>
				Promise.resolve( accountSession.clientSecret )
			); // Ensure clientSecret is wrapped as a function returning a Promise
		};

		fetchKeys();
	}, [] );

	// Initialize the Stripe Connect instance only once when publishableKey and clientSecret are ready
	useEffect( () => {
		if ( publishableKey && clientSecret && ! stripeConnectInstance ) {
			const stripeInstance = loadConnectAndInitialize( {
				publishableKey: publishableKey,
				fetchClientSecret: clientSecret, // Pass the function returning the Promise<string>
			} );

			setStripeConnectInstance( stripeInstance );
		}
	}, [ publishableKey, clientSecret, stripeConnectInstance ] );

	return (
		<>
			{ stripeConnectInstance && (
				<ConnectComponentsProvider
					connectInstance={ stripeConnectInstance }
				>
					<ConnectAccountOnboarding
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
								// TODO GH-9251 add error to the overview page
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
			) }
		</>
	);
};

export default EmbeddedOnboarding;
