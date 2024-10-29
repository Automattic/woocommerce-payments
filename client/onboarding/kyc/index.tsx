/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import WooLogo from 'assets/images/woo-logo.svg';
import Page from 'components/page';
import { OnboardingContextProvider } from 'onboarding/context';
import EmbeddedKyc from 'onboarding/steps/embedded-kyc';
import strings from 'onboarding/strings';
import { getConnectUrl } from 'utils';
import { trackKycExit } from 'wcpay/onboarding/tracking';

const OnboardingKycPage: React.FC = () => {
	const urlParams = new URLSearchParams( window.location.search );
	const collectPayoutRequirements = !! urlParams.get(
		'collect_payout_requirements'
	);

	const handleExit = () => {
		trackKycExit();

		// Let the connect logic determine where the merchant should end up.
		window.location.href = getConnectUrl(
			{
				source:
					urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) ||
					'unknown',
			},
			'WCPAY_ONBOARDING_KYC'
		);
	};

	useEffect( () => {
		// Remove loading class and add those required for full screen.
		document.body.classList.remove( 'woocommerce-admin-is-loading' );
		document.body.classList.add( 'woocommerce-admin-full-screen' );
		document.body.classList.add( 'is-wp-toolbar-disabled' );
		document.body.classList.add( 'wcpay-onboarding__body' );

		// Remove full screen classes on unmount.
		return () => {
			document.body.classList.remove( 'woocommerce-admin-full-screen' );
			document.body.classList.remove( 'is-wp-toolbar-disabled' );
			document.body.classList.remove( 'wcpay-onboarding__body' );
		};
	}, [] );
	return (
		<Page className="wcpay-onboarding-prototype">
			<OnboardingContextProvider>
				<div className="stepper__nav">
					<button
						type="button"
						className={ `stepper__nav-button hide` }
					>
						{ strings.back }
					</button>
					<img
						src={ WooLogo }
						alt="Woo"
						className="stepper__nav-logo"
					/>
					<button
						type="button"
						className="stepper__nav-button"
						onClick={ handleExit }
					>
						{ strings.cancel }
					</button>
				</div>
				<div className="stepper__wrapper">
					<div className="stepper__content">
						<EmbeddedKyc
							continueKyc={ true }
							collectPayoutRequirements={
								collectPayoutRequirements
							}
						/>
					</div>
				</div>
			</OnboardingContextProvider>
		</Page>
	);
};
export default OnboardingKycPage;
