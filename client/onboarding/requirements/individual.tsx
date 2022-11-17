/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import RequirementGroup from './group';
import { groups, requirements } from './strings';
import { individual, mapToList, individualTaxKeys } from './keymap';

interface IndividualRequirementsProps {
	keys: string[];
}

const IndividualRequirements = ( {
	keys,
}: IndividualRequirementsProps ): JSX.Element => {
	const personalList = mapToList( keys, individual );
	const hasTax = keys.some( ( key ) => individualTaxKeys.includes( key ) );

	return (
		<>
			<RequirementGroup
				icon={ 'user' }
				headline={ groups.personalDetails }
				subline={ personalList }
			/>
			{ hasTax && (
				<RequirementGroup
					icon={ 'institution' }
					headline={ groups.taxInfo }
					subline={ requirements.ssn }
				/>
			) }
			<RequirementGroup
				icon={ 'credit-card' }
				headline={ groups.bankDetails }
			/>
		</>
	);
};

export default IndividualRequirements;
