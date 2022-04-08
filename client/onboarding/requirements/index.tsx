/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import IndividualRequirements from './individual';
import CompanyRequirements from './company';

interface RequirementsProps {
	type?: string;
	keys: string[];
}

const Requirements = ( { type, keys }: RequirementsProps ): JSX.Element => {
	const isIndividual = type === 'individual';

	return isIndividual ? (
		<IndividualRequirements keys={ keys } />
	) : (
		<CompanyRequirements keys={ keys } />
	);
};

export default Requirements;
