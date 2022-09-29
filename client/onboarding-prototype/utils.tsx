/**
 * External dependencies
 */
import { forEach, set } from 'lodash';

export const fromDotNotation = (
	records: Record< string, unknown >
): Record< string, unknown > => {
	const result = {};
	forEach( records, ( value, key ) => set( result, key, value ) );
	return result;
};
