/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';

/**
 * Internal dependencies.
 */
import { Stepper, StepperIndicator, useStepperContext } from '../stepper';
import { Button } from 'wcpay/components/wp-components-wrapped';
import '../style.scss';

interface StepProps {
	name: string;
}

const StepOne: React.FC< StepProps > = () => {
	const { nextStep } = useStepperContext();
	return (
		<div>
			<h2>General evidence</h2>
			<p>Provide general evidence for your dispute.</p>
			<Button variant="primary" onClick={ nextStep }>
				Next
			</Button>
		</div>
	);
};

const StepTwo: React.FC< StepProps > = () => {
	const { nextStep, prevStep } = useStepperContext();
	return (
		<div>
			<h2>Shipping information</h2>
			<p>Provide shipping details if applicable.</p>
			<Button variant="secondary" onClick={ prevStep }>
				Previous
			</Button>
			<Button variant="primary" onClick={ nextStep }>
				Next
			</Button>
		</div>
	);
};

const StepThree: React.FC< StepProps > = () => {
	const { prevStep } = useStepperContext();
	return (
		<div>
			<h2>Review</h2>
			<p>Review your information before submitting.</p>
			<Button variant="secondary" onClick={ prevStep }>
				Previous
			</Button>
			<Button variant="primary" disabled>
				Submit
			</Button>
		</div>
	);
};

export default () => {
	const steps = [ 'General evidence', 'Shipping information', 'Review' ];
	const [ currentStep, setCurrentStep ] = useState( 0 );

	return (
		<>
			<StepperIndicator steps={ steps } currentStep={ currentStep } />
			<Stepper
				initialStep={ steps[ currentStep ] }
				onStepChange={ ( step ) =>
					setCurrentStep( steps.indexOf( step ) )
				}
			>
				<StepOne name="General evidence" />
				<StepTwo name="Shipping information" />
				<StepThree name="Review" />
			</Stepper>
		</>
	);
};
