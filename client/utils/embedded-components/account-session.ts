/**
 * External dependencies
 */
import { useState, useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import {
	loadConnectAndInitialize,
	StripeConnectInstance,
} from '@stripe/connect-js/pure';

/**
 * Internal dependencies
 */
import { createAccountSession } from 'wcpay/utils/embedded-components/utils';

interface UseAccountSessionProps {
	/**
	 * Function to set the load error message.
	 */
	setLoadErrorMessage: ( message: string ) => void;
	/**
	 * The appearance object.
	 */
	appearance: {
		variables: Record< string, any >;
	};
}

/**
 * This is a custom hook that retrieve the account session data.
 * It returns the StripeConnectInstance object that is used to render the embedded components.
 *
 * If the account session data is not available, it returns null.
 *
 * @param function setLoadErrorMessage - Function to set the load error message.
 * @param object appearance - The appearance object.
 *
 * @return StripeConnectInstance|null
 */
const useAccountSession = ( {
	setLoadErrorMessage,
	appearance,
}: UseAccountSessionProps ): StripeConnectInstance | null => {
	const [ locale, setLocale ] = useState( '' );
	const [ publishableKey, setPublishableKey ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState<
		( () => Promise< string > ) | null
	>( null );
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );

	const fetchAccountSession = useCallback( async () => {
		try {
			const accountSession = await createAccountSession();
			if ( accountSession && accountSession.clientSecret ) {
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
	}, [ setLoadErrorMessage ] );

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
	}, [ fetchAccountSession, fetchClientSecret, setLoadErrorMessage ] );

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

	return stripeConnectInstance;
};

export default useAccountSession;
