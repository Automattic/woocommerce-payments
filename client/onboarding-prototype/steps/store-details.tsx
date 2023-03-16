/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { OnboardingForm, OnboardingSelectField } from '../form';

const annualRevenues = Object.entries( strings.annualRevenues ).map(
	( [ key, name ] ) => ( {
		key,
		name,
	} )
);
const goLiveTimeframes = Object.entries( strings.goLiveTimeframes ).map(
	( [ key, name ] ) => ( {
		key,
		name,
	} )
);

const StoreDetails: React.FC = () => {
	return (
		<OnboardingForm>
			<OnboardingSelectField
				name="annual_revenue"
				options={ annualRevenues }
			/>
			<OnboardingSelectField
				name="go_live_timeframe"
				options={ goLiveTimeframes }
			/>
		</OnboardingForm>
	);
};

export default StoreDetails;
