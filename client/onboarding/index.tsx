/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import { OnboardingContextProvider } from './context';
import { Stepper } from 'components/stepper';
import { OnboardingForm } from './form';
import Step from './step';
import ModeChoice from './steps/mode-choice';
import PersonalDetails from './steps/personal-details';
import BusinessDetails from './steps/business-details';
import StoreDetails from './steps/store-details';
import LoadingStep from './steps/loading';
import { trackStarted } from './tracking';
import './style.scss';

const OnboardingStepper = () => {
	const handleExit = () => {
		if (
			window.history.length > 1 &&
			document.referrer.includes( wcSettings.adminUrl )
		)
			return window.history.back();
		window.location.href = wcSettings.adminUrl;
	};

	const handleStepChange = () => window.scroll( 0, 0 );

	return (
		<Stepper onStepChange={ handleStepChange } onExit={ handleExit }>
			<Step name="mode">
				<ModeChoice />
			</Step>
			<Step name="personal">
				<OnboardingForm>
					<PersonalDetails />
				</OnboardingForm>
			</Step>
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

const OnboardingPage: React.FC = () => {
	const isLocalhost = location.hostname === 'localhost';
	const businessUrl = isLocalhost
		? 'https://wcpay.test'
		: wcSettings?.homeUrl ?? '';

	useEffect( () => {
		trackStarted();

		// Remove loading class and add those requires for full screen.
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
		<div className="wcpay-onboarding-prototype">
			<OnboardingContextProvider initialData={ { url: businessUrl } }>
				<OnboardingStepper />
			</OnboardingContextProvider>
		</div>
	);
};

export default OnboardingPage;
