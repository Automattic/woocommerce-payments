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
import { __ } from '@wordpress/i18n';

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
 *
 * @return Returns stripeConnectInstance, error, and loading state.
 */
const useInitializeStripe = (
	isOnboarding: boolean,
	onboardingData: OnboardingFields | null
) => {
	const [
		stripeConnectInstance,
		setStripeConnectInstance,
	] = useState< StripeConnectInstance | null >( null );
	const [ initializationError, setInitializationError ] = useState<
		string | null
	>( null );
	const [ loading, setLoading ] = useState< boolean >( true );

	useEffect( () => {
		const initializeStripe = async () => {
			try {
				let session: AccountSession;

				if ( isOnboarding && onboardingData ) {
					session = await createKycAccountSession( onboardingData );

					// Track the embedded component redirection event.
					trackRedirected( true );
				} else {
					session = await createAccountSession();
				}

				const { clientSecret, publishableKey } = session;

				if ( ! publishableKey ) {
					throw new Error(
						__(
							'Unable to start onboarding. If this problem persists, please contact support.',
							'woocommerce-payments'
						)
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
				setInitializationError(
					err instanceof Error
						? err.message
						: __(
								'Unable to start onboarding. If this problem persists, please contact support.',
								'woocommerce-payments'
						  )
				);
			} finally {
				setLoading( false );
			}
		};

		initializeStripe();
	}, [ isOnboarding, onboardingData ] );

	return { stripeConnectInstance, initializationError, loading };
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
 *
 * @return Rendered Account Onboarding component.
 */
export const EmbeddedAccountOnboarding: React.FC< EmbeddedAccountOnboardingProps > = ( {
	onboardingData,
	onExit,
	onLoaderStart,
	onLoadError,
	onStepChange,
	collectPayoutRequirements = false,
} ) => {
	const { stripeConnectInstance, initializationError } = useInitializeStripe(
		true,
		onboardingData
	);

	return (
		<>
			{ initializationError && (
				<BannerNotice status="error">
					{ initializationError }
				</BannerNotice>
			) }
			{ stripeConnectInstance && (
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
	const {
		stripeConnectInstance,
		initializationError,
		loading,
	} = useInitializeStripe( false, null );

	return (
		<>
			{ ( loading || ! stripeConnectInstance ) && <StripeSpinner /> }
			{ initializationError && (
				<BannerNotice status="error">
					{ initializationError }
				</BannerNotice>
			) }
			{ stripeConnectInstance && (
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
