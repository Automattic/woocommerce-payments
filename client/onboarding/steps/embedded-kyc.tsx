/**
 * External dependencies
 */
import React, { useState } from 'react';
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
import StripeSpinner from 'wcpay/components/stripe-spinner';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { finalizeOnboarding } from 'wcpay/onboarding/utils';
import { getConnectUrl, getOverviewUrl } from 'wcpay/utils';
import useAccountSession from 'wcpay/utils/embedded/account-session';
import { trackEmbeddedStepChange } from 'wcpay/onboarding/tracking';

interface Props {
	continueKyc?: boolean;
	collectPayoutRequirements?: boolean;
}

// TODO: extract this logic and move it to a generic component to be used for all embedded components, not just onboarding.
const EmbeddedKyc: React.FC< Props > = ( {
	continueKyc = false,
	collectPayoutRequirements = false,
} ) => {
	const { data } = useOnboardingContext();
	const [ loading, setLoading ] = useState( true );
	const [ finalizingAccount, setFinalizingAccount ] = useState( false );
	const [ loadErrorMessage, setLoadErrorMessage ] = useState( '' );

	/**
	 * This is a custom hook that retrieve the account session data.
	 * It returns the StripeConnectInstance object that is used to render the embedded components.
	 *
	 * If the account session data is not available, it returns null.
	 *
	 * @param {Object} isOnboarding - Whether the user is onboarding, always true during onboarding.
	 * @param {Object} data - The data object from the onboarding context.
	 * @param {Object} continueKyc - Whether to continue the KYC process.
	 * @param {Function} setLoadErrorMessage - Function to set the load error message.
	 * @param {Object} appearance - The appearance object.
	 * @return {Object} StripeConnectInstance | null
	 */
	const stripeConnectInstance = useAccountSession( {
		isOnboarding: true,
		data,
		continueKyc,
		setLoadErrorMessage,
		appearance,
	} );

	const handleStepChange = ( step: string ) => {
		trackEmbeddedStepChange( step );
	};

	const handleOnExit = async () => {
		const urlParams = new URLSearchParams( window.location.search );
		const urlSource =
			urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';

		setFinalizingAccount( true );

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
			{ loading && (
				<div className="embedded-kyc-loader-wrapper padded">
					<StripeSpinner />
				</div>
			) }
			{ loadErrorMessage && (
				<BannerNotice status="error">{ loadErrorMessage }</BannerNotice>
			) }
			{ finalizingAccount && (
				<div className="embedded-kyc-loader-wrapper">
					<StripeSpinner />
				</div>
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
						onStepChange={ ( stepChange ) =>
							handleStepChange( stepChange.step )
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

export default EmbeddedKyc;
