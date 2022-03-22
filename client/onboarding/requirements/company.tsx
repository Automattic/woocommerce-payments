/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { groups } from './strings';
import RequirementGroup from './group';
import { representative, mapToList, company, companyDetails } from './keymap';
import { joinWithConjunction } from 'utils/strings';

interface CompanyRequirementsProps {
	keys: string[];
}

const CompanyDetails = ( { keys }: CompanyRequirementsProps ): JSX.Element => {
	const details = [];

	if ( keys.some( ( key ) => key.includes( 'owners.' ) ) )
		details.push( groups.owner );
	if ( keys.some( ( key ) => key.includes( 'directors.' ) ) )
		details.push( groups.director );
	if ( keys.some( ( key ) => key.includes( 'executives.' ) ) )
		details.push( groups.executive );

	if ( details.length === 0 ) return <></>;

	const headline = `${ groups.company } ${ joinWithConjunction( details ) }`;
	const subline = mapToList( keys, companyDetails );

	return (
		<RequirementGroup
			icon={ 'user' }
			headline={ headline }
			subline={ subline }
		/>
	);
};

const CompanyRequirements = ( {
	keys,
}: CompanyRequirementsProps ): JSX.Element => {
	const companyList = mapToList( keys, company );
	const representativeList = mapToList( keys, representative );

	return (
		<>
			<RequirementGroup
				icon={ 'institution' }
				headline={ groups.companyInfo }
				subline={ companyList }
			/>
			<CompanyDetails keys={ keys } />
			<RequirementGroup
				icon={ 'user' }
				headline={ groups.representativeDetails }
				subline={ representativeList }
			/>
			<RequirementGroup
				icon={ 'credit-card' }
				headline={ groups.companyBank }
			/>
		</>
	);
};

export default CompanyRequirements;
