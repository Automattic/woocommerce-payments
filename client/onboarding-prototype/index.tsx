/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { OnboardingContextProvider } from './context';
import Steps from './steps';

const OnboardingPrototype: React.FunctionComponent = () => {
	return (
		<OnboardingContextProvider>
			<Steps />
		</OnboardingContextProvider>
	);
};

export default OnboardingPrototype;
