/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { OnboardingContextProvider, useOnboardingContext } from './context';
import { Stepper, useStepperContext } from 'components/stepper';
import { OnboardingSteps } from './types';
import { fromDotNotation } from './utils';
import PersonalDetails from './steps/personal-details';
import BusinessDetails from './steps/business-details';
import StoreDetails from './steps/store-details';
import strings from './strings';

interface Props {
	name: OnboardingSteps;
}
const Step: React.FC< Props > = ( { name, children } ) => {
	const { nextStep } = useStepperContext();
	return (
		<>
			<h1>{ strings.steps[ name ].heading }</h1>
			<h1>{ strings.steps[ name ].subheading }</h1>
			{ children }
			<Button isPrimary onClick={ nextStep }>
				{ strings.continue }
			</Button>
		</>
	);
};

const OnboardingStepper = () => {
	const { data } = useOnboardingContext();

	const handleComplete = () => {
		const { connectUrl } = wcpaySettings;
		const url = addQueryArgs( connectUrl, {
			progressive: fromDotNotation( data ),
		} );
		window.location.href = url;
	};

	return (
		<Stepper onComplete={ handleComplete }>
			<Step name="personal">
				<PersonalDetails />
			</Step>
			<Step name="business">
				<BusinessDetails />
			</Step>
			<Step name="store">
				<StoreDetails />
			</Step>
		</Stepper>
	);
};

const OnboardingPrototype: React.FC = () => {
	return (
		<OnboardingContextProvider>
			<OnboardingStepper />
		</OnboardingContextProvider>
	);
};

export default OnboardingPrototype;
