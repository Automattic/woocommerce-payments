/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import Page from 'wcpay/components/page';
import { OnboardingContextProvider } from 'wcpay/onboarding/context';
import EmbeddedOnboarding from 'wcpay/onboarding/steps/embedded-onboarding';
import Logo from 'assets/images/woopayments.svg';
import { closeSmall, Icon } from '@wordpress/icons';
import strings from 'wcpay/onboarding/strings';
import { getAdminUrl } from 'wcpay/utils';

const ContinueOnboardingPage: React.FC = () => {
	const handleExit = () => {
		const urlParams = new URLSearchParams( window.location.search );

		window.location.href = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/connect',
			source:
				urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) ||
				'unknown',
			from: 'WCPAY_ONBOARDING_WIZARD',
		} );
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
						src={ Logo }
						alt="WooPayments"
						className="stepper__nav-logo"
					/>
					<button
						type="button"
						className="stepper__nav-button"
						onClick={ handleExit }
					>
						<Icon icon={ closeSmall } />
					</button>
				</div>
				<div className="stepper__wrapper">
					<div className="stepper__content">
						<EmbeddedOnboarding />
					</div>
				</div>
			</OnboardingContextProvider>
		</Page>
	);
};
export default ContinueOnboardingPage;
