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
import { createKycAccountSession, isPoEligible } from 'wcpay/onboarding/utils';
import { trackRedirected } from 'wcpay/onboarding/tracking';

interface UseKycAccountSessionProps {
	/**
	 * The data object from the onboarding context.
	 */
	data: Record< string, any >;
	/**
	 * Set it to true whenever KYC needs to be resumed.
	 */
	continueKyc: boolean;
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
 * @return StripeConnectInstance|null
 */
const useKycAccountSession = ( {
	data,
	continueKyc,
	setLoadErrorMessage,
	appearance,
}: UseKycAccountSessionProps ): StripeConnectInstance | null => {
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );

	useEffect( () => {
		const initializeStripe = async () => {
			try {
				// Fetch account session
				const isEligible =
					! continueKyc && ( await isPoEligible( data ) );
				const accountSession = await createKycAccountSession(
					data,
					isEligible
				);

				if ( ! accountSession?.clientSecret ) {
					setLoadErrorMessage(
						__(
							"Failed to create account session. Please check that you're using the latest version of WooPayments.",
							'woocommerce-payments'
						)
					);
					return;
				}

				trackRedirected( isEligible, true );

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
	}, [ data, continueKyc, appearance, setLoadErrorMessage ] );

	return stripeConnectInstance;
};

export default useKycAccountSession;
