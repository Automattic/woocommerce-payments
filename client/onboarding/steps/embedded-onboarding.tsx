/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import {
	loadConnectAndInitialize,
	StripeConnectInstance,
} from '@stripe/connect-js';
import { LoadError } from '@stripe/connect-js/types/config';
import {
	ConnectAccountOnboarding,
	ConnectComponentsProvider,
} from '@stripe/react-connect-js';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { NAMESPACE } from 'data/constants';
import appearance from '../embedded-appearance';
import BannerNotice from 'wcpay/components/banner-notice';
import LoadBar from 'wcpay/components/load-bar';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import {
	AccountSession,
	PoEligibleData,
	PoEligibleResult,
} from 'wcpay/onboarding/types';
import { fromDotNotation } from 'wcpay/onboarding/utils';
import { getOverviewUrl } from 'wcpay/utils';

type AccountSessionData = AccountSession;

interface FinalizeResponse {
	success: boolean;
	params: Record< string, string >;
}

interface Props {
	continueOnboarding?: boolean;
}

const EmbeddedOnboarding: React.FC< Props > = ( {
	continueOnboarding = false,
} ) => {
	const { data } = useOnboardingContext();
	const [ publishableKey, setPublishableKey ] = useState( '' );
	const [ locale, setLocale ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState<
		( () => Promise< string > ) | null
	>( null );
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadErrorMessage, setLoadErrorMessage ] = useState( '' );
	const onLoaderStart = () => {
		setLoading( false );
	};
	const onLoadError = ( loadError: LoadError ) => {
		setLoadErrorMessage( loadError.error.message || 'Unknown error' );
	};

	useEffect( () => {
		const isEligibleForPo = async () => {
			if (
				! data.country ||
				! data.business_type ||
				! data.mcc ||
				! data.annual_revenue ||
				! data.go_live_timeframe
			) {
				return false;
			}
			const eligibilityDetails: PoEligibleData = {
				business: {
					country: data.country,
					type: data.business_type,
					mcc: data.mcc,
				},
				store: {
					annual_revenue: data.annual_revenue,
					go_live_timeframe: data.go_live_timeframe,
				},
			};

			try {
				const eligibleResult = await apiFetch< PoEligibleResult >( {
					path: '/wc/v3/payments/onboarding/router/po_eligible',
					method: 'POST',
					data: eligibilityDetails,
				} );

				return 'eligible' === eligibleResult.result;
			} catch ( error ) {
				// Fall back to full KYC scenario.
				return false;
			}
		};

		const fetchKeys = async () => {
			// By default, we assume the merchant is not eligible for PO.
			let isEligible = false;

			// If we are resuming an onboarding session, we don't need to check for PO eligibility again.
			if ( ! continueOnboarding ) {
				isEligible = await isEligibleForPo();
			}

			const path = addQueryArgs( `${ NAMESPACE }/onboarding/session`, {
				self_assessment: fromDotNotation( data ),
				progressive: isEligible,
			} );
			const accountSession = await apiFetch< AccountSessionData >( {
				path: path,
				method: 'GET',
			} );
			if (
				accountSession.publishableKey &&
				accountSession.clientSecret
			) {
				setPublishableKey( accountSession.publishableKey );
				setLocale( accountSession.locale );
				setClientSecret( () => () =>
					Promise.resolve( accountSession.clientSecret )
				); // Ensure clientSecret is wrapped as a function returning a Promise
			} else {
				setLoading( false );
				setLoadErrorMessage(
					__(
						"Failed to create account session. Please check that you're using the latest version of WooCommerce Payments.",
						'woocommerce-payments'
					)
				);
			}
		};

		fetchKeys();
	}, [ data, continueOnboarding ] );

	// Initialize the Stripe Connect instance only once when publishableKey and clientSecret are ready
	useEffect( () => {
		if ( publishableKey && clientSecret && ! stripeConnectInstance ) {
			const stripeInstance = loadConnectAndInitialize( {
				publishableKey: publishableKey,
				fetchClientSecret: clientSecret, // Pass the function returning the Promise<string>
				appearance: {
					// See all possible variables below
					overlays: 'drawer',
					variables: appearance.variables,
				},
				locale: locale.replace( '_', '-' ),
			} );

			setStripeConnectInstance( stripeInstance );
		}
	}, [ publishableKey, clientSecret, stripeConnectInstance, locale ] );

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
						onLoaderStart={ onLoaderStart }
						onLoadError={ onLoadError }
						onExit={ async () => {
							const urlParams = new URLSearchParams(
								window.location.search
							);
							const urlSource =
								urlParams
									.get( 'source' )
									?.replace( /[^\w-]+/g, '' ) || 'unknown';
							try {
								const response = await apiFetch<
									FinalizeResponse
								>( {
									path: `${ NAMESPACE }/onboarding/finalize`,
									method: 'POST',
									data: {
										source: urlSource,
										from: 'WCPAY_ONBOARDING_WIZARD',
										clientSecret: clientSecret,
									},
								} );

								if ( response.success ) {
									window.location.href = getOverviewUrl(
										response.params,
										'WCPAY_ONBOARDING_WIZARD'
									);
								} else {
									// If a non-success response is received we should redirect to the overview page with an error flag:
									window.location.href = getOverviewUrl(
										{
											...response.params,
											onboardingError: true,
										},
										'WCPAY_ONBOARDING_WIZARD'
									);
								}
							} catch ( error ) {
								// If an error response is received we should redirect to the overview page with an error flag:
								// Note that this should never happen, since we always expect a response from the server.
								window.location.href = getOverviewUrl(
									{ onboardingError: true },
									'WCPAY_ONBOARDING_WIZARD'
								);
							}
						} }
					/>
				</ConnectComponentsProvider>
			) }
		</>
	);
};

export default EmbeddedOnboarding;
