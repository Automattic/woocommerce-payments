/**
 * External dependencies
 */
import React, { useEffect } from 'react';

/**
 * Internal dependencies
 */
import { OnboardingContextProvider } from './context';
import { Stepper } from 'components/stepper';
import { OnboardingSteps } from './types';
import { OnboardingForm } from './form';
import ModeChoice from './steps/mode-choice';
import PersonalDetails from './steps/personal-details';
import BusinessDetails from './steps/business-details';
import StoreDetails from './steps/store-details';
import LoadingStep from './steps/loading';
import strings from './strings';
import './style.scss';

interface Props {
	name: OnboardingSteps;
}
const Step: React.FC< Props > = ( { name, children } ) => {
	return (
		<>
			<h1 className="step__heading">{ strings.steps[ name ].heading }</h1>
			<h2 className="step__subheading">
				{ strings.steps[ name ].subheading }
			</h2>
			<div className="step__content">{ children }</div>
		</>
	);
};

const OnboardingStepper = () => {
	return (
		<Stepper>
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

const OnboardingPrototype: React.FC = () => {
	useEffect( () => {
		document.body.classList.remove( 'woocommerce-admin-is-loading' );
		document.body.classList.add( 'woocommerce-admin-full-screen' );
		document.body.classList.add( 'is-wp-toolbar-disabled' );
		document.body.classList.add( 'wcpay-onboarding-prototype__body' );

		return () => {
			document.body.classList.remove( 'woocommerce-admin-full-screen' );
			document.body.classList.remove( 'is-wp-toolbar-disabled' );
			document.body.classList.remove(
				'wcpay-onboarding-prototype__body'
			);
		};
	}, [] );

	return (
		<div className="wcpay-onboarding-prototype">
			<OnboardingContextProvider>
				<OnboardingStepper />
			</OnboardingContextProvider>
		</div>
	);
};

export default OnboardingPrototype;
