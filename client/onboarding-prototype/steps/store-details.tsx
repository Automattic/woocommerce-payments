/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { useOnboardingContext } from '../context';
import CustomSelectControl, { Item } from 'components/custom-select-control';
import { OnboardingFields } from '../types';

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
	const { data, setData } = useOnboardingContext();

	const selectedAnnualRevenue = annualRevenues.find(
		( item ) => item.key === data.annual_revenue
	);
	const selectedGoLiveTimeframe = goLiveTimeframes.find(
		( item ) => item.key === data.go_live_timeframe
	);

	const getFieldProps = (
		name: keyof Pick<
			OnboardingFields,
			'annual_revenue' | 'go_live_timeframe'
		>
	) => ( {
		label: strings.fields[ name ],
		value: data[ name ] || '',
		placeholder: strings.placeholders[ name ],
		onChange: ( { selectedItem }: { selectedItem?: Item } ) =>
			setData( { [ name ]: selectedItem?.key } ),
	} );

	return (
		<>
			<CustomSelectControl
				{ ...getFieldProps( 'annual_revenue' ) }
				value={ selectedAnnualRevenue }
				options={ annualRevenues }
			/>
			<CustomSelectControl
				{ ...getFieldProps( 'go_live_timeframe' ) }
				value={ selectedGoLiveTimeframe }
				options={ goLiveTimeframes }
			/>
		</>
	);
};

export default StoreDetails;
