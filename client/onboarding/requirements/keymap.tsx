/**
 * Internal dependencies
 */
import { requirements } from './strings';

interface KeyMap {
	[ key: string ]: string;
}

export const individual: KeyMap = {
	'individual.first_name': requirements.name,
	'individual.dob.day': requirements.dob,
	'individual.address.line1': requirements.address,
	'individual.email': requirements.email,
	'individual.phone': requirements.phone,
	'individual.nationality': requirements.nationality,
};

export const individualTaxKeys = [
	'individual.ssn_last_4',
	'individual.id_number',
];

export const representative: KeyMap = {
	'representative.first_name': requirements.name,
	'representative.dob.day': requirements.dob,
	'representative.address.line1': requirements.address,
	'representative.email': requirements.email,
	'representative.phone': requirements.phone,
	'representative.nationality': requirements.nationality,
	'representative.id_number': requirements.ssn,
};

export const company: KeyMap = {
	'company.name': requirements.name,
	'company.address.line1': requirements.address,
	'company.phone': requirements.phone,
	'company.tax_id': requirements.taxId,
	'company.registration_number': requirements.registrationNumber,
};

export const companyDetails: KeyMap = {
	'owners.first_name': requirements.name,
	'owners.dob.day': requirements.dob,
	'owners.address.line1': requirements.address,
	'owners.email': requirements.email,
	'owners.id_number': requirements.ssn,
	'directors.first_name': requirements.name,
	'directors.dob.day': requirements.dob,
	'directors.address.line1': requirements.address,
	'directors.email': requirements.email,
	'executives.first_name': requirements.name,
	'executives.dob.day': requirements.dob,
	'executives.address.line1': requirements.address,
	'executives.email': requirements.email,
};

export const mapToList = ( keys: string[], map: KeyMap ): string => {
	const list = new Set();

	for ( const key in map ) {
		if ( keys.includes( key ) ) list.add( map[ key ] );
	}

	return Array.from( list ).join( ', ' );
};
