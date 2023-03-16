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
import {
	ProgressiveOnboardingEligibleFields,
	ProgressiveOnboardingEligibleResult,
	OnboardingSteps,
} from './types';
import { fromDotNotation } from './utils';
import PersonalDetails from './steps/personal-details';
import BusinessDetails from './steps/business-details';
import StoreDetails from './steps/store-details';
import strings from './strings';
import apiFetch from '@wordpress/api-fetch';

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

	const isEligibleForPo = async () => {
		// TODO GH-5475 maybe move somewhere, not sure if it's the best place for this
		const businessDetails: ProgressiveOnboardingEligibleFields = {
			country: data.country,
			type: data.business_type,
			mcc: 'computers_peripherals_and_software', // TODO add real MCC here
			annual_revenue: data.annual_revenue,
			go_live_timeframe: data.go_live_timeframe,
		};
		const eligibleResult = await apiFetch<
			ProgressiveOnboardingEligibleResult
		>( {
			path: '/wc/v3/payments/onboarding/router/po_eligible',
			method: 'POST',
			data: {
				business: businessDetails,
			},
		} );

		return 'eligible' === eligibleResult.result;
	};

	const handleComplete = async () => {
		const { connectUrl } = wcpaySettings;
		const resultUrl = ( await isEligibleForPo() )
			? addQueryArgs( connectUrl, {
					progressive: fromDotNotation( data ),
			  } )
			: connectUrl;
		window.location.href = resultUrl;
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
