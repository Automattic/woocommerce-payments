/**
 * External dependencies
 */
import { useState, useEffect } from 'react';
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
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );

	useEffect( () => {
		const initializeStripe = async () => {
			try {
				// Fetch account session
				const accountSession = await createAccountSession();

				if ( ! accountSession?.clientSecret ) {
					throw new Error(
						"Failed to create account session. Please check that you're using the latest version of WooPayments."
					);
				}

				// Initialize Stripe Connect
				const stripeInstance = loadConnectAndInitialize( {
					publishableKey: accountSession.publishableKey,
					fetchClientSecret: async () => accountSession.clientSecret,
					appearance: {
						overlays: 'drawer',
						variables: appearance.variables,
					},
					locale: accountSession.locale.replace( '_', '-' ),
				} );

				setStripeConnectInstance( stripeInstance );
			} catch ( error ) {
				setLoadErrorMessage(
					__(
						'Failed to retrieve account session. Please try again later.',
						'woocommerce-payments'
					)
				);
			}
		};

		initializeStripe();
	}, [ appearance, setLoadErrorMessage ] );

	return stripeConnectInstance;
};

export default useAccountSession;
