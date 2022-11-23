/**
 * External dependencies
 */
import React from 'react';
import UserIcon from 'gridicons/dist/user';
import InstitutionIcon from 'gridicons/dist/institution';
import CreditCardIcon from 'gridicons/dist/credit-card';

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
				icon={ UserIcon }
				headline={ groups.personalDetails }
				subline={ personalList }
			/>
			{ hasTax && (
				<RequirementGroup
					icon={ InstitutionIcon }
					headline={ groups.taxInfo }
					subline={ requirements.ssn }
				/>
			) }
			<RequirementGroup
				icon={ CreditCardIcon }
				headline={ groups.bankDetails }
			/>
		</>
	);
};

export default IndividualRequirements;
