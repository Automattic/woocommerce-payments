/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { OnboardingSelectField } from '../form';

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
		<>
			<OnboardingSelectField
				name="annual_revenue"
				options={ annualRevenues }
			/>
			<OnboardingSelectField
				name="go_live_timeframe"
				options={ goLiveTimeframes }
			/>
		</>
	);
};

export default StoreDetails;
