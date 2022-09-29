/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

import RegularOnboardingNotice from './regular-onboarding-notice';
import OnboardingForm from './onboarding-form';

const OnboardingPage: React.FunctionComponent = () => {
	return (
		<>
			<RegularOnboardingNotice />
			<br />
			<OnboardingForm />
		</>
	);
};

export default OnboardingPage;
