/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { useOnboardingContext } from '../context';
import { OnboardingSteps } from '../types';
import strings from '../strings';
import { fromDotNotation } from '../utils';
import BusinessDetails from './business-details';
import PersonalDetails from './personal-details';
import StoreDetails from './store-details';

const steps: Record< OnboardingSteps, React.FC > = {
	personal: PersonalDetails,
	business: BusinessDetails,
	store: StoreDetails,
};

const Steps: React.FC = () => {
	const { step, setStep, data } = useOnboardingContext();
	const Step = steps[ step ];

	const handleContinue = () => {
		const keys = Object.keys( steps ) as OnboardingSteps[];
		const index = keys.indexOf( step );
		const nextStep = keys[ index + 1 ];
		if ( nextStep ) {
			setStep( nextStep );
		} else {
			const { connectUrl } = wcpaySettings;
			const url = addQueryArgs( connectUrl, {
				progressive: fromDotNotation( data ),
			} );
			window.location.href = url;
		}
	};

	return (
		<>
			<h1>{ strings.steps[ step ].heading }</h1>
			<h2>{ strings.steps[ step ].subheading }</h2>
			<Step />
			<Button isPrimary onClick={ handleContinue }>
				{ strings.continue }
			</Button>
			<pre>{ JSON.stringify( fromDotNotation( data ), null, 2 ) }</pre>
		</>
	);
};

export default Steps;
