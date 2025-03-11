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
import appearance from 'wcpay/embedded-components/appearance';
import BannerNotice from 'wcpay/components/banner-notice';
import StripeSpinner from 'wcpay/components/stripe-spinner';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { finalizeOnboarding } from 'wcpay/onboarding/utils';
import { getConnectUrl, getOverviewUrl } from 'wcpay/utils';
import useKycAccountSession from 'wcpay/embedded-components/kyc-account-session';
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
	const urlParams = new URLSearchParams( window.location.search );
	const urlSource =
		urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';
	const [ loading, setLoading ] = useState( true );
	const [ finalizingAccount, setFinalizingAccount ] = useState( false );
	const [ loadErrorMessage, setLoadErrorMessage ] = useState( '' );
	const [ loadErrorType, setLoadErrorType ] = useState( '' );
	const stripeConnectInstance = useKycAccountSession( {
		data,
		continueKyc,
		setLoadErrorMessage,
		appearance,
	} );

	const handleStepChange = ( step: string ) => {
		trackEmbeddedStepChange( step );
	};

	const handleOnExit = async () => {
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
			{ loadErrorMessage &&
				( loadErrorType === 'api_connection_error' ? (
					<BannerNotice
						status="warning"
						actions={ [
							{
								label: 'Learn more',
								variant: 'primary',
								url: 'https://woocommerce.com/payments/',
								urlTarget: '_blank',
							},
							{
								label: 'Cancel',
								variant: 'link',
								url: getConnectUrl(
									{
										'wcpay-connection-error': '1',
										source: urlSource,
									},
									'WCPAY_ONBOARDING_WIZARD'
								),
							},
						] }
					>
						{ __(
							'Payment activation through our financial partner requires HTTPS and cannot be completed.',
							'woocommerce-payments'
						) }
					</BannerNotice>
				) : (
					<BannerNotice status="error">
						{ loadErrorMessage }
					</BannerNotice>
				) ) }
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
						onLoadError={ ( loadError ) => {
							const error = loadError.error;
							setLoadErrorMessage(
								error.message || 'Failed to load'
							);
							setLoadErrorType( error.type );
						} }
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
