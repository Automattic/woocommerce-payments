/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { groups } from './strings';
import IndividualRequirements from './individual';

interface RequirementsProps {
	type: 'individual' | 'company' | 'non_profit';
	keys: string[];
}

const Requirements = ( { type, keys }: RequirementsProps ): JSX.Element => {
	const isIndividual = type === 'individual';

	return isIndividual ? <IndividualRequirements keys={ keys } /> : <></>; // TODO: Company
};

export default Requirements;

/*
Individual
==========
"individual.address.city",
"individual.address.line1",
"individual.address.postal_code",
"individual.address.state",
"individual.dob.day",
"individual.dob.month",
"individual.dob.year",
"individual.email",
"individual.first_name",
"individual.id_number",
"individual.last_name",
"individual.phone",
"individual.ssn_last_4",
"individual.verification.document",

Company
=======
"representative.address.city",
"representative.address.line1",
"representative.address.postal_code",
"representative.address.state",
"representative.dob.day",
"representative.dob.month",
"representative.dob.year",
"representative.email",
"representative.first_name",
"representative.id_number",
"representative.last_name",
"representative.phone",
"representative.relationship.executive",
"representative.relationship.title",
"representative.ssn_last_4",
"representative.verification.document",
"company.address.city",
"company.address.line1",
"company.address.postal_code",
"company.address.state",
"company.name",
"company.owners_provided",
"company.phone",
"company.tax_id",
"owners.address.city",
"owners.address.line1",
"owners.address.postal_code",
"owners.address.state",
"owners.dob.day",
"owners.dob.month",
"owners.dob.year",
"owners.email",
"owners.first_name",
"owners.id_number",
"owners.last_name",
"owners.phone",
"owners.ssn_last_4",
"owners.verification.document",

directors.first_name

executives.first_name
*/
