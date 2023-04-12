/**
 * External dependencies
 */
import React from 'react';

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
import Loading from './steps/loading';
import strings from './strings';
import './style.scss';

interface Props {
	name: OnboardingSteps;
}
const Step: React.FC< Props > = ( { name, children } ) => {
	return (
		<>
			<h1>{ strings.steps[ name ].heading }</h1>
			<h1>{ strings.steps[ name ].subheading }</h1>
			{ children }
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
			<Step name="loading">
				<Loading />
			</Step>
		</Stepper>
	);
};

const OnboardingPrototype: React.FC = () => {
	return (
		<div className="wcpay-onboarding-prototype">
			<OnboardingContextProvider>
				<OnboardingStepper />
			</OnboardingContextProvider>
		</div>
	);
};

export default OnboardingPrototype;
