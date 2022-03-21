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

export const taxKeys = [ 'individual.ssn_last_4', 'individual.id_number' ];

export const mapToList = ( keys: string[], map: KeyMap ): string => {
	const list = [];

	for ( const key in map ) {
		if ( keys.includes( key ) ) list.push( individual[ key ] );
	}

	return list.join( ', ' );
};
