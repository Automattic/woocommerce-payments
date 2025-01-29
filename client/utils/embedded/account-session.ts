/**
 * External dependencies
 */
import { useState, useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import {
	loadConnectAndInitialize,
	StripeConnectInstance,
} from '@stripe/connect-js/pure';
import { createAccountSession, isPoEligible } from 'wcpay/onboarding/utils';
import { trackRedirected } from 'wcpay/onboarding/tracking';

interface UseAccountSessionProps {
	isOnboarding: boolean;
	data: Record< string, any >;
	continueKyc: boolean;
	appearance: {
		variables: Record< string, any >;
	};
}

interface UseAccountSessionReturn {
	loadErrorMessage: string;
	stripeConnectInstance: StripeConnectInstance | null;
}

const useAccountSession = ( {
	isOnboarding,
	data,
	continueKyc,
	appearance,
}: UseAccountSessionProps ): UseAccountSessionReturn => {
	const [ locale, setLocale ] = useState( '' );
	const [ publishableKey, setPublishableKey ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState<
		( () => Promise< string > ) | null
	>( null );
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );
	const [ loadErrorMessage, setLoadErrorMessage ] = useState( '' );

	const fetchAccountSession = useCallback( async () => {
		try {
			const isEligible =
				isOnboarding && ! continueKyc && ( await isPoEligible( data ) );
			const accountSession = await createAccountSession(
				data,
				isEligible
			);
			if ( accountSession && accountSession.clientSecret ) {
				if ( isOnboarding ) {
					trackRedirected( isEligible, true );
				}

				return accountSession; // Return the full account session object
			}

			setLoadErrorMessage(
				__(
					"Failed to create account session. Please check that you're using the latest version of WooPayments.",
					'woocommerce-payments'
				)
			);
		} catch ( error ) {
			setLoadErrorMessage(
				__(
					'Failed to retrieve account session. Please try again later.',
					'woocommerce-payments'
				)
			);
		}

		// Return null if an error occurred.
		return null;
	}, [ isOnboarding, continueKyc, data ] );

	// Function to fetch clientSecret for use in Stripe auto-refresh or initialization
	const fetchClientSecret = useCallback( async () => {
		const accountSession = await fetchAccountSession();
		if ( accountSession ) {
			return accountSession.clientSecret; // Only return the clientSecret
		}
		throw new Error( 'Error fetching the client secret' );
	}, [ fetchAccountSession ] );

	// Effect to fetch the publishable key and clientSecret on initial render
	useEffect( () => {
		const fetchKeys = async () => {
			try {
				const accountSession = await fetchAccountSession();
				if ( accountSession ) {
					setLocale( accountSession.locale );
					setPublishableKey( accountSession.publishableKey );
					setClientSecret( () => fetchClientSecret );
				}
			} catch ( error ) {
				setLoadErrorMessage(
					__(
						'Failed to create account session. Please check that you are using the latest version of WooPayments.',
						'woocommerce-payments'
					)
				);
			}
		};

		fetchKeys();
	}, [ data, continueKyc, fetchAccountSession, fetchClientSecret ] );

	useEffect( () => {
		if ( publishableKey && clientSecret && ! stripeConnectInstance ) {
			const stripeInstance = loadConnectAndInitialize( {
				publishableKey,
				fetchClientSecret,
				appearance: {
					overlays: 'drawer',
					variables: appearance.variables,
				},
				locale: locale.replace( '_', '-' ),
			} );

			setStripeConnectInstance( stripeInstance );
		}
	}, [
		publishableKey,
		clientSecret,
		stripeConnectInstance,
		fetchClientSecret,
		locale,
		appearance,
	] );

	return {
		loadErrorMessage,
		stripeConnectInstance,
	};
};

export default useAccountSession;
