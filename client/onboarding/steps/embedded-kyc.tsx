/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import { LoadError } from '@stripe/connect-js';

/**
 * Internal dependencies
 */
import StripeSpinner from 'wcpay/components/stripe-spinner';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { finalizeOnboarding } from 'wcpay/onboarding/utils';
import { getConnectUrl, getOverviewUrl, isInDevMode } from 'wcpay/utils';
import { trackEmbeddedStepChange } from 'wcpay/onboarding/tracking';
import { EmbeddedAccountOnboarding } from 'wcpay/embedded-components';
import BannerNotice from 'wcpay/components/banner-notice';
import interpolateComponents from '@automattic/interpolate-components';

interface Props {
	collectPayoutRequirements?: boolean;
}

const EmbeddedKyc: React.FC< Props > = ( {
	collectPayoutRequirements = false,
} ) => {
	const { data } = useOnboardingContext();
	const [ finalizingAccount, setFinalizingAccount ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState< LoadError | null >( null );

	const urlParams = new URLSearchParams( window.location.search );
	const urlSource =
		urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';

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

	const handleLoadError = ( err: LoadError ) => {
		setLoadError( err );
	};

	return (
		<>
			{ isInDevMode() && (
				<BannerNotice
					className="wcpay-banner-notice--embedded-kyc"
					status="warning"
					isDismissible={ false }
				>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							__(
								'{{strong}}Your store is in development mode.{{/strong}} %1$s can only create test accounts in development or staging environments. ' +
									'To set up a live account, switch to a production {{wpEnvLink}}WordPress environment{{/wpEnvLink}} or remove the WCPAY_DEV_MODE constant. ' +
									'{{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
							),
							'WooPayments'
						),
						components: {
							strong: <strong />,
							wpEnvLink: (
								// @ts-expect-error: children is provided when interpolating the component
								<ExternalLink
									href={
										'https://make.wordpress.org/core/2020/08/27/wordpress-environment-types/'
									}
								/>
							),
							learnMoreLink: (
								// @ts-expect-error: children is provided when interpolating the component
								<ExternalLink
									href={
										'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/'
									}
								/>
							),
						},
					} ) }
				</BannerNotice>
			) }
			{ loading && (
				<div className="embedded-kyc-loader-wrapper padded">
					<StripeSpinner />
				</div>
			) }
			{ finalizingAccount && (
				<div className="embedded-kyc-loader-wrapper">
					<StripeSpinner />
				</div>
			) }
			{ loadError &&
				( loadError.error.type === 'invalid_request_error' ? (
					<BannerNotice
						className={ 'wcpay-banner-notice--embedded-kyc' }
						status="warning"
						isDismissible={ false }
						actions={ [
							{
								label: __(
									'Learn more',
									'woocommerce-payments'
								),
								variant: 'primary',
								url:
									'https://woocommerce.com/document/woopayments/startup-guide/#requirements',
								urlTarget: '_blank',
							},
							{
								label: __( 'Cancel', 'woocommerce-payments' ),
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
					<BannerNotice
						className="wcpay-banner-notice--embedded-kyc"
						status="error"
						isDismissible={ false }
					>
						{ loadError.error.message }
					</BannerNotice>
				) ) }
			{
				<EmbeddedAccountOnboarding
					onExit={ handleOnExit }
					onStepChange={ handleStepChange }
					onLoaderStart={ () => setLoading( false ) }
					onLoadError={ handleLoadError }
					onboardingData={ data }
					collectPayoutRequirements={ collectPayoutRequirements }
				/>
			}
		</>
	);
};

export default EmbeddedKyc;
