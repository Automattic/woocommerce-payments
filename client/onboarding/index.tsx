/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import { OnboardingContextProvider, useOnboardingContext } from './context';
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
import { persistFlowState } from './utils';

const OnboardingStepper = () => {
	const { data } = useOnboardingContext();

	const handleExit = () => {
		if (
			window.history.length > 1 &&
			document.referrer.includes( wcSettings.adminUrl )
		)
			return window.history.back();
		window.location.href = wcSettings.adminUrl;
	};

	const handleStepChange = ( step: string ) => {
		window.scroll( 0, 0 );
		persistFlowState( step, data );
	};

	return (
		<Stepper
			initialStep={ wcpaySettings.onboardingFlowState?.current_step }
			onStepChange={ handleStepChange }
			onExit={ handleExit }
		>
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

const initialData = wcpaySettings.onboardingFlowState?.data ?? {
	business_name: wcSettings?.siteTitle,
	url:
		location.hostname === 'localhost'
			? 'https://wcpay.test'
			: wcSettings?.homeUrl,
	country: wcpaySettings?.connect?.country,
};

const OnboardingPage: React.FC = () => {
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
			<OnboardingContextProvider initialData={ initialData }>
				<OnboardingStepper />
			</OnboardingContextProvider>
		</div>
	);
};

export default OnboardingPage;
