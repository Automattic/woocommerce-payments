/**
 * External dependencies
 */
import React, { useCallback, useEffect, useState } from 'react';
import {
	loadConnectAndInitialize,
	StripeConnectInstance,
} from '@stripe/connect-js';
import {
	ConnectAccountOnboarding,
	ConnectComponentsProvider,
} from '@stripe/react-connect-js';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import appearance from '../kyc/appearance';
import BannerNotice from 'wcpay/components/banner-notice';
import LoadBar from 'wcpay/components/load-bar';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import {
	createAccountSession,
	finalizeOnboarding,
	isPoEligible,
} from 'wcpay/onboarding/utils';
import { getConnectUrl, getOverviewUrl } from 'wcpay/utils';

interface Props {
	continueKyc?: boolean;
}

// TODO: extract this logic and move it to a generic component to be used for all embedded components, not just onboarding.
const EmbeddedKyc: React.FC< Props > = ( { continueKyc = false } ) => {
	const { data } = useOnboardingContext();
	const [ locale, setLocale ] = useState( '' );
	const [ publishableKey, setPublishableKey ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState<
		( () => Promise< string > ) | null
	>( null );
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadErrorMessage, setLoadErrorMessage ] = useState( '' );

	const fetchAccountSession = useCallback( async () => {
		try {
			const isEligible = ! continueKyc && ( await isPoEligible( data ) );
			const accountSession = await createAccountSession(
				data,
				isEligible
			);
			if ( accountSession && accountSession.clientSecret ) {
				return accountSession; // Return the full account session object
			}

			setLoading( false );
			setLoadErrorMessage(
				__(
					"Failed to create account session. Please check that you're using the latest version of WooPayments.",
					'woocommerce-payments'
				)
			);
		} catch ( error ) {
			setLoading( false );
			setLoadErrorMessage(
				__(
					'Failed to retrieve account session. Please try again later.',
					'woocommerce-payments'
				)
			);
		}

		// Return null if an error occurred.
		return null;
	}, [ continueKyc, data ] );

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
				setLoading( false );
				setLoadErrorMessage(
					__(
						'Failed to create account session. Please check that you are using the latest version of WooPayments.',
						'woocommerce-payments'
					)
				);
			} finally {
				setLoading( false );
			}
		};

		fetchKeys();
	}, [ data, continueKyc, fetchAccountSession, fetchClientSecret ] );

	// Effect to initialize the Stripe Connect instance once publishableKey and clientSecret are ready.
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
	] );

	const handleOnExit = async () => {
		const urlParams = new URLSearchParams( window.location.search );
		const urlSource =
			urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';

		try {
			const response = await finalizeOnboarding( urlSource );
			if ( response.success ) {
				window.location.href = getOverviewUrl(
					{
						...response.params,
						'wcpay-connection-success': '1',
					},
					'WCPAY_ONBOARDING_WIZARD'
				);
			} else {
				window.location.href = getConnectUrl(
					{
						...response.params,
						'wcpay-connection-error': '1',
					},
					'WCPAY_ONBOARDING_WIZARD'
				);
			}
		} catch ( error ) {
			window.location.href = getConnectUrl(
				{
					'wcpay-connection-error': '1',
					source: urlSource,
				},
				'WCPAY_ONBOARDING_WIZARD'
			);
		}
	};

	return (
		<>
			{ loading && <LoadBar /> }
			{ loadErrorMessage && (
				<BannerNotice status="error">{ loadErrorMessage }</BannerNotice>
			) }
			{ stripeConnectInstance && (
				<ConnectComponentsProvider
					connectInstance={ stripeConnectInstance }
				>
					<ConnectAccountOnboarding
						onLoaderStart={ () => setLoading( false ) }
						onLoadError={ ( loadError ) =>
							setLoadErrorMessage(
								loadError.error.message || 'Unknown error'
							)
						}
						onExit={ handleOnExit }
					/>
				</ConnectComponentsProvider>
			) }
		</>
	);
};

export default EmbeddedKyc;
