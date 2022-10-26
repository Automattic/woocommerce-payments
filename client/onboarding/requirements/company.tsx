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
import { groups } from './strings';
import RequirementGroup from './group';
import { representative, mapToList, company, companyDetails } from './keymap';
import { joinWithConjunction } from 'utils/strings';

interface CompanyRequirementsProps {
	keys: string[];
}

const CompanyDetails = ( { keys }: CompanyRequirementsProps ): JSX.Element => {
	const details = [];

	const keysList = keys.join();
	if ( keysList.includes( 'owners.' ) ) details.push( groups.owner );
	if ( keysList.includes( 'directors.' ) ) details.push( groups.director );
	if ( keysList.includes( 'executives.' ) ) details.push( groups.executive );

	if ( details.length === 0 ) return <></>;

	const headline = `${ groups.company } ${ joinWithConjunction( details ) }`;
	const subline = mapToList( keys, companyDetails );

	return (
		<RequirementGroup
			icon={ UserIcon }
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
				icon={ InstitutionIcon }
				headline={ groups.companyInfo }
				subline={ companyList }
			/>
			<CompanyDetails keys={ keys } />
			<RequirementGroup
				icon={ UserIcon }
				headline={ groups.representativeDetails }
				subline={ representativeList }
			/>
			<RequirementGroup
				icon={ CreditCardIcon }
				headline={ groups.companyBank }
			/>
		</>
	);
};

export default CompanyRequirements;
