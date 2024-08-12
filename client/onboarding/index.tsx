/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import Page from 'components/page';
import { OnboardingContextProvider } from './context';
import { Stepper } from 'components/stepper';
import { getMccFromIndustry } from 'onboarding/utils';
import { OnboardingForm } from './form';
import Step from './step';
import BusinessDetails from './steps/business-details';
import StoreDetails from './steps/store-details';
import LoadingStep from './steps/loading';
import { trackStarted } from './tracking';
import { getAdminUrl } from 'wcpay/utils';
import './style.scss';

const OnboardingStepper = () => {
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

	const handleStepChange = () => window.scroll( 0, 0 );

	return (
		<Stepper onStepChange={ handleStepChange } onExit={ handleExit }>
			<Step name="business">
				<OnboardingForm>
					<BusinessDetails />
				</OnboardingForm>
			</Step>
			<Step name="store">
				<OnboardingForm>
					<StoreDetails />
				</OnboardingForm>
			</Step>
			<LoadingStep name="loading" />
		</Stepper>
	);
};

const getComingSoonShareKey = () => {
	const {
		woocommerce_share_key: shareKey,
		woocommerce_coming_soon: comingSoon,
		woocommerce_private_link: privateLink,
	} = wcSettings?.admin?.siteVisibilitySettings || {};

	if ( comingSoon !== 'yes' || privateLink === 'no' ) {
		return '';
	}

	return shareKey ? '?woo-share=' + shareKey : '';
};

const initialData = {
	business_name: wcSettings?.siteTitle,
	mcc: getMccFromIndustry(),
	url:
		location.hostname === 'localhost'
			? 'https://wcpay.test'
			: wcSettings?.homeUrl + getComingSoonShareKey(),
	country: wcpaySettings?.connect?.country,
};

const OnboardingPage: React.FC = () => {
	useEffect( () => {
		const urlParams = new URLSearchParams( window.location.search );
		const source =
			urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';
		trackStarted( source );

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
			<OnboardingContextProvider initialData={ initialData }>
				<OnboardingStepper />
			</OnboardingContextProvider>
		</Page>
	);
};

export default OnboardingPage;
