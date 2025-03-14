/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import {
	loadConnectAndInitialize,
	LoadError,
	LoaderStart,
	StripeConnectInstance,
} from '@stripe/connect-js';
import {
	ConnectAccountOnboarding,
	ConnectComponentsProvider,
	ConnectNotificationBanner,
} from '@stripe/react-connect-js';

/**
 * Internal dependencies
 */
import { createKycAccountSession, createAccountSession } from './hooks';
import appearance from './appearance';
import { OnboardingFields } from 'wcpay/onboarding/types';
import StripeSpinner from 'wcpay/components/stripe-spinner';
import BannerNotice from 'wcpay/components/banner-notice';
import { AccountSession } from 'wcpay/embedded-components/types';
import { trackRedirected } from 'wcpay/onboarding/tracking';

interface EmbeddedComponentProps {
	onLoaderStart?: ( { elementTagName }: LoaderStart ) => void;
	onLoadError?: ( { error, elementTagName }: LoadError ) => void;
}

interface EmbeddedAccountOnboardingProps extends EmbeddedComponentProps {
	onboardingData: OnboardingFields;
	onExit: () => void;
	onStepChange?: ( step: string ) => void;
	collectPayoutRequirements?: boolean;
	isPoEligible?: boolean;
}

interface EmbeddedAccountNotificationBannerProps
	extends EmbeddedComponentProps {
	onNotificationsChange: ( {
		total,
		actionRequired,
	}: {
		total: number;
		actionRequired: number;
	} ) => void;
}

/**
 * Hook to initialize Stripe Connect.
 *
 * @param isOnboarding - Whether this is an onboarding flow.
 * @param onboardingData - Data required for onboarding.
 * @param isPoEligible - Whether the user is eligible for progressive onboarding.
 *
 * @return Returns stripeConnectInstance, error, and loading state.
 */
const useInitializeStripe = (
	isOnboarding: boolean,
	onboardingData: OnboardingFields | null,
	isPoEligible: boolean
) => {
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ loading, setLoading ] = useState< boolean >( true );

	useEffect( () => {
		const initializeStripe = async () => {
			try {
				let session: AccountSession;

				if ( isOnboarding && onboardingData ) {
					session = await createKycAccountSession(
						onboardingData,
						isPoEligible
					);

					// Track the embedded component redirection event.
					trackRedirected( isPoEligible, true );
				} else {
					session = await createAccountSession();
				}

				const { clientSecret, publishableKey } = session;

				if ( ! publishableKey ) {
					throw new Error(
						'Missing publishable key in session response'
					);
				}

				const instance = loadConnectAndInitialize( {
					publishableKey,
					fetchClientSecret: async () => clientSecret,
					appearance: {
						overlays: 'drawer',
						...appearance,
					},
					locale: session.locale.replace( '_', '-' ),
				} );

				setStripeConnectInstance( instance );
			} catch ( err ) {
				setError(
					err instanceof Error ? err.message : 'Unknown error'
				);
			} finally {
				setLoading( false );
			}
		};

		initializeStripe();
	}, [ isOnboarding, onboardingData, isPoEligible ] );

	return { stripeConnectInstance, error, loading };
};

/**
 * Embedded Stripe Account Onboarding Component.
 *
 * @param onboardingData - Data required for onboarding.
 * @param onExit - Callback function when the onboarding flow is exited.
 * @param onLoaderStart - Callback function when the onboarding loader starts.
 * @param onLoadError - Callback function when the onboarding load error occurs.
 * @param [onStepChange] - Callback function when the onboarding step changes.
 * @param [collectPayoutRequirements=false] - Whether to collect payout requirements.
 * @param [isPoEligible=false] - Whether the user is eligible for progressive onboarding.
 *
 * @return Rendered Account Onboarding component.
 */
export const EmbeddedAccountOnboarding: React.FC< EmbeddedAccountOnboardingProps > = ( {
	onboardingData,
	onExit,
	onLoaderStart,
	onLoadError,
	onStepChange,
	isPoEligible = false,
	collectPayoutRequirements = false,
} ) => {
	const { stripeConnectInstance, error } = useInitializeStripe(
		true,
		onboardingData,
		isPoEligible
	);

	return (
		<>
			{ error && <BannerNotice status="error">{ error }</BannerNotice> }
			{ ! error && stripeConnectInstance && (
				<ConnectComponentsProvider
					connectInstance={ stripeConnectInstance }
				>
					<ConnectAccountOnboarding
						onLoaderStart={ onLoaderStart }
						onLoadError={ onLoadError }
						onExit={ onExit }
						onStepChange={ ( stepChange ) =>
							onStepChange?.( stepChange.step )
						}
						collectionOptions={ {
							fields: collectPayoutRequirements
								? 'eventually_due'
								: 'currently_due',
							futureRequirements: 'omit',
						} }
					/>
				</ConnectComponentsProvider>
			) }
		</>
	);
};

/**
 * Embedded Stripe Notification Banner Component.
 *
 * @param onLoaderStart - Callback when Stripe component starts rendering.
 * @param onLoadError - Callback when Stripe component load error occurs.
 * @param onNotificationsChange - Callback triggered when notifications change.
 *
 * @return Rendered Notification Banner component.
 */
export const EmbeddedConnectNotificationBanner: React.FC< EmbeddedAccountNotificationBannerProps > = ( {
	onLoaderStart,
	onLoadError,
	onNotificationsChange,
} ) => {
	const { stripeConnectInstance, error, loading } = useInitializeStripe(
		false,
		null,
		false
	);

	return (
		<>
			{ ( loading || ! stripeConnectInstance ) && <StripeSpinner /> }
			{ error && <BannerNotice status="error">{ error }</BannerNotice> }
			{ ! error && stripeConnectInstance && (
				<ConnectComponentsProvider
					connectInstance={ stripeConnectInstance }
				>
					<ConnectNotificationBanner
						onLoaderStart={ onLoaderStart }
						onLoadError={ onLoadError }
						onNotificationsChange={ onNotificationsChange }
						collectionOptions={ {
							fields: 'eventually_due',
							futureRequirements: 'omit',
						} }
					/>
				</ConnectComponentsProvider>
			) }
		</>
	);
};
