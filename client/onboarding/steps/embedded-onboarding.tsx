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
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { NAMESPACE } from 'data/constants';
import LoadBar from 'wcpay/components/load-bar';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import {
	AccountSession,
	PoEligibleData,
	PoEligibleResult,
} from 'wcpay/onboarding/types';
import { fromDotNotation } from 'wcpay/onboarding/utils';
import { getAdminUrl } from 'wcpay/utils';
import { LoadError } from '@stripe/connect-js/types/config';
import BannerNotice from 'wcpay/components/banner-notice';
import { __ } from '@wordpress/i18n';

type AccountSessionData = AccountSession;

const EmbeddedOnboarding: React.FC = () => {
	const { data } = useOnboardingContext();
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
			const eligibleResult = await apiFetch< PoEligibleResult >( {
				path: '/wc/v3/payments/onboarding/router/po_eligible',
				method: 'POST',
				data: eligibilityDetails,
			} );

			return 'eligible' === eligibleResult.result;
		};

		const fetchKeys = async () => {
			let isEligible;
			try {
				isEligible = await isEligibleForPo();
			} catch ( error ) {
				// fall back to full KYC scenario.
				isEligible = false;
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
	}, [ data ] );

	// Initialize the Stripe Connect instance only once when publishableKey and clientSecret are ready
	useEffect( () => {
		if ( publishableKey && clientSecret && ! stripeConnectInstance ) {
			const stripeInstance = loadConnectAndInitialize( {
				publishableKey: publishableKey,
				fetchClientSecret: clientSecret, // Pass the function returning the Promise<string>
				appearance: {
					// See all possible variables below
					overlays: 'drawer',
					variables: {
						colorPrimary: '#3858E9',
						colorBackground: '#FFFFFF',
						buttonPrimaryColorBackground: '#3858E9',
						buttonPrimaryColorBorder: '#3858E9',
						buttonPrimaryColorText: '#FFFFFF',
						buttonSecondaryColorBackground: '#FFFFFF',
						buttonSecondaryColorBorder: '#3858E9',
						buttonSecondaryColorText: '#3858E9',
						colorText: '#101517',
						colorSecondaryText: '#50575E',
						actionPrimaryColorText: '#3858E9',
						actionSecondaryColorText: '#101517',
						colorBorder: '#DDDDDD',
						formHighlightColorBorder: '#3858E9',
						formAccentColor: '#3858E9',
						colorDanger: '#CC1818',
						offsetBackgroundColor: '#F0F0F0',
						formBackgroundColor: '#FFFFFF',
						badgeNeutralColorText: '#2C3338',
						badgeNeutralColorBackground: '#F6F7F7',
						badgeNeutralColorBorder: '#F6F7F7',
						badgeSuccessColorText: '#005C12',
						badgeSuccessColorBackground: '#EDFAEF',
						badgeSuccessColorBorder: '#EDFAEF',
						badgeWarningColorText: '#614200',
						badgeWarningColorBackground: '#FCF9E8',
						badgeWarningColorBorder: '#FCF9E8',
						badgeDangerColorText: '#8A2424',
						badgeDangerColorBackground: '#FCF0F1',
						badgeDangerColorBorder: '#FCF0F1',
						borderRadius: '2px',
						buttonBorderRadius: '2px',
						formBorderRadius: '2px',
						badgeBorderRadius: '2px',
						overlayBorderRadius: '8px',
						spacingUnit: '10px',
						fontFamily: 'SF Pro Text, Helvetica, Arial, sans-serif',
						fontSizeBase: '16px',
						headingXlFontSize: '32px',
						headingXlFontWeight: '400',
						headingLgFontSize: '24px',
						headingLgFontWeight: '400',
						headingMdFontSize: '20px',
						headingMdFontWeight: '400',
						headingSmFontSize: '16px',
						headingSmFontWeight: '600',
						headingXsFontSize: '12px',
						headingXsFontWeight: '600',
						bodyMdFontWeight: '400',
						bodyMdFontSize: '16px',
						bodySmFontSize: '14px',
						bodySmFontWeight: '400',
					},
				},
			} );

			setStripeConnectInstance( stripeInstance );
		}
	}, [ publishableKey, clientSecret, stripeConnectInstance ] );

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
